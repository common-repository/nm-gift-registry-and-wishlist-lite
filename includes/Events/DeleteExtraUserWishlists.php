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
class DeleteExtraUserWishlists extends Scheduler {

	protected $category;

	public function __construct( $category ) {
		$this->category = $category;
		$this->prefix = 'nmgr_' . $this->category;
		parent::__construct();
	}

	protected function task( $post_ids ) {
		// Delete only if the user has more than one wishlist
		if ( is_array( $post_ids ) && count( $post_ids ) > 1 ) {
			// Capture the wishlist id to be set as the only one for this user
			$main_post_id = null;

			// Loop through the user's wishlist ids
			foreach ( $post_ids as $post_id ) {
				// If the wishlist is not active, ignore it
				if ( !in_array( get_post_status( $post_id ), nmgr_get_post_statuses() ) ) {
					continue;
				}

				// The wishlist is active, set it as the user's only wishlist if not set
				if ( is_null( $main_post_id ) ) {
					$main_post_id = $post_id;
					continue;
				}

				// Trash the wishlist after we have set the only wishlist for the user
				wp_trash_post( $post_id );
			}
		}
		return false;
	}

	protected function get_batch_data() {
		global $wpdb;

		$meta_rows = $wpdb->get_results( $wpdb->prepare(
				"
		SELECT post_id, meta_value FROM {$wpdb->postmeta} postmeta
		INNER JOIN {$wpdb->posts} posts
		ON postmeta.post_id = posts.ID
		INNER JOIN {$wpdb->term_relationships} term_relationships
		ON posts.ID = term_relationships.object_id
		INNER JOIN {$wpdb->term_taxonomy} term_taxonomy
		ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
		INNER JOIN {$wpdb->terms} terms
		ON terms.term_id = term_taxonomy.term_taxonomy_id
		WHERE postmeta.meta_key = '_nmgr_user_id'
		AND posts.post_status IN ('" . implode( "','", array_map( 'esc_sql', nmgr_get_post_statuses() ) ) . "')
		AND term_taxonomy.taxonomy = 'nm_gift_registry_type'
		AND terms.slug = %s
		ORDER BY meta_id DESC
		",
				$this->category
			) );

		// Get the ids of the users that have wishlists (both key and value are the user_id)
		$user_ids_to_post_ids = array_filter( wp_list_pluck( $meta_rows, 'meta_value', 'meta_value' ) );

		// Get all the wishlists for each user
		foreach ( array_keys( $user_ids_to_post_ids ) as $user_id ) {
			$post_ids = array();
			foreach ( $meta_rows as $row ) {
				$meta_value = is_numeric( $row->meta_value ) ? ( int ) $row->meta_value : $row->meta_value;
				if ( $user_id === $meta_value ) {
					// Add post ids as indexed array
					$post_ids[] = $row->post_id;
				}
			}
			$user_ids_to_post_ids[ $user_id ] = $post_ids;
		}

		return $user_ids_to_post_ids;
	}

}
