<?php

defined( 'ABSPATH' ) || exit;

class NMGR_Wishlist {

	protected $items = [];
	protected $type = null;
	protected $data = [];
	protected $id = 0;
	protected $extra_data = [];

	/**
	 * Wishlist data stored in wp posts table
	 */
	protected $core_data = array(
		'post_title' => '',
		'post_status' => 'publish',
		'post_type' => 'nm_gift_registry',
		'post_excerpt' => '',
		'post_name' => '',
		'post_date' => '',
		'post_author' => 0,
	);

	/**
	 * Wishlist meta data stored in post meta table
	 */
	protected $meta_data = array(
		'_first_name' => '',
		'_last_name' => '',
		'_partner_first_name' => '',
		'_partner_last_name' => '',
		'_email' => '',
		'_event_date' => '',
		'_shipping_first_name' => '',
		'_shipping_last_name' => '',
		'_shipping_company' => '',
		'_shipping_address_1' => '',
		'_shipping_address_2' => '',
		'_shipping_city' => '',
		'_shipping_postcode' => '',
		'_shipping_country' => '',
		'_shipping_state' => '',
		'_date_fulfilled' => null,
		'_nmgr_user_id' => 0,
		'_nmgr_guest' => 0,
		'_nmgr_expired' => 0,
	);

	/**
	 * Get the wishlist if ID is passed, otherwise the wishlist is new and empty.
	 *
	 * @param  int|object|NMGR_Wishlist $wishlist Wishlist to read.
	 */
	public function __construct( $wishlist = 0 ) {
		if ( !empty( $wishlist->ID ) ) {
			$this->set_id( $wishlist->ID );
		} elseif ( is_numeric( $wishlist ) && $wishlist > 0 ) {
			$this->set_id( $wishlist );
		} elseif ( $wishlist instanceof self ) {
			$this->set_id( $wishlist->get_id() );
		}

		// Get meta data
		if ( is_a( wc()->countries, 'WC_Countries' ) ) {
			$wc_shipping_fields = array_keys( wc()->countries->get_address_fields( '', 'shipping_' ) );
			foreach ( $wc_shipping_fields as $field ) {
				$key = str_replace( 'shipping_', '', $field );
				if ( !array_key_exists( "_shipping_$key", $this->meta_data ) ) {
					$this->meta_data[ "shipping_$key" ] = '';
				}
			}
		}

		$this->meta_data = apply_filters( 'nmgr_get_meta_data', $this->meta_data, $this );

		$data = array_merge(
			[ 'id' => $this->get_id() ],
			$this->core_data,
			$this->meta_data,
			$this->extra_data
		);

		$this->data = apply_filters( 'nmgr_default_data', $data, $this );

		if ( $this->get_id() > 0 ) {
			$this->read();
		}
	}

	/*
	  |--------------------------------------------------------------------------
	  | Getters
	  |--------------------------------------------------------------------------
	 */

	public function get_core_data() {
		return array_intersect_key( $this->data, $this->core_data );
	}

	public function get_meta_data() {
		return array_intersect_key( $this->data, $this->meta_data );
	}

	public function get_extra_data() {
		return $this->extra_data;
	}

	/**
	 * Get all data for this wishlist including wishlist items
	 * @return array Wishlist Data
	 */
	public function get_data() {
		$data = apply_filters_deprecated( 'nmgr_get_data', [ $this->data, $this ], '5.4' );
		return apply_filters_deprecated( 'nmgr_get_wishlist_data', [ $data, $this ], '5.4' );
	}

	public function get_id() {
		return ( int ) $this->id;
	}

	/**
	 * Get the title of the wishlist
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->data[ 'post_title' ];
	}

	/**
	 * Get the post status of the wishlist (e.g. publish, draft)
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->data[ 'post_status' ];
	}

	/**
	 * Get the first name of the wishlist owner
	 *
	 * @return string
	 */
	public function get_first_name() {
		return $this->data[ '_first_name' ];
	}

	/**
	 * Get the last name of the wishlist owner
	 *
	 * @return string
	 */
	public function get_last_name() {
		return $this->data[ '_last_name' ];
	}

	/**
	 * Get the first name and last name of the wishlist owner
	 *
	 * @return string
	 */
	public function get_full_name() {
		return trim( sprintf( '%1$s %2$s', $this->get_first_name(), $this->get_last_name() ) );
	}

	/**
	 * Get the first name of the wishlist owner's partner
	 *
	 * @return string
	 */
	public function get_partner_first_name() {
		return $this->data[ '_partner_first_name' ];
	}

	/**
	 * Get the last name of the wishlist owner's partner
	 *
	 * @return string
	 */
	public function get_partner_last_name() {
		return $this->data[ '_partner_last_name' ];
	}

	/**
	 * Get the first name and last name of the wishlist owner's partner
	 *
	 * @return string
	 */
	public function get_partner_full_name() {
		return trim( sprintf( '%1$s %2$s', $this->get_partner_first_name(), $this->get_partner_last_name() ) );
	}

	/**
	 * Get the display name for the wishlist
	 * This is the combination of the names of the wishlist owner and wishlist owner's partner if available
	 *
	 * @return string
	 */
	public function get_display_name() {
		$display_name = '';
		if ( $this->get_full_name() && $this->get_partner_full_name() ) {
			$display_name = "{$this->get_full_name()} &amp; {$this->get_partner_full_name()}";
		} elseif ( $this->get_full_name() ) {
			$display_name = $this->get_full_name();
		}
		return $display_name;
	}

	/**
	 * Get the registered email for the wishlist
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->data[ '_email' ];
	}

	/**
	 * Get the date for the wishlist event
	 *
	 * @return string
	 */
	public function get_event_date() {
		return $this->data[ '_event_date' ];
	}

	/**
	 * Get the wishlist description
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->data[ 'post_excerpt' ];
	}

	/**
	 * Get all shipping fields
	 *
	 * @return array
	 */
	public function get_shipping() {
		$shipping = array();
		foreach ( $this->get_meta_data() as $key => $value ) {
			if ( false !== strpos( $key, 'shipping_' ) ) {
				$u_key = str_replace( [ '_shipping_', 'shipping_' ], '', $key );
				$shipping[ $u_key ] = $value;
			}
		}
		return $shipping;
	}

	/**
	 * Get shipping first name.
	 *
	 * @return string
	 */
	public function get_shipping_first_name() {
		return $this->data[ '_shipping_first_name' ];
	}

	/**
	 * Get shipping_last_name.
	 *
	 * @return string
	 */
	public function get_shipping_last_name() {
		return $this->data[ '_shipping_last_name' ];
	}

	/**
	 * Get shipping company.
	 *
	 * @return string
	 */
	public function get_shipping_company() {
		return $this->data[ '_shipping_company' ];
	}

	/**
	 * Get shipping address line 1
	 *
	 * @return string
	 */
	public function get_shipping_address() {
		return $this->data[ '_shipping_address_1' ];
	}

	/**
	 * Get shipping address line 1.
	 *
	 * @return string
	 */
	public function get_shipping_address_1() {
		return $this->data[ '_shipping_address_1' ];
	}

	/**
	 * Get shipping address line 2.
	 *
	 * @return string
	 */
	public function get_shipping_address_2() {
		return $this->data[ '_shipping_address_2' ];
	}

	/**
	 * Get shipping city.
	 *
	 * @return string
	 */
	public function get_shipping_city() {
		return $this->data[ '_shipping_city' ];
	}

	/**
	 * Get shipping state.
	 *
	 * @return string
	 */
	public function get_shipping_state() {
		return $this->data[ '_shipping_state' ];
	}

	/**
	 * Get shipping postcode.
	 *
	 * @return string
	 */
	public function get_shipping_postcode() {
		return $this->data[ '_shipping_postcode' ];
	}

	/**
	 * Get shipping country.
	 *
	 * @return string
	 */
	public function get_shipping_country() {
		return $this->data[ '_shipping_country' ];
	}

	/**
	 * Get the date the wishlist was fulfilled
	 *
	 * This is the date all items in the wishlist were marked as purchased
	 *
	 * @return DateTime object
	 */
	public function get_date_fulfilled() {
		return $this->data[ '_date_fulfilled' ];
	}

	/**
	 * Get the total price of all items in the wishlist
	 *
	 * @param boolean $currency_symbol Whether to prefix the returned amount with the currency symbol
	 * @return float
	 */
	public function get_total( $currency_symbol = false ) {
		$total = ( float ) 0;

		foreach ( $this->get_items() as $item ) {
			$total += $item->get_total();
		}

		return $currency_symbol ?
			wc_price( $total, array( 'currency' => get_woocommerce_currency() ) ) :
			nmgr_round( $total );
	}

	/**
	 * Get the amount purchased for all items from orders
	 * @return float|int
	 */
	public function get_purchased_amount() {
		$amount = 0;
		foreach ( $this->get_items() as $item ) {
			$amount += $item->get_purchased_amount();
		}
		return nmgr_round( $amount );
	}

	public function get_items_purchased_amounts() {
		global $wpdb;

		$data = $this->cache_get( 'items_purchased_amounts' );

		if ( false === $data ) {
			$orders_table = nmgr_orders_table();
			$status_key = false !== strpos( $orders_table, 'posts' ) ? 'post_status' : 'status';

			$statuses = array_map( function ( $stat ) {
				return "wc-$stat";
			}, wc_get_is_paid_statuses() );
			$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

			$results = $wpdb->get_results( $wpdb->prepare(
					"SELECT order_item_id, meta_key, meta_value
				FROM {$wpdb->prefix}woocommerce_order_itemmeta
				WHERE order_item_id IN
				(
				SELECT oim.order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim
				LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oim.order_item_id = oi.order_item_id
				LEFT JOIN $orders_table AS po ON oi.order_id = po.ID
				WHERE oim.meta_key = 'nmgr_wishlist_id' AND oim.meta_value = %d
				AND po.$status_key IN ($status_placeholders)
				)
			",
					array_merge( [ $this->get_id() ], $statuses )
				) );

			$data = [];
			foreach ( $results as $val ) {
				switch ( $val->meta_key ) {
					case '_line_total':
					case '_line_subtotal':
					case '_line_subtotal_tax':
					case '_line_tax':
					case 'nmgr_item_id':
						$data[ $val->order_item_id ][ $val->meta_key ] = $val->meta_value;
						break;
				}
			}

			$this->cache_set( 'items_purchased_amounts', $data );
		}

		return $data;
	}

	/**
	 * Get the amount left to be purchased for all items from orders
	 * @return int|float
	 */
	public function get_unpurchased_amount() {
		$amount = 0;
		foreach ( $this->get_items() as $item ) {
			$amount += $item->get_unpurchased_amount();
		}
		return nmgr_round( $amount );
	}

	/**
	 * Get the total amount purchased from the wishlist
	 * @return int|float
	 */
	public function get_total_purchased_amount() {
		return nmgr_round(
			apply_filters( 'nmgr_wishlist_total_purchased_amount', $this->get_purchased_amount(), $this )
		);
	}

	/**
	 * Get the total amount left to be purchased for the wishlist
	 * @return int|float
	 */
	public function get_total_unpurchased_amount() {
		return $this->get_unpurchased_amount();
	}

	/**
	 * Get the permalink for the wishlist
	 *
	 * @return string
	 */
	public function get_permalink() {
		return apply_filters( 'nmgr_wishlist_permalink', get_permalink( $this->get_id() ), $this );
	}

	/**
	 * Get the user id of the user associated with the wishlist
	 *
	 * @return int
	 */
	public function get_user_id() {
		return $this->data[ '_nmgr_user_id' ];
	}

	/**
	 * Get the user associated with the wishlist
	 *
	 * @return WP_User|false
	 */
	public function get_user() {
		return is_numeric( $this->get_user_id() ) ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Get the customer associated with the wishlist
	 *
	 * This should be the same as the user associated with the wishlist
	 * but simply retrieved as a WC_Customer object
	 */
	public function get_customer() {
		return new \WC_Customer( $this->get_user_id() );
	}

	/**
	 * Get the slug of the wishlist
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->data[ 'post_name' ];
	}

	/**
	 * Get the date the wishlist was created
	 *
	 * @return string
	 */
	public function get_date_created() {
		return $this->data[ 'post_date' ];
	}

	/*
	  |--------------------------------------------------------------------------
	  | Setters
	  |--------------------------------------------------------------------------
	 */

	public function set_id( $value ) {
		$this->id = ( int ) $value;
	}

	/**
	 * Set the title of the wishlist
	 */
	public function set_title( $value ) {
		$this->data[ 'post_title' ] = $value;
	}

	/**
	 * Set the post status of the wishlist
	 */
	public function set_status( $value ) {
		$this->data[ 'post_status' ] = $value;
	}

	/**
	 * Set the first name of the wishlist owner
	 */
	public function set_first_name( $value ) {
		$this->data[ '_first_name' ] = $value;
	}

	/**
	 * Set the last name of the wishlist owner
	 */
	public function set_last_name( $value ) {
		$this->data[ '_last_name' ] = $value;
	}

	/**
	 * Set the first name of the wishlist owner's partner
	 */
	public function set_partner_first_name( $value ) {
		$this->data[ '_partner_first_name' ] = $value;
	}

	/**
	 * Set the last name of the wishlist owner's partner
	 */
	public function set_partner_last_name( $value ) {
		$this->data[ '_partner_last_name' ] = $value;
	}

	/**
	 * Set the registered email for the wishlist
	 */
	public function set_email( $value ) {
		$this->data[ '_email' ] = $value;
	}

	/**
	 * Set the date for the wishlist event
	 */
	public function set_event_date( $value ) {
		$this->data[ '_event_date' ] = $value;
	}

	/**
	 * Set the wishlist description
	 */
	public function set_description( $value ) {
		$this->data[ 'post_excerpt' ] = $value;
	}

	/**
	 * Set shipping first name.
	 *
	 * @param string $value Shipping first name.
	 */
	public function set_shipping_first_name( $value ) {
		$this->data[ '_shipping_first_name' ] = $value;
	}

	/**
	 * Set shipping last name.
	 *
	 * @param string $value Shipping last name.
	 */
	public function set_shipping_last_name( $value ) {
		$this->data[ '_shipping_last_name' ] = $value;
	}

	/**
	 * Set shipping company.
	 *
	 * @param string $value Shipping company.
	 */
	public function set_shipping_company( $value ) {
		$this->data[ '_shipping_company' ] = $value;
	}

	/**
	 * Set shipping address line 1.
	 *
	 * @param string $value Shipping address line 1.
	 */
	public function set_shipping_address_1( $value ) {
		$this->data[ '_shipping_address_1' ] = $value;
	}

	/**
	 * Set shipping address line 2.
	 *
	 * @param string $value Shipping address line 2.
	 */
	public function set_shipping_address_2( $value ) {
		$this->data[ '_shipping_address_2' ] = $value;
	}

	/**
	 * Set shipping city.
	 *
	 * @param string $value Shipping city.
	 */
	public function set_shipping_city( $value ) {
		$this->data[ '_shipping_city' ] = $value;
	}

	/**
	 * Set shipping state.
	 *
	 * @param string $value Shipping state.
	 */
	public function set_shipping_state( $value ) {
		$this->data[ '_shipping_state' ] = $value;
	}

	/**
	 * Set shipping postcode.
	 *
	 * @param string $value Shipping postcode.
	 */
	public function set_shipping_postcode( $value ) {
		$this->data[ '_shipping_postcode' ] = $value;
	}

	/**
	 * Set shipping country.
	 *
	 * @param string $value Shipping country.
	 */
	public function set_shipping_country( $value ) {
		$this->data[ '_shipping_country' ] = $value;
	}

	/**
	 * Set user id.
	 *
	 * @param int $value User ID.
	 */
	public function set_user_id( $value ) {
		$this->data[ 'post_author' ] = (is_numeric( $value ) ? ( int ) $value : 0 );
		$this->data[ '_nmgr_user_id' ] = $value;
	}

	/**
	 * Set the date the wishlist was fulfilled
	 *
	 * This is the date all items in the wishlist were marked as purchased
	 *
	 * @param string|integer|null $value UTC timestamp
	 */
	public function set_date_fulfilled( $value ) {
		$this->data[ '_date_fulfilled' ] = $value;
	}

	public function set_expiry( $value ) {
		$this->data[ '_nmgr_expired' ] = absint( $value );
	}

	/**
	 * Set the wishlist type
	 * @param string $type Taxonomy term slug
	 */
	public function set_type( $type ) {
		$this->type = $type;
	}

	/*
	  |--------------------------------------------------------------------------
	  | Wishlist Items
	  |--------------------------------------------------------------------------
	 */

	/**
	 * Remove all items  from this wishlist
	 *
	 * @return void
	 */
	public function delete_items() {
		global $wpdb;

		$this->clear_cache();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}nmgr_wishlist_items WHERE wishlist_id = %d", $this->get_id() ) );
	}

	/**
	 * Get all items in this wishlist
	 * @return NMGR_Wishlist_Item[]|\NMGR\Sub\Wishlist_Item[]
	 */
	public function get_items() {
		if ( !$this->items ) {
			$args = [ 'preload_products' => 'all' ];
			$this->items = apply_filters_deprecated( 'nmgr_items', [ $this->read_items( $args ), $this ], '5.0.0' );
		}
		return $this->items;
	}

	/**
	 * Get a single wishlist item from the wishlist
	 *
	 * @param  int  $id wishlist_item_id
	 * @return NMGR_Wishlist_Item|\NMGR\Sub\Wishlist_Item|false
	 */
	public function get_item( $id ) {
		if ( isset( $this->items[ $id ] ) ) {
			return $this->items[ $id ];
		}

		return nmgr_get_wishlist_item( $id );
	}

	public function get_item_by_unique_id( $unique_id ) {
		return $this->get_item_by_column( 'unique_id', $unique_id );
	}

	/**
	 * Remove an item from the wishlist.
	 *
	 * @param int $item_id Item ID to delete.
	 * @return false|void
	 */
	public function delete_item( $item_id ) {
		_deprecated_function( __METHOD__, '5.1.0', '$item->delete()' );
		$item = nmgr_get_wishlist_item( $item_id );
		$item->delete();
	}

	/**
	 * Get the total quantities of all items in the wishlist
	 * @return int
	 */
	public function get_items_quantity_count() {
		$count = 0;
		foreach ( $this->get_items() as $item ) {
			$count += $item->get_quantity();
		}
		return ( int ) $count;
	}

	/**
	 * Gets the count of purchased wishlist items
	 * @return int
	 */
	public function get_items_purchased_quantity_count() {
		$count = 0;
		foreach ( $this->get_items() as $item ) {
			$count += $item->get_purchased_quantity();
		}
		return ( int ) apply_filters( 'nmgr_wishlist_item_purchased_count', $count, $this );
	}

	/**
	 * Add an item (product) to this wishlist and save the item in the database
	 *
	 * @param  int|WC_Product $product_obj Product id or object.
	 * @param  int $qty Quantity to add.
	 * @param int $favourite Whether the product is marked as favourite in the wishlist. Values are 1 or 0.
	 * @param array $variation Product variations if the product is a variation.
	 * @param array $item_data Extra data associated with the item to be added.
	 *
	 * @return int
	 */
	public function add_item( $product_obj, $qty = 1, $favourite = null, $variation = [], $item_data = [] ) {
		$product = is_a( $product_obj, \WC_Product::class ) ? $product_obj : wc_get_product( $product_obj );

		if ( !$product || !$qty ) {
			return 0;
		}

		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

		// Generate a unique id to identify item in wishlist based on product ID, variation ID, and variation data
		$unique_id = $this->generate_unique_id( $product_id, $variation_id, $variation, $item_data );

		$args = array(
			'wishlist_id' => $this->get_id(),
			'product_id' => $product_id,
			'product_or_variation_id' => $variation_id ? $variation_id : $product_id,
			'variation_id' => $variation_id,
			'variation' => $variation,
			'quantity' => $qty,
			'favourite' => $favourite,
			'unique_id' => $unique_id,
		);

		$item = nmgr()->wishlist_item();

		$item_with_unique_id = $this->get_item_by_unique_id( $unique_id );
		if ( $item_with_unique_id ) {
			$item = $item_with_unique_id;

			// if the wishlist already has the item, we can only update the quantity
			$args[ 'quantity' ] = $item->get_quantity() + $qty;
		}

		foreach ( $args as $key => $value ) {
			$item->set_prop( $key, $value );
		}

		$item->save();

		return $item->get_id();
	}

	/**
	 * Checks if the wishlist has items
	 *
	 * @return boolean
	 */
	public function has_items() {
		return ( bool ) $this->get_items_count();
	}

	/**
	 * Generate a unique id for the wishlist item being added
	 *
	 * @param int $product_id ID of the product the key is being generated for
	 * @param int $variation_id Variation id of the product the key is being generated for
	 * @param array $variation Variation data for the wishlist item
	 * @param array $item_data Extra data passed to denote the uniqueness of the item in the
	 * wishlist
	 */
	public function generate_unique_id( $product_id, $variation_id = 0, $variation = array(), $item_data = array() ) {
		$id_parts = array( $product_id );

		if ( $variation_id && 0 !== $variation_id ) {
			$id_parts[] = $variation_id;
		}

		if ( is_array( $variation ) && !empty( $variation ) ) {
			$variation_key = '';
			foreach ( $variation as $key => $value ) {
				$variation_key .= trim( $key ) . trim( $value );
			}
			$id_parts[] = $variation_key;
		}

		if ( is_array( $item_data ) && !empty( $item_data ) ) {
			$item_data_key = '';
			foreach ( $item_data as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = http_build_query( $value );
				}
				$item_data_key .= trim( $key ) . trim( $value );
			}
			$id_parts[] = $item_data_key;
		}

		return md5( implode( '_', $id_parts ) );
	}

	/**
	 * Get all items in an order for this wishlist
	 *
	 * @param type $order_id Order id
	 */
	public function get_items_in_order( $order_id ) {
		if ( is_numeric( $order_id ) ) {
			$order = wc_get_order( $order_id );
		} elseif ( $order_id instanceof WC_Order ) {
			$order = $order_id;
		}

		if ( !$order ) {
			return;
		}

		$items = $order->get_items();
		$items_in_order = array();

		foreach ( $items as $item_id => $item ) {
			$order_item_wishlist_id = ( int ) $item->get_meta( 'nmgr_wishlist_id' );
			// We want to make sure the item has not been completely refunded
			if ( $order_item_wishlist_id &&
				( int ) $item->get_quantity() > absint( $order->get_qty_refunded_for_item( $item->get_id() ) ) ) {
				if ( $order_item_wishlist_id === $this->get_id() ) {
					$items_in_order[ $item_id ] = array(
						'name' => $item->get_name(),
						'quantity' => $item->get_quantity(),
						'variation_id' => $item->get_variation_id(),
						'total' => $item->get_total(),
					);
				}
			}
		}

		return apply_filters( 'nmgr_wishlist_get_items_in_order', $items_in_order, $order, $this );
	}

	/**
	 * Get the wishlist item representing a product, if the product is in the wishlist
	 *
	 * @param int|WC_Product $product_id The product id or object
	 * @return NMGR_Wishlist_Item|\NMGR\Sub\Wishlist_Item|false
	 */
	public function get_item_by_product( $product_id ) {
		$id = is_a( $product_id, 'WC_Product' ) ? $product_id->get_id() : $product_id;
		if ( $id ) {
			return $this->get_item_by_column( 'product_id', $id );
		}
	}

	public function get_item_by_column( $column_key, $column_value ) {
		global $wpdb;

		$args = [
			'where' => $wpdb->prepare( "AND items.$column_key = %s", $column_value ),
			'limit' => 1,
			'preload_products' => 'current',
		];

		$val = $this->read_items( $args );
		return !empty( $val ) ? reset( $val ) : false;
	}

	/*
	  |--------------------------------------------------------------------------
	  | Conditionals
	  |--------------------------------------------------------------------------
	 */

	/**
	 * Whether the wishlist has a shipping address
	 *
	 * The wishlist has a shipping address if all the required fields are filled
	 * or if the country and address 1 fields are filled.
	 *
	 * @return boolean
	 */
	public function has_shipping_address() {
		$address = $this->get_shipping();

		if ( !isset( $address[ 'country' ] ) || !$address[ 'country' ] ) {
			return false;
		}

		$fields = is_a( wc()->countries, 'WC_Countries' ) ? wc()->countries->get_address_fields( $address[ 'country' ], 'shipping_' ) : array();
		$required = array_keys( array_filter( $fields, function ( $field ) {
				return isset( $field[ 'required' ] ) && $field[ 'required' ];
			} ) );

		foreach ( $required as $field ) {
			$unprefixed = str_replace( 'shipping_', '', $field );
			if ( !isset( $address[ $unprefixed ] ) ||
				(isset( $address[ $unprefixed ] ) && !$address[ $unprefixed ] ) ) {
				return false;
			}
		}

		return (isset( $address[ 'address_1' ] ) && $address[ 'address_1' ]) ||
			(isset( $address[ 'address_2' ] ) && $address[ 'address_2' ]);
	}

	/**
	 * Whether the wishlist needs its shipping address to be filled
	 *
	 * The wishlist needs a shipping address if the shipping address is required in the
	 * plugin setting and it's shipping address is not completely filled.
	 *
	 * @return boolean
	 */
	public function needs_shipping_address() {
		$val = nmgr_get_type_option( $this->get_type(), 'shipping_address_required' ) &&
			!$this->has_shipping_address();
		return apply_filters( 'nmgr_wishlist_needs_shipping_address', $val, $this );
	}

	/**
	 * Checks if the wishlist has a product
	 *
	 * @param int|array $product_id Product id(s)
	 * @return boolean true|false
	 */
	public function has_product( $product_id ) {
		global $wpdb;

		$product_item_ids = ( array ) $product_id;

		$val = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$wpdb->prefix}nmgr_wishlist_items AS items
			WHERE wishlist_id = %d
			AND product_id IN ('" . implode( "','", array_map( 'intval', $product_item_ids ) ) . "')
			LIMIT 1
				",
				$this->get_id()
			) );

		return ( bool ) $val;
	}

	/**
	 * Check if all the items in the wishlist have been fully purchased
	 *
	 * @return boolean
	 */
	public function has_items_fulfilled() {
		$val = false;
		$items = $this->get_items();

		if ( !empty( $items ) ) {
			$val = true;
			foreach ( $items as $item ) {
				if ( !$item->is_fulfilled() ) {
					$val = false;
					break;
				}
			}
		}

		return $val;
	}

	public function has_quantity_mismatch() {
		foreach ( $this->get_items() as $item ) {
			if ( $item->get_quantity() < $item->get_purchased_quantity() ) {
				return true;
			}
		}
	}

	public function is_fulfilled() {
		return $this->has_items_fulfilled();
	}

	/**
	 * Check whether the wishlist is active.
	 * An active wishlist is a wishlist that has any of the registered post statuses and is not trashed.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->get_id() && in_array( $this->get_status(), nmgr_get_post_statuses() );
	}

	/**
	 * Check if the wishlist belongs to a guest
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_guest() {
		return $this->get_user_id() === get_post_meta( $this->get_id(), '_nmgr_guest', true );
	}

	/**
	 * Check if the wishlist has the term
	 * @param string $term_slug Default values are gift-registry, wishlist
	 * @return boolean
	 */
	public function is_type( $term_slug ) {
		return $term_slug === $this->get_type();
	}

	/**
	 * Check if the wishlist is expired.
	 * This happens if the event date is set and it is passed the current date
	 *
	 * We get this value directly from php using the event date rather than from the _nmgr_expired
	 * meta_key which is set in the database during a cron job and may not be available at the
	 * time of calling this function
	 *
	 * @return boolean
	 */
	public function is_expired() {
		return $this->get_event_date() && $this->get_expiry_days() < 0;
	}

	public function get_type() {
		return $this->type;
	}

	public static function get_type_from_db( $wishlist_id ) {
		$terms = wp_get_object_terms( $wishlist_id, [ 'nm_gift_registry_type' ], [
			'update_term_meta_cache' => false,
			'fields' => 'slugs',
			'number' => 1,
			] );

		if ( !empty( $terms ) && !is_wp_error( $terms ) ) {
			return reset( $terms );
		}
	}

	/**
	 * Get the days of expiry related to the event date
	 * @return boolean|int False if wishlist has no event date. Positive number if wishlist expiry
	 * is in the future, negative number if wishlist has expired, Zero if wishlist expiry is today.
	 */
	public function get_expiry_days() {
		if ( $this->get_event_date() ) {
			$event_date = nmgr_get_datetime( $this->get_event_date() );
			if ( $event_date ) {
				$diff = date_diff( new DateTime( current_time( 'Y-m-d' ) ), new DateTime( $event_date->format( 'Y-m-d' ) ) );
				return ( int ) $diff->format( "%R%a" );
			}
		}
		return false;
	}

	public function get_expiry() {
		return $this->data[ '_nmgr_expired' ];
	}

	/**
	 * Get the number of items in the wishlist
	 * @global type $wpdb
	 * @return type
	 */
	public function get_items_count() {
		return ( int ) count( $this->get_items() );
	}

	/**
	 * @return NMGR_Wishlist_Item[]|\NMGR\Sub\Wishlist_Item[]
	 */
	public function read_items( $args = [] ) {
		if ( !$this->get_id() ) {
			return [];
		}

		$preload_products = $args[ 'preload_products' ] ?? '';
		unset( $args[ 'preload_products' ] );

		$cache_key = md5( 'read_items' . implode( ',', $args ) );
		$items_data = $this->cache_get( $cache_key );

		if ( false === $items_data ) {
			$args[ 'where' ] = "AND items.wishlist_id = {$this->get_id()} " . ($args[ 'where' ] ?? '');
			$items_data = nmgr()->wishlist_item()->get_from_db( $args );
			$this->cache_set( $cache_key, $items_data );
		}

		$item_ids_to_class_objs = [];

		foreach ( $items_data as $item_data ) {
			$item = nmgr()->wishlist_item();
			$item->set_id( $item_data->wishlist_item_id );
			foreach ( array_keys( $item->get_data() ) as $key ) {
				if ( property_exists( $item_data, $key ) ) {
					$item->set_prop( $key, maybe_unserialize( $item_data->$key ) );
				}
			}
			$item->cache_set( 'read', clone $item );

			$item_ids_to_class_objs[ $item->get_id() ] = $item;
		}

		if ( $preload_products && !empty( $item_ids_to_class_objs ) ) {
			$include = ( 'all' === $preload_products ) ?
				$this->get_product_or_variation_ids() :
				array_column( $items_data, 'product_or_variation_id' );

			$args = [
				'include' => $include,
				'limit' => -1,
			];
			wc_get_products( $args );
		}

		return $item_ids_to_class_objs;
	}

	public function get_product_or_variation_ids() {
		global $wpdb;

		$ids = $this->cache_get( 'product_or_variation_ids' );

		if ( false === $ids ) {
			$ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT product_or_variation_id FROM {$wpdb->prefix}nmgr_wishlist_items WHERE wishlist_id = %d",
					$this->get_id()
				) );
			$this->cache_set( 'product_or_variation_ids', $ids );
		}

		return $ids;
	}

	/*
	  |--------------------------------------------------------------------------
	  | CRUD
	  |--------------------------------------------------------------------------
	 */

	public function save() {
		if ( $this->get_id() ) {
			$this->update();
		} else {
			$this->create();
		}
		return $this->get_id();
	}

	public function create() {
		$this->before_save();

		do_action_deprecated( 'nmgr_data_before_save', [ $this ], '5.4', 'nmgr_create_wishlist' );
		do_action( 'nmgr_create_wishlist', $this );

		$id = wp_insert_post( $this->get_core_data() );

		if ( $id && !is_wp_error( $id ) ) {
			$this->set_id( $id );

			if ( !$this->type ) {
				$type = nmgr_get_type_option( 'gift-registry', 'enable' ) ? 'gift-registry' : 'wishlist';
				$this->type = apply_filters_deprecated( 'nmgr_default_type', [ $type ], '5.4' );
			}

			$this->save_type();
			$this->update_meta_data();

			do_action_deprecated( 'nmgr_save', [ $this->get_data(), [], $this ], '5.4', 'nmgr_created_wishlist' );
			do_action( 'nmgr_created_wishlist', $id, $this );
			do_action( 'nmgr_save_extra_data', $this->extra_data, $this );
			do_action_deprecated( 'nmgr_data_after_save', [ $this ], '5.4', 'nmgr_created_wishlist' );
		}
	}

	public function read() {
		$wishlist = $this->cache_get( 'read' );
		if ( false === $wishlist ) {
			$post = get_post( $this->get_id() );

			if ( !$this->get_id() || !$post || 'nm_gift_registry' !== $post->post_type ) {
				throw new Exception( sprintf(
							/* translators: %s: wishlist type title */
							nmgr()->is_pro ? __( 'Invalid %s.', 'nm-gift-registry' ) : __( 'Invalid %s.', 'nm-gift-registry-lite' ),
							nmgr_get_type_title()
						) );
			}

			foreach ( array_keys( $this->get_data() ) as $key ) {
				if ( property_exists( $post, $key ) ) {
					$this->set_prop( $key, $post->$key );
				}
			}

			$type = $this->get_type_from_db( $this->get_id() );
			if ( !empty( $type ) ) {
				$this->set_type( $type );
			}

			$meta = get_post_meta( $this->get_id() );
			foreach ( array_keys( $this->get_meta_data() ) as $meta_key ) {
				if ( array_key_exists( $meta_key, $meta ) ) {
					$this->set_prop( $meta_key, maybe_unserialize( $meta[ $meta_key ][ 0 ] ) );
				}
			}

			$this->cache_set( 'read', clone $this );
		} else {
			foreach ( get_object_vars( $wishlist ) as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->{$key} = $value;
				}
			}
		}
	}

	public function update() {
		$this->before_save();

		do_action_deprecated( 'nmgr_data_before_save', [ $this ], '5.4', 'nmgr_update_wishlist' );
		do_action( 'nmgr_update_wishlist', $this );

		/**
		 * Use $wpdb->update to update directly if doing the save_post action (such as in admin screen)
		 * to prevent infinite loops
		 */
		if ( doing_action( 'save_post_nm_gift_registry' ) ) {
			$GLOBALS[ 'wpdb' ]->update(
				$GLOBALS[ 'wpdb' ]->posts,
				$this->get_core_data(),
				array( 'ID' => $this->get_id() )
			);
			clean_post_cache( $this->get_id() );
		} else {
			wp_update_post( array_merge( array( 'ID' => $this->get_id() ), $this->get_core_data() ) );
		}

		/**
		 * We don't call the save_type() method when updating, as we do when creating,
		 * because we do not expect to update the wishlist type. It should not change.
		 */
		$this->update_meta_data();
		$cache = $this->cache_get( 'read' );
		$cache_data = $cache ? $cache->get_data() : [];
		$this->clear_cache();

		do_action_deprecated( 'nmgr_save', [ $this->get_data(), $cache_data, $this ], '5.4', 'nmgr_updated_wishlist' );
		do_action( 'nmgr_updated_wishlist', $this->get_id(), $this );
		do_action( 'nmgr_save_extra_data', $this->extra_data, $this );
		do_action_deprecated( 'nmgr_data_after_save', [ $this ], '5.4', 'nmgr_updated_wishlist' );
	}

	public function delete( $force_delete = false ) {
		$id = $this->get_id();

		if ( !$id ) {
			return;
		}

		$this->clear_cache();

		if ( $force_delete ) {
			wp_delete_post( $id );
			$this->set_id( 0 );
		} else {
			wp_trash_post( $id );
			$this->set_status( 'trash' );
		}

		$this->set_id( 0 );
		return true;
	}

	public function save_type() {
		if ( $this->get_type() ) {
			wp_set_post_terms( $this->get_id(),
				nmgr_get_term_id_by_slug( $this->get_type() ), 'nm_gift_registry_type' );
		}
	}

	public function update_meta_data() {
		foreach ( $this->get_meta_data() as $key => $value ) {
			update_post_meta( $this->get_id(), $key, $value );
		}
	}

	public function set_prop( $prop, $value ) {
		$key = ltrim( $prop, '_' );
		if ( is_callable( array( $this, "set_$key" ) ) ) {
			$this->{"set_$key"}( $value );
		} elseif ( array_key_exists( $prop, $this->get_data() ) ) {
			$this->data[ $prop ] = $value;
		} else {
			$this->extra_data[ $prop ] = $value;
		}
	}

	/**
	 * Actions to perform before saving a wishlist
	 */
	protected function before_save() {
		// If the wishlist belongs to a guest, ensure the '_nmgr_guest' meta key is set to the guest's user id
		$user_id = $this->get_user_id();
		if ( $user_id && !is_numeric( $user_id ) ) {
			$this->data[ '_nmgr_guest' ] = $user_id;
		}

		// Update wishlist expired status if necessary
		$is_expired = absint( $this->is_expired() );
		if ( $is_expired !== $this->get_expiry() ) {
			$this->set_expiry( $is_expired );
		}
	}

	public function cache_get( $key ) {
		$data = wp_cache_get( $this->get_id(), 'nmgr_wishlist' );
		return (false !== $data && array_key_exists( $key, $data ) ) ? $data[ $key ] : false;
	}

	public function cache_set( $key, $value ) {
		$data = wp_cache_get( $this->get_id(), 'nmgr_wishlist' );
		if ( false === $data ) {
			$data = [];
		}

		$data[ $key ] = $value;
		wp_cache_set( $this->get_id(), $data, 'nmgr_wishlist' );
	}

	public function clear_cache() {
		wp_cache_delete( $this->get_id(), 'nmgr_wishlist' );
		wp_cache_delete( "total_{$this->get_id()}", 'nmgr_wishlist' );
	}

}
