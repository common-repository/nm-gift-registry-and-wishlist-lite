<?php
/**
 * Sync
 */

namespace NMGR\Setup\Wizard;

use NMGR\Setup\Wizard\Fields;

defined( 'ABSPATH' ) || exit;

class Wizard {

	private static $sections = [];

	public static function run() {
		self::$sections = self::get_sections();

		add_action( 'admin_init', [ __CLASS__, 'redirect_to_setup' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'admin_menu', [ __CLASS__, 'save_settings' ] );
		add_action( 'admin_notices', [ __CLASS__, 'output_notices' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_filter( 'removable_query_args', [ __CLASS__, 'remove_query_arg' ] );
	}

	public static function install_actions() {
		$db_versions = array_filter( [ get_option( 'nmgr_version' ), get_option( 'nmgrlite_version' ) ] );

		foreach ( $db_versions as $installed_version ) {
			if ( version_compare( $installed_version, '4.0.0', '>=' ) ) {
				return;
			}
		}

		$has_old_version = version_compare( get_option( nmgr()->prefix . '_version' ), '4.0.0', '<' );
		$is_new_install = !get_option( 'nmgr_settings' );

		// Maybe setup redirection
		if ( $has_old_version || $is_new_install ) {
			set_transient( 'nmgr_setup_redirect', ($is_new_install ? 'new' : 'update' ), 300 );
		}

		if ( $has_old_version && !$is_new_install ) {
			self::update_settings();
		}
	}

	public static function update_settings() {
		$existing_settings = get_option( 'nmgr_settings' );

		// Enable gift registry by default
		if ( !array_key_exists( 'wishlist_enable', $existing_settings ) ) {
			$existing_settings[ 'wishlist_enable' ] = '';
		}

		// Disable wishlist by default
		if ( !array_key_exists( 'enable', $existing_settings ) ) {
			$existing_settings[ 'enable' ] = 1;
		}

		// Setup the proper gift registry page
		if ( !array_key_exists( 'page_id', $existing_settings ) ) {
			if ( !empty( $existing_settings[ 'wishlist_archive_page_id' ] ) ) {
				$existing_settings[ 'page_id' ] = $existing_settings[ 'wishlist_archive_page_id' ];
				unset( $existing_settings[ 'wishlist_archive_page_id' ] );
			} elseif ( !empty( $existing_settings[ 'wishlist_single_page_id' ] ) ) {
				$existing_settings[ 'page_id' ] = $existing_settings[ 'wishlist_single_page_id' ];
				unset( $existing_settings[ 'wishlist_single_page_id' ] );
			}
		}

		update_option( 'nmgr_settings', $existing_settings );
	}

	public static function redirect_to_setup() {
		$transient = get_transient( 'nmgr_setup_redirect' );

		if ( $transient && !wp_doing_ajax() ) {
			delete_transient( 'nmgr_setup_redirect' );
			$url = add_query_arg( 'nmgr-key', $transient, admin_url( 'edit.php?post_type=nm_gift_registry&page=nmgr-setup' ) );
			wp_redirect( $url );
			exit;
		}
	}

	public static function is_plugin_update() {
		return 'update' === ($_GET[ 'nmgr-key' ] ?? '');
	}

	public static function remove_query_arg( $args ) {
		$args[] = 'nmgr-wizard';
		return $args;
	}

	public static function output_notices() {
		global $pagenow, $typenow;

		if ( 'edit.php' === $pagenow && 'nm_gift_registry' === $typenow && 'saved' === ( $_GET[ 'nmgr-wizard' ] ?? '' ) ) {
			$message = nmgr()->is_pro ?
				__( 'Settings saved.', 'nm-gift-registry' ) :
				__( 'Settings saved.', 'nm-gift-registry-lite' );

			echo '<div class="updated notice is-dismissible"><p><strong>' . wp_kses_post( $message ) . '</strong></p></div>';
		}
	}

	public static function save_settings() {
		global $wpdb;

		if ( !empty( $_POST[ 'nmgr_setup_submit' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ] ) ) {
			$referer = [];
			parse_str( $_POST[ '_wp_http_referer' ], $referer );
			$posted_section = $referer[ 'section' ] ?? self::get_first_section();

			if ( !empty( $_POST[ 'nmgr_setup_save' ] ) ) {
				$fields = new Fields();
				$fields->set_type( $posted_section );

				// Sanitize posted data
				$sanitized_posted_data = array_map( [ $fields, 'sanitize' ], $_POST );

				// Maybe prefix field key
				$field_key = function ( $key ) use ( $posted_section ) {
					return 'wishlist' === $posted_section ? 'wishlist_' . $key : $key;
				};

				// Maybe create page
				$page_id_key = $field_key( 'page_id' );

				if ( 'wishlist' === $posted_section ) {
					$shortcode = '[nmgr_wishlist]';
				} else {
					$shortcode = !empty( $sanitized_posted_data[ 'enable_archives' ] ) ? '[nmgr_archive]' : '[nmgr_wishlist]';
				}
				$post_content = '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->';

				if ( 'create' === ($sanitized_posted_data[ $page_id_key ] ?? '') ) {
					$page_args = [
						'post_title' => $sanitized_posted_data[ $field_key( 'page_title' ) ],
						'post_content' => $post_content,
						'post_status' => 'publish',
						'post_type' => 'page',
						'comment_status' => 'closed',
					];

					$page_id = wp_insert_post( $page_args );

					$sanitized_posted_data[ $page_id_key ] = $page_id;
				} elseif ( !empty( $sanitized_posted_data[ $page_id_key ] ) ) {
					$page = get_post( $sanitized_posted_data[ $page_id_key ] );

					// Remove alternative shortcode
					$alt_shortcode = '[nmgr_archive]' === $shortcode ? '[nmgr_wishlist]' : '[nmgr_archive]';
					$wp_alt_shortcode = '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->';
					$page_content = str_replace( [ $alt_shortcode, $wp_alt_shortcode ], '', $page->page_content );

					$db_search = strtok( $shortcode, ' ' );
					$has_shortcode = $wpdb->get_var( $wpdb->prepare(
							"SELECT ID FROM $wpdb->posts
								WHERE post_type='page'
								AND post_status='publish'
								AND ID=%d
								AND post_content LIKE %s
								LIMIT 1;",
							$page->ID,
							"%{$db_search}%"
						) );

					if ( !$has_shortcode ) {
						$post_content = $post_content . $page_content;
						$page_args = [
							'post_content' => $post_content,
							'comment_status' => 'closed',
							'ID' => $page->ID,
						];

						$page_id = wp_update_post( $page_args );
					}
				}

				// Get all fields with their default values
				$fields_data = $fields->get();
				$default_values = array_column( $fields_data, 'default', 'id' );

				// Get all fields with their current posted values if present
				$current_values = nmgr_merge_args( $default_values, $sanitized_posted_data );

				// Get fields to save directly in plugin settings
				$settings_fields_keys = array_column( $fields->get_settings_fields(), 'id', 'id' );
				$save_in_settings = array_intersect_key( $current_values, $settings_fields_keys );
				$db_settings = get_option( 'nmgr_settings' );
				$updated_db_settings = array_merge( $db_settings, $save_in_settings );
				update_option( 'nmgr_settings', $updated_db_settings );

				flush_rewrite_rules();
			}

			$next_section_key = self::get_next_section( $posted_section );
			if ( $next_section_key ) {
				$url = add_query_arg( 'section', $next_section_key );
			} else {
				$url = add_query_arg( 'nmgr-wizard', 'saved', admin_url( 'edit.php?post_type=nm_gift_registry' ) );
			}

			wp_redirect( $url );
			exit;
		}
	}

	public static function enqueue_scripts() {
		if ( 'nmgr-setup' !== ($_GET[ 'page' ] ?? '') ) {
			return;
		}

		wp_enqueue_style( 'nmgr-admin' );
		wp_enqueue_style( 'nmgr-setup',
			nmgr()->url . 'includes/Setup/Wizard/assets/css/style.css',
			[],
			nmgr()->version
		);
		wp_enqueue_script( 'nmgr-setup',
			nmgr()->url . 'includes/Setup/Wizard/assets/js/script.js',
			[ 'jquery' ],
			nmgr()->version,
			true
		);
	}

	public static function get_title() {
		return nmgr()->is_pro ?
			__( 'Setup', 'nm-gift-registry' ) :
			__( 'Setup', 'nm-gift-registry-lite' );
	}

	public static function add_page() {
		$prefix = self::get_title();
		$title = $prefix . ' &ndash; ' . nmgr()->name;

		add_submenu_page( 'edit.php?post_type=nm_gift_registry', $title, $title, 'read', 'nmgr-setup', [ __CLASS__, 'page' ] );
		remove_submenu_page( 'edit.php?post_type=nm_gift_registry', 'nmgr-setup' );
	}

	public static function page() {
		$current_section = self::get_current_section();
		$previous_section = self::get_previous_section( $current_section );
		$filename = 'welcome' === $current_section ? 'welcome' : 'wishlist';
		$file = __DIR__ . '/sections/' . $filename . '.php';
		?>

		<form id="nmgr-setup" method="post" action="">
			<div class="nmgr-hearts nmgr-tc"><span>&hearts;</span></div>
			<h2 class="nmgr-name nmgr-tc"><?php echo esc_html( nmgr()->name ); ?></h2>
			<h3 class="nmgr-page-title nmgr-tc"><?php echo esc_html( self::get_title() ); ?></h3>
			<div id="nmgr-setup-tabs" role="tablist" class="nmgr-tc">
				<?php
				foreach ( self::$sections as $key => $args ):
					$active_class = $current_section === $key ? 'active' : '';
					$url = add_query_arg( 'section', $key );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nmgr-setup-tab-item <?php echo $active_class; ?>">
						<?php echo esc_html( $args[ 'title' ] ); ?>
					</a>
					<?php
				endforeach;
				?>
			</div>
			<div id="nmgr-content">
				<?php
				if ( $current_section && file_exists( $file ) ) {
					include_once $file;
				}
				?>
				<div class="nmgr-footer <?php echo $previous_section ? 'has-previous' : ''; ?>">
					<?php
					if ( $previous_section ) :
						?>
						<a class="button nmgr" href="<?php echo esc_url( add_query_arg( 'section', $previous_section ) ); ?>">
							<?php
							echo esc_html( nmgr()->is_pro ?
									__( 'Back', 'nm-gift-registry' ) :
									__( 'Back', 'nm-gift-registry-lite' ) );
							?>
						</a>
						<?php
					endif;
					?>

					<?php
					$continue_text = nmgr()->is_pro ?
						__( 'Continue', 'nm-gift-registry' ) :
						__( 'Continue', 'nm-gift-registry-lite' );
					?>
					<input name="nmgr_setup_submit" class="button button-primary nmgr" type="submit"
								 value="<?php echo esc_attr( $continue_text ); ?>" />
				</div>
			</div>
			<?php wp_nonce_field(); ?>
		</form>
		<?php
	}

	private static function get_first_section() {
		$section_keys = array_keys( self::$sections );
		return reset( $section_keys );
	}

	private static function get_current_section() {
		return sanitize_text_field( $_GET[ 'section' ] ?? self::get_first_section() );
	}

	private static function get_next_section( $current_section ) {
		$keys = array_keys( self::$sections );
		$position = array_search( $current_section, $keys, true );

		if ( false !== $position && array_key_exists( $position + 1, $keys ) ) {
			return $keys[ $position + 1 ];
		}
	}

	private static function get_previous_section( $current_section ) {
		$keys = array_keys( self::$sections );
		$position = array_search( $current_section, $keys, true );

		if ( false !== $position && array_key_exists( $position - 1, $keys ) ) {
			return $keys[ $position - 1 ];
		}
	}

	private static function get_sections() {
		$sections = [
			'welcome' => [
				'title' => nmgr()->is_pro ?
				__( 'Welcome', 'nm-gift-registry' ) :
				__( 'Welcome', 'nm-gift-registry-lite' ),
				'priority' => 10,
			],
			'wishlist' => [
				'title' => nmgr()->is_pro ?
				__( 'Wishlist', 'nm-gift-registry' ) :
				__( 'Wishlist', 'nm-gift-registry-lite' ),
				'priority' => 20,
			],
			'gift-registry' => [
				'title' => nmgr()->is_pro ?
				__( 'Gift Registry', 'nm-gift-registry' ) :
				__( 'Gift Registry', 'nm-gift-registry-lite' ),
				'priority' => 'update' === ($_GET[ 'nmgr-key' ] ?? '') ? 15 : 30,
			],
		];

		\NMGR\Fields\Fields::sort_by_priority( $sections );

		return $sections;
	}

}
