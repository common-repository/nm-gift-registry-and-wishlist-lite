<?php

/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

use NMGR\Setup\Wizard\Wizard,
		NMGR\Setup\Upgrader,
		NMGR\Events\SetExpiredWishlists,
		NMGR\Events\SetWishlistTerms,
		NMGR\Events\DeleteExtraUserWishlists,
		NMGR\Events\UpdateOrderItemMeta;

class NMGR_Setup {

	/**
	 * @var \NMGR_Props|\NMGR\Sub\Props
	 */
	private $plugin_props;
	public $file;

	public function __construct( $filepath ) {
		$this->file = $filepath;

		spl_autoload_register( array( $this, 'autoload' ) );

		$props_class = $this->get_props_class();
		$this->plugin_props = new $props_class( $filepath );
	}

	protected function autoload( $class ) {
		$namespace = 'NMGR\\';

		if ( !class_exists( $class ) && false !== stripos( $class, $namespace ) ) {
			// Replace the namespace with the directory
			$path1 = str_replace( $namespace, trailingslashit( __DIR__ ), $class );
			// Change the namespace separators to directory separators
			$path2 = str_replace( '\\', '/', $path1 );
			// Add the file extension
			$path = $path2 . '.php';

			if ( file_exists( $path ) ) {
				include_once $path;
			}
		} elseif ( !class_exists( $class ) && false !== stripos( $class, 'nmgr' ) ) {
			$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
			$filepath = trailingslashit( __DIR__ ) . $file;

			if ( file_exists( $filepath ) ) {
				include_once $filepath;
			}
		}
	}

	private function get_props_class() {
		$pro_class = \NMGR\Sub\Props::class;
		return class_exists( $pro_class ) ? $pro_class : \NMGR_Props::class;
	}

	public function get_plugin_props() {
		return $this->plugin_props;
	}

	public function load() {
		if ( is_admin() ) {
			register_activation_hook( $this->file, array( $this, 'activate' ) );
			register_deactivation_hook( $this->file, array( $this, 'deactivate' ) );

			$uninstall_method = $this->plugin_props->is_pro ? 'uninstall_pro' : 'uninstall_lite';
			register_uninstall_hook( $this->file, array( static::class, $uninstall_method ) );
		}

		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 30 );
		add_filter( 'plugin_action_links', array( $this, 'show_disabled_notices' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'maybe_install_and_run' ) );
		add_action( 'before_woocommerce_init', array( $this, 'hpos_compat' ) );
	}

	public function hpos_compat() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				$this->file,
				true
			);
		}
	}

	public function plugin_row_meta( $links, $file ) {
		if ( $file == $this->plugin_props->basename ) {
			$defaults = [
				'docs_url' => $this->plugin_props->is_pro ?
				__( 'Docs', 'nm-gift-registry' ) :
				__( 'Docs', 'nm-gift-registry-lite' ),
				'support_url' => $this->plugin_props->is_pro ?
				__( 'Support', 'nm-gift-registry' ) :
				__( 'Support', 'nm-gift-registry-lite' ),
				'review_url' => $this->plugin_props->is_pro ?
				__( 'Review', 'nm-gift-registry' ) :
				__( 'Review', 'nm-gift-registry-lite' ),
			];

			foreach ( $defaults as $url => $text ) {
				if ( !empty( $this->plugin_props->{$url} ) ) {
					$links[] = '<a target="_blank" href="' . $this->plugin_props->{$url} . '">' . $text . '</a>';
				}
			}

			if ( !$this->plugin_props->is_pro && !empty( $this->plugin_props->product_url ) ) {
				$links[] = '<a target="_blank" href="' . $this->plugin_props->product_url . '" style="color:#b71401;"><strong>' . __( 'Get PRO', 'nm-gift-registry-lite' ) . '</strong></a>';
			}
		}
		return $links;
	}

	public function load_plugin_textdomain() {
		if ( $this->plugin_props->is_pro ) {
			load_plugin_textdomain( 'nm-gift-registry', false, plugin_basename( dirname( NMGR_FILE ) ) . '/languages' );
		} else {
			$domain = 'nm-gift-registry-lite';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

			load_textdomain( $domain, WP_LANG_DIR . '/plugins/nm-gift-registry-and-wishlist-lite-' . $locale . '.mo' );
			load_plugin_textdomain( $domain, false, plugin_basename( dirname( NMGRLITE_FILE ) ) . '/languages' );
		}
	}

	public function activate() {
		if ( $this->plugin_props->is_pro ) {
			if ( function_exists( 'nm_gift_registry_lite' ) ) {
				deactivate_plugins( nm_gift_registry_lite()->basename );
			}
		} else {
			if ( function_exists( 'nm_gift_registry' ) ) {
				deactivate_plugins( nm_gift_registry()->basename );
			}
		}
		$this->plugin_props->flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function maybe_install_and_run() {
		if ( $this->maybe_disable() ) {
			return;
		}

		$this->maybe_disable_crowdfunding_plugin();

		// At this point we can install and run our plugin

		include_once $this->plugin_props->path . 'includes/nmgr-functions.php';

		// Installation action
		if ( version_compare(
				get_option( $this->plugin_props->prefix . '_version' ),
				$this->plugin_props->version,
				'<'
			) ) {
			add_action( 'init', array( $this, 'install_actions' ), 30 );
		}

		// Run plugin
		$this->run();
	}

	public function install_actions() {
		Wizard::install_actions();

		Upgrader::run();

		$this->create_tables();
		$this->add_default_settings();
		static::add_capabilities();

		// register taxonomy (for inserting terms and flushing rewrite rules)
		nmgr()->wordpress()->register_taxonomy();

		// Insert terms
		$this->insert_terms();

		// register custom post type (for flushing rewrite rules)
		nmgr()->wordpress()->register_post_types();

		// register custom wishlist single and archive pages (for flushing rewrite rules)
		nmgr()->wordpress()->add_rewrite_rules();

		// Update version
		update_option( nmgr()->prefix . '_version', nmgr()->version );

		// flush rewrite rules
		flush_rewrite_rules();

		// Runs on register activation hook (on activation) or init (on update)
		do_action( nmgr()->prefix . '_installed' );
	}

	public function run() {
		add_filter( 'plugin_action_links_' . nmgr()->basename, array( $this, 'plugin_action_links' ) );

		Wizard::run();
		NMGR_Scripts::run();
		nmgr()->ajax()->run();
		nmgr()->admin_post()->run();
		NMGR_Admin::run();
		nmgr()->templates()->run();
		NMGR_Form::run();
		nmgr()->order()->run();
		nmgr()->wordpress()->run();
		nmgr()->gift_registry_settings()->run();
		nmgr()->wishlist_settings()->run();
		nmgr()->add_to_wishlist()->run();

		if ( nmgr()->is_licensed && class_exists( \NMGR\Sub\Update::class ) ) {
			(new \NMGR\Sub\Update( $this->plugin_props ) )->run();
		}

		new NMGR_Widget_Cart();
		new NMGR_Widget_Search();
		(new SetExpiredWishlists )->init();
		(new SetWishlistTerms )->init();
		(new DeleteExtraUserWishlists( 'gift-registry' ) )->init();
		(new DeleteExtraUserWishlists( 'wishlist' ) )->init();
		(new UpdateOrderItemMeta )->init();

		$this->run_emails();

		do_action( nmgr()->prefix . '_plugin_loaded' );
	}

	protected function run_emails() {
		if ( class_exists( \NMGR\Sub\Email::class ) ) {

			$trigger_actions = \NMGR\Sub\Email::trigger_actions();

			add_filter( 'woocommerce_email_classes', function ( $emails ) use ( $trigger_actions ) {
				$email_ids = array_keys( $trigger_actions );
				foreach ( $email_ids as $email_id ) {
					$name = str_replace( 'email', 'nmgr', $email_id );
					$emails[ $name ] = new \NMGR\Sub\Email( $email_id );
				}
				return $emails;
			} );

			add_filter( 'woocommerce_email_actions', function ( $actions ) use ( $trigger_actions ) {
				$email_actions = array_unique( array_merge( ...array_values( $trigger_actions ) ) );
				return array_merge( $actions, $email_actions );
			} );

			// Prevent the default woocommerce email settings for the plugin emails from being saved on the wc-settings page
			add_action( 'woocommerce_email_settings_before', function () {
				if ( false !== strpos( ($_GET[ 'section' ] ?? '' ), 'nmgr_' ) ) {
					$url = admin_url( 'edit.php?post_type=nm_gift_registry&page=nmgr-settings&tab=emails' );
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
					?>
					<style>
						table.form-table,
						.woocommerce-save-button {
							display: none !important;
						}
					</style>
					<?php

				}
			} );
		}
	}

	/**
	 * Set wishlist types
	 * Added in version 4.0.0
	 */
	public function insert_terms() {
		$terms = [ 'wishlist', 'gift registry' ];
		foreach ( $terms as $term ) {
			if ( empty( term_exists( $term, 'nm_gift_registry_type' ) ) ) {
				wp_insert_term( $term, 'nm_gift_registry_type' );
			}
		}
	}

	public function add_default_settings() {
		$wishlist_settings = nmgr()->wishlist_settings();
		$gift_registry_settings = nmgr()->gift_registry_settings();
		$existing_settings = get_option( 'nmgr_settings', [] );
		$db_version = get_option( nmgr()->prefix . '_version' );

		if ( !$existing_settings || !$db_version ) {
			$default_settings = array_merge(
				$wishlist_settings->get_default_field_values(),
				$gift_registry_settings->get_default_field_values()
			);

			if ( !$existing_settings ) {
				add_option( 'nmgr_settings', $default_settings );
			} else {
				/**
				 * @var $db_version
				 * If we have existing settings but we don't have the plugin version registered in the database
				 * it means we are moving from lite to pro version or vice versa, so we update plugin settings afresh.
				 */
				update_option( 'nmgr_settings', array_merge( $default_settings, $existing_settings ) );
			}
		}

		if ( nmgr()->is_pro ) {
			add_option( 'nmgr_exclude_from_search', array() );
		}

		if ( !nmgr()->is_pro ) {
			$default_pro_fields_values = array_merge(
				$wishlist_settings->get_default_pro_fields_values(),
				$gift_registry_settings->get_default_pro_fields_values()
			);
			add_option( 'nmgr_default_pro_fields_values', $default_pro_fields_values );
		}

		nmgr_update_pagename( $existing_settings[ 'page_id' ] ?? 0, 'gift-registry' );
		nmgr_update_pagename( $existing_settings[ 'wishlist_page_id' ] ?? 0, 'wishlist' );
	}

	public function create_tables() {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
		  CREATE TABLE {$wpdb->prefix}nmgr_wishlist_items (
				wishlist_item_id BIGINT UNSIGNED NOT NULL auto_increment,
				wishlist_id BIGINT UNSIGNED NOT NULL,
				product_or_variation_id BIGINT UNSIGNED NULL DEFAULT 0,
				product_id BIGINT UNSIGNED NOT NULL,
				variation_id BIGINT UNSIGNED NULL DEFAULT 0,
				quantity INT(10) NULL DEFAULT 0,
				purchased_quantity INT(10) NULL DEFAULT 0,
				favourite TINYINT(1) NULL DEFAULT 0,
				archived TINYINT(1) NULL DEFAULT 0,
				variation LONGTEXT NULL DEFAULT '',
				unique_id VARCHAR(255) NULL DEFAULT '',
				quantity_reference LONGTEXT NULL DEFAULT '',
				purchase_log LONGTEXT NULL DEFAULT '',
				date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (wishlist_item_id),
				KEY wishlist_id (wishlist_id),
				KEY product_or_variation_id (product_or_variation_id),
				KEY product_id (product_id),
				KEY variation_id (variation_id)
			 ) $collate;
		  ";

		// update schema with dbdelta
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $tables );
	}

	/**
	 * Get the roles that have been assigned a specific plugin capability
	 *
	 * Default roles are admiinstrator and shop_manager
	 *
	 * @param string $capability_type The plugin capability
	 * @return array The roles that have this capability
	 */
	public static function get_roles( $capability_type ) {
		return apply_filters( "nmgr_{$capability_type}_roles", array(
			'administrator',
			'shop_manager'
			) );
	}

	public static function get_capabilities() {
		$capabilities = array();
		$post_type = 'nm_gift_registry';
		$post_type_plural = 'nm_gift_registries';

		// Permission for managing plugin settings
		$capabilities[ 'manage_settings' ] = array(
			"manage_{$post_type}_settings"
		);

		// Permission for managing gift registry post type CRUD operations
		$capabilities[ "manage_CRUD" ] = array(
			"edit_{$post_type}",
			"read_{$post_type}",
			"delete_{$post_type}",
			"edit_{$post_type_plural}",
			"edit_others_{$post_type_plural}",
			"publish_{$post_type_plural}",
			"read_private_{$post_type_plural}",
			"delete_{$post_type_plural}",
			"delete_private_{$post_type_plural}",
			"delete_published_{$post_type_plural}",
			"delete_others_{$post_type_plural}",
			"edit_private_{$post_type_plural}",
			"edit_published_{$post_type_plural}",
		);

		return $capabilities;
	}

	public static function add_capabilities() {
		global $wp_roles;

		if ( !class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( !isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore
		}

		$capabilities = static::get_capabilities();

		foreach ( $capabilities as $type => $permissions ) {
			$roles = static::get_roles( $type );

			if ( !empty( $roles ) ) {
				foreach ( $permissions as $permission ) {
					foreach ( $roles as $role ) {
						$wp_roles->add_cap( $role, $permission );
					}
				}
			}
		}
	}

	public static function remove_capabilities() {
		global $wp_roles;

		if ( !class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( !isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore
		}

		$capabilities = static::get_capabilities();

		foreach ( $capabilities as $type => $permissions ) {
			$roles = static::get_roles( $type );

			if ( !empty( $roles ) ) {
				foreach ( $permissions as $permission ) {
					foreach ( $roles as $role ) {
						$wp_roles->remove_cap( $role, $permission );
					}
				}
			}
		}
	}

	public function plugin_action_links( $links ) {
		$gr = nmgr()->gift_registry_settings();
		$wl = nmgr()->wishlist_settings();

		return array_merge( $links, array(
			'<a href="' . $gr->get_page_url() . '">' . $gr->get_heading() . '</a>',
			'<a href="' . $wl->get_page_url() . '">' . $wl->get_heading() . '</a>',
			) );
	}

	public function maybe_disable_crowdfunding_plugin() {
		if ( $this->plugin_props->is_pro && function_exists( 'nmgrcf' ) &&
			property_exists( $this->plugin_props, 'requires_nmgrcf' ) &&
			version_compare( nmgrcf()->version, $this->plugin_props->requires_nmgrcf, '<' ) &&
			property_exists( nmgrcf(), 'is_disabled' ) ) {
			nmgrcf()->notices[ 'disable' ][] = sprintf(
				/* translators: 1,2: plugin name, 3: required version */
				'%1$s needs version %2$s or higher to work with %3$s. Please update %1$s.',
				nmgrcf()->name,
				$this->plugin_props->requires_nmgrcf,
				$this->plugin_props->name
			);

			nmgrcf()->is_disabled = true;
		}
	}

	public function maybe_disable() {
		if ( !class_exists( 'Woocommerce' ) ) {
			$this->plugin_props->notices[ 'disable' ][] = sprintf(
				/* translators: %s: plugin name */
				$this->plugin_props->is_pro ? __( 'You need the WooCommerce plugin to be installed and activated for %s to work.', 'nm-gift-registry' ) : __( 'You need the WooCommerce plugin to be installed and activated for %s to work.', 'nm-gift-registry-lite' ),
				$this->plugin_props->name
			);
		} elseif ( version_compare( WC_VERSION, $this->plugin_props->requires_wc, '<' ) ) {
			$this->plugin_props->notices[ 'disable' ][] = sprintf(
				/* translators: %1$s: plugin name, %2$s: required woocommerce version */
				$this->plugin_props->is_pro ? __( '%1$s needs WooCommerce %2$s or higher to work. Please update WooCommerce.', 'nm-gift-registry' ) : __( '%1$s needs WooCommerce %2$s or higher to work. Please update WooCommerce.', 'nm-gift-registry-lite' ),
				$this->plugin_props->name,
				$this->plugin_props->requires_wc
			);
		}

		if ( !empty( $this->plugin_props->notices[ 'disable' ] ) ) {
			$this->plugin_props->is_disabled = true;
		}

		return $this->plugin_props->is_disabled;
	}

	public function show_disabled_notices( $actions, $plugin_file ) {
		if ( $this->plugin_props->basename === $plugin_file && !empty( $this->plugin_props->notices[ 'disable' ] ) ) {
			$notices = htmlspecialchars( json_encode( implode( "\n\n", $this->plugin_props->notices[ 'disable' ] ) ) );

			$actions[ 'disabled' ] = '<strong style="color:red;display:inline;cursor:pointer;" onclick="alert(' . $notices . ');">' .
				($this->plugin_props->is_pro ?
				__( 'Disabled', 'nm-gift-registry' ) :
				__( 'Disabled', 'nm-gift-registry-lite' )) .
				' &#9432;</strong>';
		}
		return $actions;
	}

	public static function uninstall_pro() {
		global $wpdb;

		if ( !apply_filters( 'nmgr_delete_data_on_uninstall', false ) ) {
			return;
		}

		// delete images
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nm_gift_registry'" );
		foreach ( $post_ids as $post_id ) {
			$attachment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment'", $post_id ) );
			if ( !empty( $attachment_ids ) ) {
				foreach ( $attachment_ids as $id ) {
					wp_delete_attachment( $id, true );
				}
			}
		}

		// delete comments and commentmeta
		$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_type IN ( 'nmgiftregistry_msg' );" );
		$wpdb->query( "DELETE meta FROM {$wpdb->commentmeta} meta LEFT JOIN {$wpdb->comments} comments ON comments.comment_ID = meta.comment_id WHERE comments.comment_ID IS NULL;" );

		if ( !get_option( 'nmgrlite_version' ) ) {
			static::uninstall_actions();
		}

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name = 'nmgr_version';" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name = 'nmgr_exclude_from_search';" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_nmgr\_%';" );

		wp_cache_flush();
	}

	public static function uninstall_lite() {
		global $wpdb;

		if ( !apply_filters( 'nmgr_delete_data_on_uninstall', false ) ) {
			return;
		}

		if ( !get_option( 'nmgr_version' ) ) {
			static::uninstall_actions();
		}

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'nmgrlite\_%';" );

		wp_cache_flush();
	}

	private static function uninstall_actions() {
		global $wpdb;

		// remove capabilities
		static::remove_capabilities();

		// delete tables
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nmgr_wishlist_items" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nmgr_wishlist_itemmeta" );

		// delete posts and postmeta
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'nm_gift_registry');" );
		$wpdb->query( "DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.ID IS NULL;" );

		// delete user meta
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'nmgr\_%';" );

		// delete options
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%%nmgr\_%%';" );

		// delete taxonomy
		$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => 'nm_gift_registry_type' ) );

		// Delete orphan relationships.
		$wpdb->query( "DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} posts ON posts.ID = tr.object_id WHERE posts.ID IS NULL;" );

		// Delete orphan terms.
		$wpdb->query( "DELETE t FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.term_id IS NULL;" );
	}

}
