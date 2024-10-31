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
class SetWishlistTerms extends Scheduler {

	protected $prefix = 'nmgr';
	private $term_id = 0;

	public function __construct() {
		parent::__construct();
		add_action( 'admin_notices', [ $this, 'running_notice' ] );
	}

	protected function task( $post_id ) {
		if ( !$this->term_id ) {
			$this->term_id = nmgr_get_term_id_by_slug( 'gift-registry' );
		}

		if ( $this->term_id && !has_term( [ 'gift-registry', 'wishlist' ], 'nm_gift_registry_type', $post_id ) ) {
			wp_set_object_terms( $post_id, $this->term_id, 'nm_gift_registry_type' );
		}
	}

	protected function get_batch_data() {
		global $wpdb;

		$cache_key = 'nmgr_post_ids';
		$ids = wp_cache_get( $cache_key, 'nmgr' );

		if ( false === $ids ) {
			$ids = $wpdb->get_col( "SELECT ID from $wpdb->posts WHERE post_type='nm_gift_registry'" );
			wp_cache_set( $cache_key, $ids, 'nmgr', WEEK_IN_SECONDS );
		}

		return $ids;
	}

	public function running_notice() {
		if ( $this->is_task_running() ) {
			$message = '<strong>' . nmgr()->name . '</strong> &ndash; ' .
				(nmgr()->is_pro ?
				__( 'Currently updating wishlist types for all wishlists in the background.', 'nm-gift-registry' ) :
				__( 'Currently updating wishlist types for all wishlists in the background.', 'nm-gift-registry-lite' ));

			echo '<div class="notice-info notice is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		}
	}

}
