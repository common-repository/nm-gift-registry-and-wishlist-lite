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
class SetExpiredWishlists extends Scheduler {

	protected $prefix = 'nmgr';
	protected $recurrence = true;
	protected $category = 'gift-registry';

	public function __construct() {
		$this->file = nmgr()->file;
		$this->timestamp = time() + (3 * DAY_IN_SECONDS);
		parent::__construct();
	}

	protected function task( $id ) {
		update_post_meta( $id, '_nmgr_expired', 1 );
		return false;
	}

	protected function get_batch_data() {
		return $this->get_expired_ids();
	}

	/**
	 *
	 * @global type $wpdb
	 * @return array Database query result
	 */
	private function get_expired_ids() {
		$cache_key = 'nmgr_expired_ids_' . $this->category;
		$ids = wp_cache_get( $cache_key, 'nmgr' );

		if ( false === $ids ) {
			$args = [
				'posts_per_page' => -1,
				'post_type' => 'nm_gift_registry',
				'post_status' => nmgr_get_post_statuses(),
				'fields' => 'ids',
				'meta_query' => [
					[
						'key' => '_event_date',
						'value' => '',
						'compare' => '!=',
					],
					[
						'key' => '_event_date',
						'value' => date( 'Y-m-d' ),
						'compare' => '<',
						'relation' => 'AND',
					],
					[
						'key' => '_nmgr_expired',
						'value_num' => '1',
						'compare' => '!=',
						'relation' => 'AND',
					],
				],
				'tax_query' => [
					[
						'taxonomy' => 'nm_gift_registry_type',
						'field' => 'slug',
						'terms' => $this->category,
						'operator' => 'IN',
					]
				],
			];

			$ids = get_posts( $args );

			wp_cache_set( $cache_key, $ids, 'nmgr', WEEK_IN_SECONDS );
		}

		return $ids;
	}

}
