<?php

use NMGR\Lib\Single;

defined( 'ABSPATH' ) || exit;

class NMGR_Admin_Post {

	/**
	 * Is meta boxes saved once?
	 *
	 * @var boolean
	 */
	protected static $saved_meta_boxes = false;

	/**
	 * Meta box notices
	 *
	 * @var array
	 */
	public static $notices = array();

	public static function run() {
		add_filter( 'woocommerce_screen_ids', array( __CLASS__, 'add_screen_id' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'post_updated_messages' ) );
		add_filter( 'nmgr_requested_fields', array( __CLASS__, 'modify_fields_args' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_meta_boxes' ), 10 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 20 );
		add_action( 'save_post_nm_gift_registry', array( __CLASS__, 'setup_created_wishlist_hook' ), 10, 3 );
		add_action( 'save_post_nm_gift_registry', array( __CLASS__, 'save_meta_boxes' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_output_notices' ) );
		add_action( 'shutdown', array( __CLASS__, 'save_notices' ) );
		add_action( 'nmgr_updated_wishlist', array( __CLASS__, 'maybe_trigger_created_wishlist_hook' ), -1 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'output_core_form_fields' ) );
		add_action( 'admin_footer', array( __CLASS__, 'show_choose_wishlist_type_form' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_chosen_wishlist_type' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );
	}

	protected static function is_choose_wishlist_type() {
		global $pagenow, $post;

		return 'post-new.php' === $pagenow &&
			'nm_gift_registry' === get_post_type( $post ) &&
			nmgr_get_type_option( 'gift-registry', 'enable' ) &&
			nmgr_get_type_option( 'wishlist', 'enable' ) &&
			!has_term( [ 'wishlist', 'gift-registry' ], 'nm_gift_registry_type' );
	}

	public static function save_chosen_wishlist_type() {
		if ( !empty( $_POST[ 'nmgr_id_choose_wishist_type' ] ) ) {
			$wishlist = nmgr()->wishlist();
			$wishlist->set_id( ( int ) $_POST[ 'nmgr_id_choose_wishist_type' ] );
			$wishlist->set_type( sanitize_text_field( $_POST[ 'nm_gift_registry_type' ] ) );
			$wishlist->save_type();
		}
	}

	public static function show_choose_wishlist_type_form() {
		if ( self::is_choose_wishlist_type() ) {
			?>
			<style>
				#nmgr-choose-type {
					background-color: white;
					border: 1px solid #ccc;
					min-width: 300px;
					position: fixed;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					padding: 27px;
				}

				#nmgr-choose-type .nmgr-title {
					margin-top: 0;
				}

				form#post {
					opacity: .2;
				}

				form#post * {
					pointer-events: none;
				}
			</style>
			<?php
			self::choose_wishlist_type_form();
		}
	}

	protected static function choose_wishlist_type_form() {
		global $post;
		?>
		<form id="nmgr-choose-type" method="post" action="<?php echo esc_attr( get_edit_post_link( $post->ID ) ); ?>">
			<h3 class="nmgr-title">
				<?php
				echo esc_html( nmgr()->is_pro ?
						__( 'Wishlist type', 'nm-gift-registry' ) :
						__( 'Wishlist type', 'nm-gift-registry-lite' ) );
				?>
			</h3>
			<?php self::wishlist_type_input( $post ); ?>
		</form>
		<?php
	}

	protected static function wishlist_type_input( $post ) {
		$taxonomy = 'nm_gift_registry_type';
		$terms = get_terms( $taxonomy, array( 'hide_empty' => 0 ) );
		$postterms = get_the_terms( $post->ID, $taxonomy );
		$current_term = $postterms && !is_wp_error( $postterms ) ? array_pop( $postterms ) : false;
		$current_term_id = $current_term ? $current_term->term_id : 0;
		?>
		<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
			<?php
			foreach ( $terms as $term ) {
				if ( $current_term_id && $current_term_id !== $term->term_id ) {
					continue;
				}

				$checkbox_args = array(
					'input_type' => 'radio',
					'input_name' => $taxonomy,
					'input_value' => $term->slug,
					'input_id' => 'nmgr-' . $taxonomy . '-' . $term->term_id,
					'checked' => checked( $current_term_id, $term->term_id, false ),
					'label_text' => $term->name,
					'label_before' => true,
				);

				if ( !$current_term_id ) {
					$checkbox_args[ 'input_attributes' ] = [
						'onChange' => "this.form.submit()",
					];
				}

				echo '<div>' . nmgr_get_checkbox_switch( $checkbox_args ) . '</div>';
			}
			?>
		</div>
		<?php
		if ( !$current_term_id ) :
			?>
			<input type="hidden" name="nmgr_id_choose_wishist_type" value="<?php echo $post->ID; ?>">
			<?php
		endif;
	}

	/**
	 * Set the nm_gift_registry post type admin page as a woocommerce admin page
	 * (lazily just so that woocommerce can enqueue its admin styles for our form fields)
	 */
	public static function add_screen_id( $screen_ids ) {
		$screen_ids[] = 'nm_gift_registry';
		return $screen_ids;
	}

	public static function post_updated_messages( $messages ) {
		if ( !empty( $messages[ 'post' ] ) ) {
			$messages[ 'nm_gift_registry' ] = $messages[ 'post' ];
			$messages[ 'nm_gift_registry' ][ 1 ] = nmgr()->is_pro ?
				__( 'Wishlist updated.', 'nm-gift-registry' ) :
				__( 'Wishlist updated.', 'nm-gift-registry-lite' );
		}
		return $messages;
	}

	/**
	 * Modify field arguments for plugin fields specially for admin page
	 *
	 * @param type $fields Plugin form fields.
	 * @return array Modified fields
	 */
	public static function modify_fields_args( $fields ) {
		if ( !is_nmgr_admin() ) {
			return $fields;
		}

		foreach ( $fields as $key => $args ) {
			switch ( $key ) {
				case 'shipping_country':
					$fields[ $key ][ 'class' ][] = 'form-row-first';
					break;
				case 'shipping_state':
					$fields[ $key ][ 'class' ][] = 'form-row-last';
					break;
				case 'shipping_address_1':
					$fields[ $key ][ 'class' ][] = 'form-row-first';
					break;
				case 'shipping_address_2':
					$fields[ $key ][ 'class' ][] = 'form-row-last';
					break;
				case 'shipping_city':
					$fields[ $key ][ 'class' ][] = 'form-row-first';
					break;
				case 'shipping_postcode':
					$fields[ $key ][ 'class' ][] = 'form-row-last';
					break;
			}
		}
		return $fields;
	}

	/**
	 * Add a notice
	 *
	 * @param string $text The notice
	 * @param string $notice_type The type of notice. Should be success, error, or notice. Default is notice.
	 */
	public static function add_notice( $text, $notice_type = 'notice' ) {
		global $pagenow;

		if ( 'post.php' === $pagenow ) {
			self::$notices[] = array(
				'message' => $text,
				'type' => $notice_type
			);
		}
	}

	/**
	 * Save notices to an option.
	 */
	public static function save_notices() {
		if ( !empty( self::$notices ) ) {
			update_option( 'nmgr_metabox_notices', self::$notices );
		}
	}

	public static function maybe_output_notices() {
		$notices = array_filter( ( array ) get_option( 'nmgr_metabox_notices' ) );
		if ( !empty( $notices ) ) {
			self::$notices = $notices;

			delete_option( 'nmgr_metabox_notices' );

			add_action( 'admin_notices', array( __CLASS__, 'output_notices' ) );
		}
	}

	/**
	 * Show any stored messages.
	 */
	public static function output_notices() {
		if ( empty( self::$notices ) ) {
			return;
		}

		foreach ( self::$notices as $notice ) {
			if ( !is_array( $notice ) || !isset( $notice[ 'message' ], $notice[ 'type' ] ) || !in_array( $notice[ 'type' ], array( 'success', 'error', 'notice' ) ) ) {
				continue;
			}

			switch ( $notice[ 'type' ] ) {
				case 'error':
					echo '<div class="error notice is-dismissible"><p>' . wp_kses_post( $notice[ 'message' ] ) . '</p></div>';
					break;
				case 'notice':
					echo '<div class="notice-info notice is-dismissible"><p>' . wp_kses_post( $notice[ 'message' ] ) . '</p></div>';
					break;
				case 'success':
					echo '<div class="updated notice is-dismissible"><p>' . wp_kses_post( $notice[ 'message' ] ) . '</p></div>';
					break;
			}
		}

		self::$notices = array();
	}

	protected static function is_gift_registry() {
		global $post;
		return is_a( $post, 'WP_Post' ) ? !has_term( 'wishlist', 'nm_gift_registry_type', $post->ID ) : true;
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'nm_gift_registry_typediv',
			nmgr()->is_pro ?
				__( 'Wishlist type', 'nm-gift-registry' ) :
				__( 'Wishlist type', 'nm-gift-registry-lite' ),
			array( __CLASS__, 'wishlist_type_metabox' ),
			'nm_gift_registry',
			'normal',
			'high'
		);

		add_meta_box(
			'nm_gift_registry-profile',
			nmgr()->is_pro ?
				__( 'Profile', 'nm-gift-registry' ) :
				__( 'Profile', 'nm-gift-registry-lite' ),
			array( __CLASS__, 'profile_metabox' ),
			'nm_gift_registry',
			'normal',
			'high'
		);

		add_meta_box(
			'nm_gift_registry-items',
			nmgr()->is_pro ?
				__( 'Items', 'nm-gift-registry' ) :
				__( 'Items', 'nm-gift-registry-lite' ),
			array( __CLASS__, 'items_metabox' ),
			'nm_gift_registry',
			'normal'
		);
	}

	public static function remove_meta_boxes() {
		if ( 'nm_gift_registry' === get_post_type() ) {
			remove_meta_box( 'commentsdiv', 'nm_gift_registry', 'normal' );
			remove_meta_box( 'commentstatusdiv', 'nm_gift_registry', 'side' );
			remove_meta_box( 'commentstatusdiv', 'nm_gift_registry', 'normal' );
			remove_meta_box( 'nm_gift_registry_typediv', 'nm_gift_registry', 'side' );

			if ( !self::is_gift_registry() ) {
				remove_meta_box( 'postimagediv', 'nm_gift_registry', 'side' );
			}
		}
	}

	public static function wishlist_type_metabox( $post ) {
		self::wishlist_type_input( $post );
	}

	public static function profile_metabox( $post ) {
		$form = new NMGR_Form( $post->ID );
		$user = '';
		$user_id = '';
		$user_string = '';
		$enable_shipping = nmgr_get_option( 'enable_shipping' );

		// If the post is not a new post and we have a post author, get his user details and account shipping details
		if ( ('auto-draft' !== $post->post_status) && get_post_meta( $post->ID, '_nmgr_user_id', true ) ) {
			$user_id = get_post_meta( $post->ID, '_nmgr_user_id', true );
			$user_string = nmgr()->is_pro ?
				__( 'Guest', 'nm-gift-registry' ) :
				__( 'Guest', 'nm-gift-registry-lite' );

			if ( is_numeric( $user_id ) ) {
				$user = new \WC_Customer( $form->get_wishlist()->get_user_id() );
				$name = $user->get_first_name() . ' ' . $user->get_last_name();
				$user_string = sprintf(
					esc_html( '%1$s (%2$s)' ),
					trim( $name ) ? $name : $user->get_display_name(),
					$user->get_email()
				);
			}
		}

		$profile_fields_1 = array_keys( $form->get_fields( 'profile', array(), true, false ) );

		$ignore_profile_fields = array(
			'title'
		);

		$profile_fields_2 = array_diff( $profile_fields_1, $ignore_profile_fields );

		$user_details_text = nmgr()->is_pro ?
			__( 'User Details', 'nm-gift-registry' ) :
			__( 'User Details', 'nm-gift-registry-lite' );
		$profile_fields = $form->get_fields_html( $profile_fields_2, '', $user_details_text );

		$has_profile_fields = $form->has_fields();

		add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
			$fields[ 'state' ][ 'priority' ] = 45; // Set state field to be after country field.
			return $fields;
		} );

		$wishlist_shipping = $form->get_fields_html( 'shipping' );
		$has_wishlist_shipping = $form->has_fields();

		$class = $has_profile_fields &&
			$enable_shipping &&
			( true === $has_wishlist_shipping) ? 'two-col' : '';

		$is_archived = is_callable( [ $form->get_wishlist(), 'is_archived' ] ) ?
			$form->get_wishlist()->is_archived() :
			false;
		?>
		<fieldset <?php echo $is_archived ? 'disabled' : ''; ?>>
			<p class="nmgr-user">
				<label for="_nmgr_user_id">
					<?php
					echo esc_html(
						nmgr()->is_pro ?
							__( 'User:', 'nm-gift-registry' ) :
							__( 'User:', 'nm-gift-registry-lite' )
					);
					?> </label>
				<select class="nmgr-user-search"
								id="_nmgr_user_id"
								name="user_id"
								data-placeholder="<?php
								printf(
									/* translators: %s: wishlist type title */
									nmgr()->is_pro ? esc_attr__( 'Enter name of %s owner', 'nm-gift-registry' ) : esc_attr__( 'Enter name of %s owner', 'nm-gift-registry-lite' ),
									esc_html( nmgr_get_type_title( '', false, 'wishlist' ) )
								);
								?>"
								data-allow-clear="true">
					<option value="">&nbsp;</option> <!--- allow selection of empty value -->
					<option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo htmlspecialchars( wp_kses_post( $user_string ) ); ?></option>
				</select>
			</p>

			<?php if ( self::is_gift_registry() ) : ?>

				<div class="wishlist-columns <?php echo esc_attr( $class ); ?>">

					<?php if ( $has_profile_fields ) : ?>
						<div class='column'><?php echo $profile_fields; ?></div>
					<?php endif; ?>

					<?php if ( $enable_shipping && $has_wishlist_shipping ) : ?>
						<div class='column'>
							<h3>
								<?php
								echo esc_html( nmgr()->is_pro ?
										__( 'Shipping Details', 'nm-gift-registry' ) :
										__( 'Shipping Details', 'nm-gift-registry-lite' ) );
								?>
							</h3>
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
							if ( $user &&
								((method_exists( $user, 'has_shipping_address' ) && $user->has_shipping_address()) ||
								$user->get_shipping_address())
							) {
								nmgr_show_copy_shipping_address_btn( $user->get_shipping() );
							}

							echo $has_wishlist_shipping ? "<div class='wishlist-shipping-address'>$wishlist_shipping</div>" : '';
							// phpcs:enable
							?>
						</div>
					<?php endif; ?>
				</div>

			<?php endif; ?>
		</fieldset>
		<?php
	}

	public static function items_metabox( $post ) {
		echo nmgr_get_account_section( 'items', $post->ID );
	}

	public static function save_meta_boxes( $post_id ) {
		if ( !is_nmgr_admin() || self::$saved_meta_boxes ||
			!current_user_can( 'edit_nm_gift_registries', $post_id ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			'editpost' !== ( $_REQUEST[ 'action' ] ?? '' ) ) {
			return;
		}

		// Flag the save event to run once to avoid endless loops.
		self::$saved_meta_boxes = true;

		// Save wishlist post meta fields
		try {
			if ( (isset( $_REQUEST[ '_wpnonce' ] ) &&
				!wp_verify_nonce( $_REQUEST[ '_wpnonce' ], 'update-post_' . $post_id )) ||
				self::$notices ) {
				return;
			}

			$form = new NMGR_Form( $post_id );
			$form->set_data( $_REQUEST )->validate();

			if ( $form->has_errors() ) {
				foreach ( $form->get_error_messages() as $message ) {
					self::add_notice( $message, 'error' );
				}

				$data = $form->get_data();

				foreach ( $form->get_error_fields() as $error_field ) {
					if ( isset( $data[ $error_field ] ) ) {
						unset( $data[ $error_field ] );
					}
				}
				$form->set_data( $data );
			}

			$form->save();
		} catch ( Exception $e ) {
			self::add_notice( $e->getMessage(), 'error' );
		}
	}

	public static function setup_created_wishlist_hook( $post_id, $post, $update ) {
		if ( is_nmgr_admin() && !$update ) {
			update_post_meta( $post_id, 'trigger_created_wishlist_action', 1 );
		}
	}

	public static function maybe_trigger_created_wishlist_hook( $post_id ) {
		if ( is_nmgr_admin() && get_post_meta( $post_id, 'trigger_created_wishlist_action', true ) ) {
			delete_post_meta( $post_id, 'trigger_created_wishlist_action' );
			do_action( 'nmgr_created_wishlist', $post_id );
		}
	}

	/**
	 * Output fields needed to save the form
	 */
	public static function output_core_form_fields( $post ) {
		if ( 'nm_gift_registry' === get_post_type( $post ) ) {
			$form = new \NMGR_Form( $post->ID );
			echo $form->get_fields_html( array( 'wishlist_id' ) );
			echo $form->get_nonce_field();
		}
	}

	public static function show_notices() {
		global $post, $pagenow;

		if ( is_nmgr_admin() && 'post.php' === $pagenow ) {
			$template = new Single( $post->ID );
			$template->process_notices();
			foreach ( $template->get_notices() as $notice ) {
				printf( '<div class="notice notice-info"><p>%s</p></div>',
					wp_kses_post( $notice[ 'message' ] )
				);
			}
		}
	}

}
