<?php

use NMGR\Lib\Single;

/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Wordpress {

	public static function run() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 70 );
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 70 );
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 70 );
		add_action( 'init', array( __CLASS__, 'add_image_sizes' ), 70 );
		add_action( 'init', array( __CLASS__, 'add_shortcodes' ), 70 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );

		add_action( 'before_delete_post', array( __CLASS__, 'setup_before_delete_post_action' ), -1 );
		add_action( 'delete_post', array( __CLASS__, 'setup_delete_action' ), -1 );
		add_action( 'trashed_post', array( __CLASS__, 'setup_trashed_action' ), -1 );
		add_action( 'untrashed_post', array( __CLASS__, 'setup_untrashed_action' ), -1 );

		add_action( 'nmgr_before_delete_wishlist', array( __CLASS__, 'before_delete_wishlist' ) );

		// Add to template_redirect hook so that it occurs only on frontend (non-ajax) requests
		add_action( 'wp', array( __CLASS__, 'show_notice' ) );
		add_filter( 'posts_search', array( __CLASS__, 'enhance_wishlist_search' ), 10, 2 );
		add_action( 'wp', array( __CLASS__, 'maybe_set_user_id_cookie' ) );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'login_redirect' ) );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'login_redirect' ) );
		add_action( 'nmgr_wishlist_item_deleted', array( __CLASS__, 'delete_item_from_cart' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'insert_post_data' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'set_single_wishlist_permalink' ), 10, 2 );
		add_filter( 'post_type_archive_link', array( __CLASS__, 'set_wishlist_archive_permalink' ), 10, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'set_single_wishlist_document_title' ) );
		add_filter( 'restrict_manage_posts', array( __CLASS__, 'enable_select_wishlist_type' ) );
		add_action( 'wp', array( __CLASS__, 'set_add_to_wishlist_button_position' ) );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 999 );
		add_action( 'save_post', array( __CLASS__, 'update_pagename' ), 10, 2 );
	}

	public static function maybe_flush_rewrite_rules() {
		if ( get_option( 'nmgr_flush_rewrite_rules' ) ) {
			update_option( 'nmgr_flush_rewrite_rules', '' );
			flush_rewrite_rules();
		}
	}

	public static function update_pagename( $post_id, $post ) {
		if ( 'page' === $post->post_type ) {
			if ( ( int ) nmgr_get_option( 'page_id' ) === ( int ) $post_id &&
				nmgr_update_pagename( $post, 'gift-registry' ) ) {
				nmgr()->flush_rewrite_rules();
			} elseif ( ( int ) nmgr_get_option( 'wishlist_page_id' ) === ( int ) $post_id &&
				nmgr_update_pagename( $post, 'wishlist' ) ) {
				nmgr()->flush_rewrite_rules();
			}
		}
	}

	public static function enable_select_wishlist_type() {
		global $wp_query;

		if ( is_nmgr_admin() ) {
			$args = array(
				'show_count' => 1,
				'show_uncategorized' => 1,
				'orderby' => 'name',
				'selected' => isset( $wp_query->query_vars[ 'nm_gift_registry_type' ] ) ?
				$wp_query->query_vars[ 'nm_gift_registry_type' ] : '',
				'show_option_none' => nmgr()->is_pro ?
				__( 'Select wishlist type', 'nm-gift-registry' ) :
				__( 'Select wishlist type', 'nm-gift-registry-lite' ),
				'option_none_value' => '',
				'value_field' => 'slug',
				'taxonomy' => 'nm_gift_registry_type',
				'name' => 'nm_gift_registry_type',
				'class' => 'select_nm_gift_registry_type',
			);

			wp_dropdown_categories( $args );
		}
	}

	public static function register_post_types() {
		if ( post_type_exists( 'nm_gift_registry' ) ) {
			return;
		}

		$args = array(
			'labels' => array(
				'name' => nmgr()->is_pro ?
				__( 'Wishlists', 'nm-gift-registry' ) :
				__( 'Wishlists', 'nm-gift-registry-lite' ),
				'singular_name' => nmgr()->is_pro ?
				__( 'Wishlist', 'nm-gift-registry' ) :
				__( 'Wishlist', 'nm-gift-registry-lite' ),
				'all_items' => nmgr()->is_pro ?
				__( 'All Wishlists', 'nm-gift-registry' ) :
				__( 'All Wishlists', 'nm-gift-registry-lite' ),
				'menu_name' => nmgr()->is_pro ?
				__( 'NM Gift Registry', 'nm-gift-registry' ) :
				__( 'NM Gift Registry', 'nm-gift-registry-lite' ),
				'add_new_item' => nmgr()->is_pro ?
				__( 'Add new wishlist', 'nm-gift-registry' ) :
				__( 'Add new wishlist', 'nm-gift-registry-lite' ),
				'add_new' => nmgr()->is_pro ?
				__( 'Add new wishlist', 'nm-gift-registry' ) :
				__( 'Add new wishlist', 'nm-gift-registry-lite' ),
				'edit' => nmgr()->is_pro ?
				__( 'Edit', 'nm-gift-registry' ) :
				__( 'Edit', 'nm-gift-registry-lite' ),
				'edit_item' => nmgr()->is_pro ?
				__( 'Edit wishlist', 'nm-gift-registry' ) :
				__( 'Edit wishlist', 'nm-gift-registry-lite' ),
				'new_item' => nmgr()->is_pro ?
				__( 'New wishlist', 'nm-gift-registry' ) :
				__( 'New wishlist', 'nm-gift-registry-lite' ),
				'view_item' => nmgr()->is_pro ?
				__( 'View wishlist', 'nm-gift-registry' ) :
				__( 'View wishlist', 'nm-gift-registry-lite' ),
				'view_items' => nmgr()->is_pro ?
				__( 'View wishlists', 'nm-gift-registry' ) :
				__( 'View wishlists', 'nm-gift-registry-lite' ),
				'search_items' => nmgr()->is_pro ?
				__( 'Search wishlists', 'nm-gift-registry' ) :
				__( 'Search wishlists', 'nm-gift-registry-lite' ),
				'not_found' => nmgr()->is_pro ?
				__( 'No wishlists found', 'nm-gift-registry' ) :
				__( 'No wishlists found', 'nm-gift-registry-lite' ),
				'not_found_in_trash' => nmgr()->is_pro ?
				__( 'No wishlists found in trash', 'nm-gift-registry' ) :
				__( 'No wishlists found in trash', 'nm-gift-registry-lite' ),
				'filter_items_list' => nmgr()->is_pro ?
				__( 'Filter wishlists', 'nm-gift-registry' ) :
				__( 'Filter wishlists', 'nm-gift-registry-lite' ),
				'items_list' => nmgr()->is_pro ?
				__( 'Wishlists list', 'nm-gift-registry' ) :
				__( 'Wishlists list', 'nm-gift-registry-lite' ),
				'item_published' => nmgr()->is_pro ?
				__( 'Wishlist published', 'nm-gift-registry' ) :
				__( 'Wishlist published', 'nm-gift-registry-lite' ),
				'item_published_privately' => nmgr()->is_pro ?
				__( 'Wishlist published privately', 'nm-gift-registry' ) :
				__( 'Wishlist published privately', 'nm-gift-registry-lite' ),
				'item_updated' => nmgr()->is_pro ?
				__( 'Wishlist updated', 'nm-gift-registry' ) :
				__( 'Wishlist updated', 'nm-gift-registry-lite' ),
				'attributes' => nmgr()->is_pro ?
				__( 'Wishlist Attributes', 'nm-gift-registry' ) :
				__( 'Wishlist Attributes', 'nm-gift-registry-lite' ),
			),
			'description' => nmgr()->is_pro ?
			__( 'Add gift registries and wishlists to your store.', 'nm-gift-registry' ) :
			__( 'Add gift registries and wishlists to your store.', 'nm-gift-registry-lite' ),
			'public' => true,
			'show_ui' => true,
			'publicly_queryable' => true,
			'exclude_from_search' => ( bool ) nmgr_get_option( 'exclude_from_search' ),
			'show_in_menu' => true,
			'map_meta_cap' => true,
			'hierarchical' => false,
			'show_in_nav_menus' => false,
			'query_var' => true,
			'supports' => array( 'title' ),
			'capability_type' => array( 'nm_gift_registry', 'nm_gift_registries' ),
			'has_archive' => true,
			'menu_icon' => 'dashicons-heart',
			'taxonomies' => [ 'nm_gift_registry_type' ],
		);

		register_post_type( 'nm_gift_registry', apply_filters( 'nmgr_register_post_type', $args ) );
	}

	/**
	 * Add rewrite rules
	 */
	public static function add_rewrite_rules() {
		global $wp_rewrite;

		$query_wishlist = 'nmgr_w';
		$query_action = 'nmgr_action';

		add_rewrite_tag( '%' . $query_wishlist . '%', '([^/]*' );
		add_rewrite_tag( '%' . $query_action . '%', '([^/]*' );

		$page_arrays = array_unique( array_filter( [
			get_option( 'nmgr_pagename' ),
			get_option( 'nmgr_wishlist_pagename' ),
			] ) );

		if ( !empty( $page_arrays ) ) {
			foreach ( $page_arrays as $pagename ) {
				foreach ( nmgr_get_base_actions() as $action ) {
					add_rewrite_rule( "{$pagename}/({$action})/?$",
						"index.php?pagename={$pagename}&" . $query_wishlist . '=$matches[1]',
						'top'
					);
				}

				add_rewrite_rule( "{$pagename}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$", "index.php?pagename=$pagename" . '&paged=$matches[1]', 'top' );

				add_rewrite_rule( "{$pagename}/([^/]*)/?([^/]*)/?", 'index.php?pagename=' . $pagename . '&' . $query_wishlist . '=$matches[1]&' . $query_action . '=$matches[2]', 'top' );
			}
		}
	}

	/**
	 * Add nmgr images sizes to wordpress
	 *
	 * nmgr_medium - size for wishlist featured image, used on account page and single wishlist page
	 * @since 1.1.5 nmgr_thumbnail - used in items table, wishlist cart and add to wishlist template
	 */
	public static function add_image_sizes() {
		add_image_size( 'nmgr_medium', nmgr()->post_thumbnail_size(), nmgr()->post_thumbnail_size(), true );
		add_image_size( 'nmgr_thumbnail', apply_filters( 'nmgr_thumbnail_size', 90 ) );
	}

	public static function add_shortcodes() {
		$shortcodes = array(
			'nmgr_search_form' => [ \NMGR\Lib\Archive::class, 'get_search_form' ],
			'nmgr_search_results' => [ \NMGR\Lib\Archive::class, 'get_search_results_template' ],
			'nmgr_search' => [ \NMGR\Lib\Archive::class, 'get_search_template' ],
			'nmgr_add_to_wishlist' => [ nmgr()->add_to_wishlist(), 'get_button_template' ],
			'nmgr_wishlist' => [ \NMGR\Lib\Single::class, 'get_template' ],
			'nmgr_archive' => [ \NMGR\Lib\Archive::class, 'get_template' ],
			'nmgr_cart' => [ \NMGR_Widget_Cart::class, 'template' ],
		);

		foreach ( $shortcodes as $shortcode => $function ) {
			if ( is_callable( $function ) ) {
				add_shortcode( $shortcode, $function );
			}
		}
	}

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 *
	 * @param int $postid Post ID.
	 */
	public static function setup_before_delete_post_action( $post_id ) {
		if ( 'nm_gift_registry' === get_post_type( $post_id ) ) {
			do_action( 'nmgr_before_delete_wishlist', $post_id );
		}
	}

	/**
	 * Fires immediately before a post is deleted from the database.
	 *
	 * @param int $postid Post ID.
	 */
	public static function setup_delete_action( $post_id ) {
		if ( 'nm_gift_registry' === get_post_type( $post_id ) ) {
			do_action( 'nmgr_delete_wishlist', $post_id );
		}
	}

	/**
	 * Fires after a post is sent to the trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function setup_trashed_action( $post_id ) {
		if ( 'nm_gift_registry' === get_post_type( $post_id ) ) {
			do_action( 'nmgr_trashed_wishlist', $post_id );
		}
	}

	/**
	 * Fires after a post is restored from the trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function setup_untrashed_action( $post_id ) {
		if ( 'nm_gift_registry' === get_post_type( $post_id ) ) {
			do_action( 'nmgr_untrashed_wishlist', $post_id );
		}
	}

	/**
	 * Delete wishlist items and images before permanently deleting a wishlist
	 *
	 * @param init $wishlist_id Wishlist id
	 */
	public static function before_delete_wishlist( $wishlist_id ) {
		$wishlist = nmgr_get_wishlist( $wishlist_id );
		// Delete wishlist items
		$wishlist->delete_items();

		// Delete wishlist images
		if ( is_callable( [ $wishlist, 'delete_images' ] ) ) {
			$wishlist->delete_images();
		}
	}

	/**
	 * Show various notices related to adding a wishlist depending on the situation
	 */
	public static function show_notice() {
		if ( !isset( $_REQUEST[ 'nmgr-notice' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_REQUEST[ 'nmgr-notice' ] ) );
		$type = sanitize_text_field( $_REQUEST[ 'nmgr-type' ] ?? '' );

		$redirect = (is_nmgr_guest( 'gift-registry' ) || is_nmgr_guest( 'wishlist' )) ? false : true;

		switch ( $notice ) {
			case 'select-product':
				$product_type = isset( $_REQUEST[ 'nmgr-pt' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'nmgr-pt' ] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

				if ( 'variable' == $product_type ) {
					wc_add_notice(
						sprintf(
							/* translators: %s: wishlist type title */
							nmgr()->is_pro ? __( 'Select a variation of this product to add to your %s.', 'nm-gift-registry' ) : __( 'Select a variation of this product to add to your %s.', 'nm-gift-registry-lite' ),
							esc_html( nmgr_get_type_title( '', false, $type ) )
						),
						'notice'
					);
				} elseif ( 'grouped' == $product_type ) {
					wc_add_notice(
						sprintf(
							/* translators: %s: wishlist type title */
							nmgr()->is_pro ? __( 'Select option(s) of this product to add to your %s.', 'nm-gift-registry' ) : __( 'Select option(s) of this product to add to your %s.', 'nm-gift-registry-lite' ),
							esc_html( nmgr_get_type_title( '', false, $type ) )
						),
						'notice'
					);
				}
				break;

			case 'require-login':
				$redirect = false;
				wc_add_notice(
					sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'Login to add products to your %s.', 'nm-gift-registry' ) : __( 'Login to add products to your %s.', 'nm-gift-registry-lite' ),
						esc_html( nmgr_get_type_title( '', false, $type ) )
					),
					'notice'
				);
				break;

			case 'create-wishlist':
				wc_add_notice(
					sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'Create a %s to add products to it.', 'nm-gift-registry' ) : __( 'Create a %s to add products to it.', 'nm-gift-registry-lite' ),
						esc_html( nmgr_get_type_title( '', false, $type ) )
					),
					'notice'
				);
				break;

			case 'login-to-access':
				$redirect = false;
				$template = new Single();
				$template->set_type( $type );
				wc_add_notice( $template->get_registered_notice( $notice ), 'notice' );
				break;
		}

		if ( $redirect ) {
			$query_string = '';
			parse_str( wc_clean( wp_unslash( $_SERVER[ 'QUERY_STRING' ] ) ), $query_string ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$remove_keys = array_filter( array_keys( $query_string ), function ( $val ) {
				return 'nmgr-redirect' !== $val && false !== strpos( $val, 'nmgr-' );
			} );

			if ( $remove_keys ) {
				wp_redirect( remove_query_arg( $remove_keys ) );
				exit;
			}
		}
	}

	/**
	 * Improve the default wishlist post type search to also search in certain postmeta fields
	 */
	public static function enhance_wishlist_search( $where, $query ) {
		global $wpdb;

		if ( (is_admin() && !is_nmgr_admin()) ||
			'nm_gift_registry' !== $query->get( 'post_type' ) ||
			!is_nmgr_search() ||
			!\NMGR\Lib\Archive::get_search_query() ) {
			return $where;
		}

		// Include password protected wishlists in search results
		if ( nmgr()->is_pro && !is_user_logged_in() ) {
			$pattern = " AND ({$wpdb->prefix}posts.post_password = '')";
			$where = str_replace( $pattern, '', $where );
		}

		// Locations in postmeta table to search for search term
		$meta_keys_to_search = apply_filters( 'nmgr_meta_keys_to_search', array(
			'_last_name',
			'_first_name',
			'_partner_first_name',
			'_partner_last_name',
			'_email'
			) );

		$meta_args = array( 'relation' => 'OR' );

		foreach ( $meta_keys_to_search as $key ) {
			$meta_args[] = array(
				'key' => $key,
				'value' => sanitize_text_field( \NMGR\Lib\Archive::get_search_query() ),
				'compare' => 'like',
			);
		}

		// Use WP_Meta_Query to compose sql for future maintenance
		$meta_query = new WP_Meta_Query( $meta_args );
		$sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );

		$search_post_ids = array();
		$found_post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} " . $sql[ 'join' ] . $sql[ 'where' ] );

		if ( count( $found_post_ids ) > 0 ) {
			$search_post_ids = array_filter( array_unique( array_map( 'absint', $found_post_ids ) ) );
		}

		if ( count( $search_post_ids ) > 0 ) {
			$where = str_replace(
				'AND (((',
				"AND ( ({$wpdb->posts}.ID IN (" . implode( ',', $search_post_ids ) . ")) OR ((",
				$where
			);
		}

		return $where;
	}

	/**
	 * Redirect the user to various pages after login based on whether he has created a wishlist or not
	 */
	public static function login_redirect( $redirect ) {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( !empty( $_GET[ 'nmgr-redirect' ] ) ) {
			$redirect = sanitize_text_field( wp_unslash( $_GET[ 'nmgr-redirect' ] ) );
		}
		// phpcs:enable
		return $redirect;
	}

	/**
	 * Plugin query vars
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'nmgr_s';
		return $vars;
	}

	/**
	 * Set the add-to-wishlist button position on single and archive pages
	 *
	 * @since 2.0.0
	 */
	public static function set_add_to_wishlist_button_position() {
		$args = [
			'gift-registry' => function () {
				echo nmgr()->add_to_wishlist()->get_button_template( null );
			},
			'wishlist' => function () {
				echo nmgr()->add_to_wishlist()->get_button_template( [ 'type' => 'wishlist' ] );
			},
		];

		foreach ( $args as $type => $function ) {
			$archive_display_hook = nmgr_get_type_option( $type, 'add_to_wishlist_button_position_archive' );

			if ( 0 === strpos( $archive_display_hook, 'woocommerce_' ) ) {
				$priority = 'woocommerce_before_shop_loop_item' === $archive_display_hook ? 5 : 20;
				add_action( $archive_display_hook, $function, $priority );
			} elseif ( $archive_display_hook ) {
				// This default must be there so that the button would always be displayed on the archive page
				add_action( 'woocommerce_before_shop_loop_item_title', $function, 20 );
			}

			$single_display_priority = nmgr_get_type_option( $type, 'add_to_wishlist_button_position_single', 35 );

			if ( 0 === strpos( $single_display_priority, 'woocommerce_' ) ) {
				add_action( $single_display_priority, $function );
			} elseif ( 0 === strpos( $single_display_priority, 'thumbnail_' ) ) {
				add_action( 'woocommerce_product_thumbnails', $function );
			} elseif ( $single_display_priority ) {
				// This default must be there so that the button would always be displayed on the single page
				add_action( 'woocommerce_single_product_summary', $function, ( int ) $single_display_priority );
			}
		}
	}

	public static function maybe_set_user_id_cookie() {
		if ( (is_nmgr_guest( 'gift-registry' ) || is_nmgr_guest( 'wishlist' )) &&
			!isset( $_COOKIE[ 'nmgr_user_id' ] ) ) {
			/**
			 * 2147483647 is maximum expiry age possible (2038)
			 * This means that guest wishlists don't expire
			 */
			if ( !headers_sent() ) {
				// Generate a user id for guests
				require_once ABSPATH . 'wp-includes/class-phpass.php';
				$hasher = new PasswordHash( 8, false );
				$user_id = md5( $hasher->get_random_bytes( 32 ) );

				setcookie( 'nmgr_user_id', $user_id, 2147483647, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, false, false );
			}
		}
	}

	/**
	 * Delete a wishlist item from woocommerce cart if it is deleted from the wishlist
	 */
	public static function delete_item_from_cart( $item_id ) {
		if ( is_a( wc()->cart, 'WC_Cart' ) && !WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
				$nmgr_data = nmgr_get_cart_item_data( $cart_item, 'wishlist_item' );
				if ( $nmgr_data && (( int ) $item_id === ( int ) $nmgr_data[ 'wishlist_item_id' ] ) ) {
					wc()->cart->remove_cart_item( $key );
				}
			}
		}
	}

	/**
	 * @todo Move function to NMGR_Admin_Post as it only acts in admin area.
	 */
	public static function insert_post_data( $data, $postarr ) {
		global $post;

		if ( 'nm_gift_registry' !== $data[ 'post_type' ] ||
			in_array( $data[ 'post_status' ], [ 'trash', 'auto-draft' ] ) ||
			!is_nmgr_admin() ) {
			return $data;
		}

		$type = has_term( 'wishlist', 'nm_gift_registry_type', $post ) ? 'wishlist' : 'gift-registry';

		$is_admin = is_nmgr_admin();

		/**
		 * On the wishlist edit post in admin area we are expecting the '_nmgr_user_id request
		 * parameter which tells us who owns the wishlist. Set the wishlist post_author based on
		 * this parameter.
		 */
		if ( $is_admin && isset( $_REQUEST[ 'user_id' ] ) ) {
			if ( is_numeric( $_REQUEST[ 'user_id' ] ) ) {
				// If the user id belongs to a registered user, set it as the post author
				$data[ 'post_author' ] = ( int ) wp_unslash( $_REQUEST[ 'user_id' ] );
			} else {
				// For guests, set post author as 0.
				$data[ 'post_author' ] = 0;
			}
		} elseif ( $is_admin && !is_numeric( get_post_meta( $postarr[ 'ID' ], '_nmgr_user_id', true ) ) ) {
			/**
			 * When updating a post, make sure we don't set a post author for guest wishlists.
			 * In the admin area $postarr['ID'] is always set so we don't need to check if it is set since this code
			 * is already running in the admin area with is_nmgr_admin.
			 * (This particular code snippet is necessary for when the post is updated via 'quick edit' in the list table.
			 */
			$data[ 'post_author' ] = 0;
		}

		/**
		 * If users are allowed to have one wishlist (as in lite version) and this user already has,
		 * set the post status to auto-draft, and add error message.
		 *
		 * This code only runs in the admin area so $postarr['ID'] is always set which allows us to
		 * compare the current wishlist being saved with the user's default wishlist if any. It is not
		 * designed to run on the frontend as the frontend is already set up in a structured way to
		 * prevent users from saving multiple wishlists and that should be enough.
		 *
		 * This code also runs only for registered users. It does not prevent multiple wishlists from
		 * being created for guests in the admin as this is pointless since the guest cookies cannot be
		 * generated as there is a logged in user.
		 */
		$cond = $is_admin && $data[ 'post_author' ];

		if ( nmgr()->is_pro ) {
			$cond = $cond && !nmgr_get_type_option( $type, 'allow_multiple_wishlists' );
		}

		if ( $cond ) {
			$wishlist_id = nmgr_get_user_default_wishlist_id( $data[ 'post_author' ], $type );

			/**
			 * If the submitted user already has a wishlist and his wishlist is not the same as this wishlist being saved,
			 * do not publish this wishlist but leave it at it's previous post status.
			 * Using 'get_post_field' is a clever way to see the previous post status of the wishlist (not the one currently being
			 * set) and it allow us to keep the wishlist as an auto-draft (if it is a new wishlist), or trashed (if it is an already trashed
			 * wishlist). This allows for a smoother user experience in the admin area rather than explicitly setting the post_status
			 * as auto-draft or trashed.
			 */
			if ( $wishlist_id && isset( $postarr[ 'ID' ] ) && ( int ) $postarr[ 'ID' ] !== $wishlist_id ) {
				$wishlist_type_title = nmgr_get_type_title( '', false, $type );

				// inform the admin that the submitted user can only have one wishlist
				NMGR_Admin_Post::add_notice( sprintf(
						/* translators: %1$s: username, %2$s: %3$s: %4$s: wishlist type title */
						nmgr()->is_pro ? __( 'The user %1$s already has one %2$s. Users are allowed to have only one %3$s. This %4$s has not been updated.', 'nm-gift-registry' ) : __( 'The user %1$s already has one %2$s. Users are allowed to have only one %3$s. This %4$s has not been updated.', 'nm-gift-registry-lite' ),
						'<strong>' . esc_html( get_the_author_meta( 'user_login', $data[ 'post_author' ] ) ) . '</strong>',
						$wishlist_type_title,
						$wishlist_type_title,
						$wishlist_type_title
					), 'error' );

				$data[ 'post_status' ] = get_post_field( 'post_status', $postarr[ 'ID' ] );
				$data[ 'post_author' ] = get_post_field( 'post_author', $postarr[ 'ID' ] );
			}
		}

		return $data;
	}

	public static function set_single_wishlist_permalink( $link, $post ) {
		if ( 'nm_gift_registry' === $post->post_type ) {
			$type = nmgr()->wishlist()->get_type_from_db( $post->ID );
			$pagename = 'wishlist' === $type ? get_option( 'nmgr_wishlist_pagename' ) : get_option( 'nmgr_pagename' );

			if ( $pagename ) {
				remove_filter( 'post_type_link', array( __CLASS__, 'set_single_wishlist_permalink' ), 10, 2 );
				$link = home_url( $pagename . '/' . $post->post_name );
				add_filter( 'post_type_link', array( __CLASS__, 'set_single_wishlist_permalink' ), 10, 2 );
			}
		}
		return $link;
	}

	public static function set_wishlist_archive_permalink( $link, $post_type ) {
		if ( 'nm_gift_registry' === $post_type ) {
			$page_id = nmgr_get_option( 'page_id' );
			if ( $page_id ) {
				$archive_post = get_post( $page_id );
				if ( is_a( $archive_post, 'WP_Post' ) && 'trash' !== $archive_post->post_status ) {
					$link = get_the_permalink( $archive_post );
				}
			}
		}
		return $link;
	}

	public static function set_single_wishlist_document_title( $parts ) {
		if ( is_nmgr_wishlist() ) {
			$wishlist = nmgr_get_wishlist( nmgr_get_current_wishlist_id() );
			if ( $wishlist ) {
				$parts = array_merge( [ 'wishlist_title' => $wishlist->get_title() ], $parts );
			}
		}
		return $parts;
	}

	public static function register_taxonomy() {
		if ( taxonomy_exists( 'nm_gift_registry_type' ) ) {
			return;
		}

		register_taxonomy(
			'nm_gift_registry_type',
			array( 'nm_gift_registry' ),
			apply_filters( 'nmgr_register_taxonomy_args',
				array(
					'hierarchical' => true,
					'labels' => array(
						'name' => nmgr()->is_pro ?
							__( 'Wishlist type', 'nm-gift-registry' ) :
							__( 'Wishlist type', 'nm-gift-registry-lite' ),
						'singular_name' => nmgr()->is_pro ?
							__( 'Wishlist type', 'nm-gift-registry' ) :
							__( 'Wishlist type', 'nm-gift-registry-lite' ),
						'menu_name' => nmgr()->is_pro ?
							_x( 'Wishlist types', 'Admin menu name', 'nm-gift-registry' ) :
							_x( 'Wishlist types', 'Admin menu name', 'nm-gift-registry-lite' ),
						'search_items' => nmgr()->is_pro ?
							__( 'Search wishlist types', 'nm-gift-registry' ) :
							__( 'Search wishlist types', 'nm-gift-registry-lite' ),
						'all_items' => nmgr()->is_pro ?
							__( 'All wishlist types', 'nm-gift-registry' ) :
							__( 'All wishlist types', 'nm-gift-registry-lite' ),
						'parent_item' => nmgr()->is_pro ?
							__( 'Parent wishlist type', 'nm-gift-registry' ) :
							__( 'Parent wishlist type', 'nm-gift-registry-lite' ),
						'edit_item' => nmgr()->is_pro ?
							__( 'Edit wishlist type', 'nm-gift-registry' ) :
							__( 'Edit wishlist type', 'nm-gift-registry-lite' ),
						'update_item' => nmgr()->is_pro ?
							__( 'Update wishlist type', 'nm-gift-registry' ) :
							__( 'Update wishlist type', 'nm-gift-registry-lite' ),
						'add_new_item' => nmgr()->is_pro ?
							__( 'Add new wishlist type', 'nm-gift-registry' ) :
							__( 'Add new wishlist type', 'nm-gift-registry-lite' ),
						'new_item_name' => nmgr()->is_pro ?
							__( 'New wishlist type name', 'nm-gift-registry' ) :
							__( 'New wishlist type name', 'nm-gift-registry-lite' ),
						'not_found' => nmgr()->is_pro ?
							__( 'No wishlist types found', 'nm-gift-registry' ) :
							__( 'No wishlist types found', 'nm-gift-registry-lite' ),
					),
					'show_in_nav_menus' => false,
					'show_in_menu' => false,
					'show_admin_column' => true,
					'rewrite' => array(
						'with_front' => false,
					),
				)
			)
		);
	}

}
