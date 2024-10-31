<?php

/**
 * Sync
 */

namespace NMGR\Events;

use NMGR\Events\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * @access private
 */
class UpdateOrderItemMeta extends Scheduler {

	protected $prefix = 'nmgr';
	protected $cache_data = false;
	protected $batch_processing = true;

	protected function task( $row ) {
		global $wpdb;

		$n = maybe_unserialize( $row->meta_value );
		$d = [];
		if ( isset( $n[ 'wishlist_item_id' ] ) ) {
			$d[] = '(' . esc_sql( $row->order_item_id ) . ',"nmgr_item_id","' . esc_sql( $n[ 'wishlist_item_id' ] ) . '")';
		}

		if ( isset( $n[ 'wishlist_id' ] ) ) {
			$d[] = '(' . esc_sql( $row->order_item_id ) . ',"nmgr_wishlist_id","' . esc_sql( $n[ 'wishlist_id' ] ) . '")';
		}

		if ( !empty( $d ) ) {
			$result = $wpdb->query(
				"INSERT INTO {$this->table()} ( order_item_id, meta_key, meta_value )
							VALUES " . implode( ',', $d )
			);

			if ( $result ) {
				$wpdb->delete( $this->table(), [ 'meta_id' => $row->meta_id ], [ '%d' ] );
			}
		}
	}

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'woocommerce_order_itemmeta';
	}

	protected function get_batch_data() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE meta_key = 'nm_gift_registry' LIMIT 100" );
	}

	protected function complete() {
		delete_metadata( 'order_item', 0, 'nmgr_wishlist_title', '', true );
	}

}
