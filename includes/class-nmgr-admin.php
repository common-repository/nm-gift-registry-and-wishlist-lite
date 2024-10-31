<?php

/**
 * Sync
 */
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined( 'ABSPATH' ) || exit;

class NMGR_Admin {

	public static function run() {
		if ( !is_admin() ) {
			return;
		}

		add_action( "manage_nm_gift_registry_posts_custom_column", array( __CLASS__, 'get_column_contents' ), 999, 2 );
		add_filter( "manage_edit-nm_gift_registry_columns", array( __CLASS__, 'get_column_headers' ) );
		add_filter( "manage_edit-nm_gift_registry_sortable_columns", array( __CLASS__, 'get_column_info' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( "admin_print_styles", array( __CLASS__, 'woocommerce_styles' ) );
		add_filter( "admin_footer_text", array( __CLASS__, 'admin_footer_text' ), 10 );
		add_filter( "manage_edit-product_columns", array( __CLASS__, 'product_column_headers' ) );
		add_action( "manage_product_posts_custom_column", array( __CLASS__, 'product_column_contents' ) );
		add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'product_sortable_columns' ) );
		add_filter( 'request', array( __CLASS__, 'order_products_by_wishlist_count' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 100, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'output_notices' ) );
		add_action( 'init', array( __CLASS__, 'cot_compat' ), 70 );
	}

	public static function cot_compat() {
		if ( !function_exists( 'wc_get_page_screen_id' ) ) {
			return;
		}

		$order_screen_id = wc_get_page_screen_id( 'shop-order' );
		add_filter( "manage_edit-{$order_screen_id}_columns", [ __CLASS__, 'shop_order_column_headers' ] );
		add_action( "manage_{$order_screen_id}_posts_custom_column", [ __CLASS__, 'shop_order_column_contents' ], 10, 2 );
		add_filter( "manage_{$order_screen_id}_columns", [ __CLASS__, 'shop_order_column_headers' ] ); // cot
		add_filter( "manage_{$order_screen_id}_custom_column", [ __CLASS__, 'shop_order_column_contents' ], 10, 2 ); // cot
	}

	public static function output_notices() {
		if ( 'nmgr-setup' === ($_GET[ 'page' ] ?? '') ) {
			return;
		}

		$notices = [];

		$params = [
			'gift-registry' => [
				'url' => nmgr()->gift_registry_settings()->get_page_url(),
			],
			'wishlist' => [
				'url' => nmgr()->wishlist_settings()->get_page_url(),
			],
		];

		foreach ( $params as $type => $param ) {
			if ( nmgr_get_type_option( $type, 'enable' ) && !nmgr_get_type_option( $type, 'page_id' ) ) {
				$notices[] = [
					'message' => '<strong>' . nmgr()->name . '</strong> &ndash; ' . sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'You need to set a page for viewing %s.', 'nm-gift-registry' ) : __( 'You need to set a page for viewing %s.', 'nm-gift-registry-lite' ),
						nmgr_get_type_title( '', 1, $type )
					) . ' ' . nmgr_get_click_here_link( $param[ 'url' ] ),
				];
			}
		}

		foreach ( $notices as $notice ) {
			echo '<div class="notice-info notice is-dismissible"><p>' . wp_kses_post( $notice[ 'message' ] ) . '</p></div>';
		}
	}

	public static function get_column_headers( $columns ) {
		$nmgr = array();

		if ( nmgr_get_option( 'enable' ) ) {
			if ( 'no' !== nmgr_get_option( 'display_form_first_name' ) ||
				'no' !== nmgr_get_option( 'display_form_last_name' ) ||
				'no' !== nmgr_get_option( 'display_form_partner_first_name' ) ||
				'no' !== nmgr_get_option( 'display_form_partner_last_name' ) ) {
				$nmgr[ 'nmgr_display_name' ] = nmgr()->is_pro ?
					__( 'Display name', 'nm-gift-registry' ) :
					__( 'Display name', 'nm-gift-registry-lite' );
			}
		}

		if ( nmgr_get_option( 'enable' ) && 'no' !== nmgr_get_option( 'display_form_email' ) ) {
			$nmgr[ 'nmgr_email' ] = nmgr()->is_pro ?
				__( 'Email', 'nm-gift-registry' ) :
				__( 'Email', 'nm-gift-registry-lite' );
		}

		if ( nmgr_get_option( 'enable' ) && 'no' !== nmgr_get_option( 'display_form_event_date' ) ) {
			$nmgr[ 'nmgr_event_date' ] = nmgr()->is_pro ?
				__( 'Event date', 'nm-gift-registry' ) :
				__( 'Event date', 'nm-gift-registry-lite' );
		}

		if ( nmgr_get_option( 'enable' ) && nmgr_get_option( 'enable_shipping' ) ) {
			$nmgr [ 'nmgr_shipping_address' ] = nmgr()->is_pro ?
				__( 'Ships to', 'nm-gift-registry' ) :
				__( 'Ships to', 'nm-gift-registry-lite' );
		}

		if ( nmgr_get_option( 'enable' ) ) {
			$qty_text = nmgr()->is_pro ?
				__( 'Desired Quantity', 'nm-gift-registry' ) :
				__( 'Desired Quantity', 'nm-gift-registry-lite' );
			$qty_svg = nmgr_get_svg( array(
				'icon' => 'cart-empty',
				'title' => $qty_text,
				'size' => 1.25,
				) );
			$nmgr[ 'nmgr_quantity' ] = "$qty_svg<span style='display:none;'>$qty_text</span>";

			$pur_qty_text = nmgr()->is_pro ?
				__( 'Purchased Quantity', 'nm-gift-registry' ) :
				__( 'Purchased Quantity', 'nm-gift-registry-lite' );
			$pur_qty_svg = nmgr_get_svg( array(
				'icon' => 'cart-full',
				'title' => $pur_qty_text,
				'size' => 1.25
				) );
			$nmgr[ 'nmgr_purchased_quantity' ] = "$pur_qty_svg<span style='display:none;'>$pur_qty_text</span>";

			$amt_pur_text = nmgr()->is_pro ?
				__( 'Amount purchased', 'nm-gift-registry' ) :
				__( 'Amount purchased', 'nm-gift-registry-lite' );
			$amt_pur_svg = nmgr_get_svg( array(
				'icon' => 'credit-card',
				'title' => sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Purchased amount: the value of the purchased items in the %s', 'nm-gift-registry' ) : __( 'Purchased amount: the value of the purchased items in the %s', 'nm-gift-registry-lite' ),
					nmgr_get_type_title()
				),
				'sprite' => false,
				'size' => 1.10
				) );

			$nmgr[ 'nmgr_purchased_amount' ] = "$amt_pur_svg<span style='display:none;'>$amt_pur_text</span>";
		}

		$total_amt_text = nmgr()->is_pro ?
			__( 'Total', 'nm-gift-registry' ) :
			__( 'Total', 'nm-gift-registry-lite' );
		$total_amt_svg = nmgr_get_svg( array(
			'icon' => 'credit-card-full',
			'title' => sprintf(
				/* translators: %s: wishlist type title */
				nmgr()->is_pro ? __( 'Total amount: the value of all items in the %s', 'nm-gift-registry' ) : __( 'Total amount: the value of all items in the %s', 'nm-gift-registry-lite' ),
				nmgr_get_type_title()
			),
			'sprite' => false,
			'size' => 1.10
			) );
		$nmgr[ 'nmgr_total_amount' ] = "$total_amt_svg<span style='display:none;'>$total_amt_text</span>";

		$nmgr[ 'nmgr_author' ] = nmgr()->is_pro ?
			__( 'Author', 'nm-gift-registry' ) :
			__( 'Author', 'nm-gift-registry-lite' );

		$sorted_columns = array_slice( $columns, 0, count( $columns ) - 1, true ) +
			$nmgr +
			array_slice( $columns, -1, 1, true );

		return $sorted_columns;
	}

	public static function get_column_info( $sortable_columns ) {
		$sortable_columns[ 'nmgr_quantity' ] = 'quantity';
		$sortable_columns[ 'nmgr_purchased_quantity' ] = 'purchased-quantity';
		return $sortable_columns;
	}

	public static function get_column_contents( $column, $post_id ) {
		$wishlist = nmgr_get_wishlist( $post_id );

		switch ( $column ) {
			case 'nmgr_author':
				$user = $wishlist->get_user();
				if ( $user ) {
					$url = add_query_arg( [
						'post_type' => 'nm_gift_registry',
						'author' => $user->ID
						] );
					$author = '<a href="' . $url . '">' . $user->display_name . '</a>';
				} else {
					$author_text = nmgr()->is_pro ?
						__( 'Guest', 'nm-gift-registry' ) :
						__( 'Guest', 'nm-gift-registry-lite' );
					$author = '<span class="nmgr-post-author">' . $author_text . '</span>';
				}
				echo wp_kses_post( $author );
				break;

			case 'nmgr_display_name' :
				echo esc_html( $wishlist->get_display_name() );
				break;

			case 'nmgr_email' :
				echo esc_html( $wishlist->get_email() );
				break;

			case 'nmgr_event_date' :
				$date = $wishlist->get_event_date();
				if ( $date ) {
					echo esc_html( nmgr_format_date( $wishlist->get_event_date() ) );
					if ( $wishlist->is_expired() ) {
						$text = nmgr()->is_pro ?
							__( 'Expired', 'nm-gift-registry' ) :
							__( 'Expired', 'nm-gift-registry-lite' );
						echo '<br><strong class="nmgr-expired-text">' . $text . '</strong>';
					}
				} else {
					echo '&mdash;';
				}
				break;

			case 'nmgr_shipping_address' :
				echo is_a( wc()->countries, 'WC_Countries' ) ? wc()->countries->get_formatted_address( $wishlist->get_shipping() ) : '';
				break;

			case 'nmgr_quantity':
				echo esc_html( $wishlist->get_items_quantity_count() );
				break;

			case 'nmgr_purchased_quantity' :
				echo esc_html( $wishlist->get_items_purchased_quantity_count() );
				break;

			case 'nmgr_purchased_amount':
				echo wp_kses_post( wc_price( $wishlist->get_total_purchased_amount() ) );
				break;

			case 'nmgr_total_amount':
				echo wp_kses_post( wc_price( $wishlist->get_total() ) );
				break;
		}
	}

	/**
	 * Display wishlist post states
	 */
	public static function post_states( $states, $post ) {
		if ( absint( nmgr_get_option( 'wishlist_page_id' ) ) === $post->ID ) {
			$states[ 'nmgr_wishlist_page' ] = nmgr()->is_pro ?
				__( 'Wishlist Page', 'nm-gift-registry' ) :
				__( 'Wishlist Page', 'nm-gift-registry-lite' );
		}

		if ( absint( nmgr_get_option( 'page_id' ) ) === $post->ID ) {
			$states[ 'nmgr_gift_registry_page' ] = nmgr()->is_pro ?
				__( 'Gift Registry Page', 'nm-gift-registry' ) :
				__( 'Gift Registry Page', 'nm-gift-registry-lite' );
		}

		return $states;
	}

	public static function shop_order_column_headers( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $name ) {
			$new_columns[ $key ] = $name;

			if ( 'order_number' === $key ) {
				$new_columns[ 'nm_gift_registry' ] = nmgr_get_type_title( 'c' );
			}
		}

		return $new_columns;
	}

	public static function shop_order_column_contents( $column, $post_id_or_order ) {
		if ( 'nm_gift_registry' !== $column ) {
			return;
		}

		$order = is_numeric( $post_id_or_order ) ? wc_get_order( $post_id_or_order ) : $post_id_or_order;
		$order_data = new NMGR_Order_Data( $order );
		$data = array();

		foreach ( $order_data->get_wishlist_ids() as $wishlist_id ) {
			$data[ $wishlist_id ] = array(
				'wishlist_id' => $wishlist_id,
				'item_count' => count( $order_data->get_order_item_ids_for_wishlist( $wishlist_id ) )
			);
		}

		$column_data = apply_filters( 'nmgr_shop_order_column_data', $data, $order_data->get_order() );

		foreach ( $column_data as $data ) {
			$wishlist = isset( $data[ 'wishlist_id' ] ) ? nmgr_get_wishlist( $data[ 'wishlist_id' ] ) : false;
			$item_count = isset( $data[ 'item_count' ] ) ? $data[ 'item_count' ] : false;

			if ( $wishlist ) {
				echo '<div class="nm-wishlist">';
				echo nmgr_get_wishlist_link( $wishlist );
				echo '<div class="wishlist-details">';
				if ( $wishlist->get_display_name() ) {
					echo '<div class="wishlist-display-name">' . $wishlist->get_display_name() . '</div>';
				}

				if ( $item_count ) {
					$item_count_text = sprintf(
						/* translators: %s: count of items in wishlist */
						nmgr()->is_pro ? _n( '%s item', '%s items', $item_count, 'nm-gift-registry' ) : _n( '%s item', '%s items', $item_count, 'nm-gift-registry-lite' ),
						$item_count
					);
					echo '<div class="wishlist-item-count-in-order" style="color:#999;">' . $item_count_text . '</div>';
				}

				echo '</div></div>';
			}
		}
	}

	public static function woocommerce_styles() {
		global $post_type, $pagenow;

		if ( 'edit.php' === $pagenow && 'product' === $post_type ) {
			$css = 'table.wp-list-table .column-nm_gift_registry_count {width:48px;}';
			wp_add_inline_style( 'woocommerce_admin_styles', $css );
		}
	}

	public static function admin_footer_text( $text ) {
		if ( !is_nmgr_admin() ) {
			return $text;
		}

		$five_star = nmgr()->is_pro ?
			__( 'Five star', 'nm-gift-registry' ) :
			__( 'Five star', 'nm-gift-registry-lite' );

		return sprintf(
			/* translators: 1: plugin homepage link, 2: wordpress plugin review link */
			nmgr()->is_pro ? __( 'Thanks for creating with %1$s. Love it  &hearts;, please leave a %2$s rating.', 'nm-gift-registry' ) : __( 'Thanks for creating with %1$s. Love it  &hearts;, please leave a %2$s rating.', 'nm-gift-registry-lite' ),
			'<a href="https://nmerimedia.com" target="_blank">' . nmgr()->name . '</a>',
			'<a href="https://wordpress.org/support/plugin/nm-gift-registry-and-wishlist-lite/reviews?rate=5#new-post" target="_blank" aria-label="' . $five_star . '" title="' . $five_star . '" >&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
		);
	}

	public static function product_column_headers( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $name ) {
			$new_columns[ $key ] = $name;

			if ( 'name' === $key ) {
				$text = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( '%s count', 'nm-gift-registry' ) : __( '%s count', 'nm-gift-registry-lite' ),
					esc_html( nmgr_get_type_title( 'cf' ) )
				);

				$tip = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'The number of %s the product is in.', 'nm-gift-registry' ) : __( 'The number of %s the product is in.', 'nm-gift-registry-lite' ),
					esc_html( nmgr_get_type_title( '', true ) )
				);

				$col = '<span class="tips" data-tip="' . $tip . '">' .
					nmgr_get_svg( array(
						'icon' => 'heart',
						'style' => 'vertical-align:text-bottom;',
						'sprite' => false,
						'title' => $text,
					) ) . '</span>' .
					"<span style='display:none'>$text</span>";
				$new_columns[ 'nm_gift_registry_count' ] = $col;
			}
		}

		return $new_columns;
	}

	public static function product_column_contents( $column ) {
		global $post, $wpdb;

		if ( 'nm_gift_registry_count' !== $column ) {
			return;
		}

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(wishlist_id) FROM {$wpdb->prefix}nmgr_wishlist_items WHERE product_id = %d", $post->ID ) );

		echo '<span class="nm_gift_registry_count' . ($count ? '' : ' na') . '">' .
		($count ? $count : '&ndash;') .
		'</span>';
	}

	public static function product_sortable_columns( $columns ) {
		$columns[ 'nm_gift_registry_count' ] = 'nm_gift_registry_count';
		return $columns;
	}

	public static function order_products_by_wishlist_count( $vars ) {
		global $typenow;

		if ( 'product' === $typenow && 'nm_gift_registry_count' === $vars[ 'orderby' ] ?? null ) {
			add_filter( 'posts_join', array( __CLASS__, 'products_wishlist_count_sql_join' ) );
			add_filter( 'posts_orderby', array( __CLASS__, 'products_wishlist_count_sql_orderby' ) );
		}
		return $vars;
	}

	public static function products_wishlist_count_sql_join( $join ) {
		global $wpdb;
		$join .= " LEFT JOIN ( SELECT COUNT(*) AS nm_gift_registry_count, product_id FROM {$wpdb->prefix}nmgr_wishlist_items GROUP BY product_id ) AS n ON ID = n.product_id";
		return $join;
	}

	public static function products_wishlist_count_sql_orderby() {
		return "n.nm_gift_registry_count " . (sanitize_sql_orderby( wp_unslash( $_GET[ 'order' ] ?? 'ASC' ) ) );
	}

	public static function row_actions( $actions, $post ) {
		if ( 'nm_gift_registry' === $post->post_type ) {
			$actions = array_merge( array( 'id' => sprintf(
					/* translators: %d: wishlist ID. */
					nmgr()->is_pro ? __( 'ID: %d', 'nm-gift-registry' ) : __( 'ID: %d', 'nm-gift-registry-lite' ),
					$post->ID
				) ), $actions );
		}
		return $actions;
	}

}
