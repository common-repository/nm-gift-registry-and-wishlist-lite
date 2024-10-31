<?php

namespace NMGR\Setup;

use NMGR\Events\SetWishlistTerms,
		NMGR\Events\UpdateOrderItemMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Upgrader class
 * works for both lite and pro versions
 */
class Upgrader {

	public static $old_version;

	public static function run() {
		self::$old_version = get_option( nmgr()->prefix . '_version' );
		/**
		 * Run upgrades on 'init' instead of 'admin_init' so that they can also run on frontend
		 * Hook to priority 70 to run after events have been registed in \NMGR_Events::run()
		 * And priority 70 is standard plugin init priority.
		 */
		add_action( 'init', [ __CLASS__, 'init' ], 70 );
	}

	public static function init() {
		$methods = get_class_methods( __CLASS__ );
		foreach ( $methods as $method ) {
			if ( 0 === strpos( $method, '_' ) ) {
				$version = ltrim( str_replace( '_', '.', $method ), '.' );
				// Run only upgrade methods that are greater that the last saved plugin version in db
				if ( version_compare( self::$old_version, $version, '<' ) ) {
					self::$method();
				}
			}
		}
	}

	public static function _4_0_0() {
		(new SetWishlistTerms )->run();
		delete_metadata( 'user', 0, 'nmgr_enable_wishlist', '', true );

		$existing_settings = get_option( 'nmgr_settings' );
		if ( $existing_settings ) {
			if ( array_key_exists( 'add_to_wishlist_single', $existing_settings ) &&
				!$existing_settings[ 'add_to_wishlist_single' ] ) {
				$existing_settings[ 'add_to_wishlist_button_position_single' ] = '';
				$update = true;
			}

			if ( array_key_exists( 'add_to_wishlist_archive', $existing_settings ) &&
				!$existing_settings[ 'add_to_wishlist_archive' ] ) {
				$existing_settings[ 'add_to_wishlist_button_position_archive' ] = '';
				$update = true;
			}

			if ( isset( $update ) && $update ) {
				update_option( 'nmgr_settings', $existing_settings );
			}
		}
	}

	public static function _4_2_0() {
		wp_clear_scheduled_hook( 'nmgr_delete_guest_wishlists' );
		wp_clear_scheduled_hook( 'nmgr_set_expired_wishlists' );
		wp_clear_scheduled_hook( 'nmgr_gift-registry_SetExpiredWishlists' );
	}

	public static function _4_3_0() {
		(new UpdateOrderItemMeta )->run();
	}

	/**
	 * Version 4.4.0
	 */
	public static function _4_4_0() {
		global $wpdb;

		delete_option( 'nmgr_show_current_version_notices' );

		/**
		 * Add _nmgr_fulfilled wishlist meta_key to replace _date_fulfilled
		 */
		$wpdb->query( "
		INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value)
			SELECT pm.post_id, '_nmgr_fulfilled', '1' FROM $wpdb->postmeta AS pm
			INNER JOIN $wpdb->posts AS pp	ON pm.post_id = pp.ID
			WHERE pp.post_type = 'nm_gift_registry'
			AND pm.meta_key = '_date_fulfilled'
			AND pm.meta_value != ''
			AND NOT EXISTS (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_nmgr_fulfilled' AND post_id = pm.post_id)
		" );

		/**
		 * Change '_nmgr_wishlist_id' order meta key to 'nmgr_wishlist_id'
		 */
		$wpdb->query( "
		UPDATE {$wpdb->prefix}woocommerce_order_itemmeta
			SET meta_key = 'nmgr_wishlist_id'
			WHERE meta_key = '_nmgr_wishlist_id'
		" );

		/**
		 * Change '_nmgr_item_id' order meta key to 'nmgr_item_id'
		 */
		$wpdb->query( "
		UPDATE {$wpdb->prefix}woocommerce_order_itemmeta
			SET meta_key = 'nmgr_item_id'
			WHERE meta_key = '_nmgr_item_id'
		" );

		$items_table = "{$wpdb->prefix}nmgr_wishlist_items";
		$itemmeta_table = "{$wpdb->prefix}nmgr_wishlist_itemmeta";

		if ( !$wpdb->get_var( "SHOW TABLES LIKE '$items_table'" ) ||
			!$wpdb->get_var( "SHOW TABLES LIKE '$itemmeta_table'" ) ) {
			return;
		}

		$update_fields = [
			'quantity_reference',
			'unique_id',
			'purchased_quantity',
			'quantity',
			'variation',
			'variation_id',
			'archived',
			'favourite',
			'purchase_log',
		];

		// Move fields from itemmeta table to items table
		foreach ( $update_fields as $new_key ) {
			$old_key = '_' . $new_key;

			if ( $wpdb->query( "SHOW columns from $items_table LIKE '$new_key'" ) ) {
				$wpdb->query( "UPDATE $items_table AS a INNER JOIN $itemmeta_table AS b ON a.wishlist_item_id = b.wishlist_item_id SET a.$new_key = b.meta_value WHERE b.meta_key = '$old_key'" );

				$wpdb->query( "DELETE FROM $itemmeta_table WHERE meta_key = '$old_key'" );
			}
		}

		$wpdb->query( "DELETE FROM $itemmeta_table WHERE meta_key = '_product_id'" );

		// wishlist_id
		$wpdb->query( "ALTER TABLE $items_table CHANGE `wishlist_id` `wishlist_id` BIGINT UNSIGNED NOT NULL AFTER `wishlist_item_id`" );

		// product_or_variation_id
		if ( $wpdb->query( "SHOW columns from $items_table LIKE 'product_or_variation_id'" ) ) {
			$wpdb->query( "ALTER TABLE $items_table CHANGE `product_or_variation_id` `product_or_variation_id` BIGINT UNSIGNED NULL DEFAULT 0 AFTER `wishlist_id`" );

			$wpdb->query( "
			UPDATE $items_table
				SET product_or_variation_id = (CASE
					WHEN variation_id = 0 THEN product_id
					ELSE variation_id
					END)
			" );
		}

		// purchase_log
		if ( $wpdb->query( "SHOW columns from $items_table LIKE 'purchase_log'" ) ) {
			$wpdb->query( "ALTER TABLE $items_table CHANGE `date_created` `date_created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `purchase_log`" );
		}
	}

	public static function _4_5_0() {
		wp_clear_scheduled_hook( 'nmgr_gift-registry_DeleteGuestWishlists' );
		wp_clear_scheduled_hook( 'nmgr_wishlist_DeleteGuestWishlists' );
	}

	public static function _5_0_0() {
		global $wpdb;

		delete_option( 'nmgr_dismiss_removed_templates_notice' );

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}nmgr_wishlist_itemmeta'" ) &&
			!$wpdb->get_var( "SELECT EXISTS (SELECT 1 FROM {$wpdb->prefix}nmgr_wishlist_itemmeta)" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}nmgr_wishlist_itemmeta" );
		}

		if ( $wpdb->query( "SHOW columns from {$wpdb->prefix}nmgr_wishlist_items LIKE 'date_modified'" ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}nmgr_wishlist_items DROP date_modified" );
		}
	}

	public static function _5_1_0() {
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'nmgr_wishlist' );
			wp_cache_flush_group( 'nmgr_item' );
		}
	}

	public static function _5_4_1() {
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'nmgr_wishlist' );
			wp_cache_flush_group( 'nmgr_item' );
		}
	}

}
