<?php

defined( 'ABSPATH' ) || exit;

class NMGR_Wishlist_Item {

	protected $id = 0;

	/**
	 * Wishlist item data stored in nmgr_wishlist_items table
	 *
	 * @var array
	 */
	protected $data = array(
		'wishlist_id' => 0,
		'product_or_variation_id' => 0,
		'product_id' => 0,
		'variation_id' => 0,
		'variation' => array(),
		'quantity' => 0,
		'purchased_quantity' => 0,
		'unique_id' => '',
		'quantity_reference' => array(),
		'purchase_log' => array(),
		'date_created' => '',
	);

	/**
	 * Left here for crowdfunding plugin
	 * @todo Remove in crowdfunding version > 4.13
	 */
	protected $core_data = [];

	/**
	 * @param int|object $item ID to load from the DB, or NMGR_Wishlist_Item object.
	 */
	public function __construct( $item = 0 ) {
		/**
		 * Left here for crowdfunding plugin
		 * @todo Remove in crowdfunding version > 4.13
		 */
		$this->data = array_merge( $this->data, $this->core_data );

		// $item would be an object with 'wishlist_item_id' if read from database
		if ( is_object( $item ) && !empty( $item->wishlist_item_id ) ) {
			$this->set_id( absint( $item->wishlist_item_id ) );
		} elseif ( is_numeric( $item ) && $item > 0 ) {
			$this->set_id( $item );
		} elseif ( $item instanceof self ) {
			$this->set_id( absint( $item->get_id() ) );
		}

		if ( $this->get_id() > 0 ) {
			$this->read();
		}
	}

	/*
	  |--------------------------------------------------------------------------
	  | Getters
	  |--------------------------------------------------------------------------
	 */

	public function get_id() {
		return ( int ) $this->id;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_product_name() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->get_name()' );
		return $this->get_product()->get_name();
	}

	public function get_product_sku() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->get_sku()' );
		return $this->get_product()->get_sku();
	}

	public function get_product_stock_status() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->get_stock_status()' );
		return $this->get_product()->get_stock_status();
	}

	/**
	 * @return int|null
	 */
	public function get_product_stock_quantity() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->get_product_stock_quantity()' );
		return $this->get_product()->get_stock_quantity();
	}

	public function get_product_image_id() {
		_deprecated_function( __METHOD__, '5.1.0' );
		return $this->get_product()->get_image_id();
	}

	public function get_product_image( $size = 'nmgr_thumbnail', $args = [] ) {
		$attr = array_merge( [
			'title' => $this->get_product()->get_name(),
			'class' => 'nmgr-tip',
			'alt' => $this->get_product()->get_name()
			],
			$args
		);

		return $this->get_product()->get_image( $size, $attr );
	}

	public function is_in_stock() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->is_in_stock()' );
		return $this->get_product()->is_in_stock();
	}

	public function is_purchasable() {
		_deprecated_function( __METHOD__, '5.3.0', 'get_product()->is_purchasable()' );
		return $this->get_product()->is_purchasable();
	}

	public function get_product_permalink() {
		$product = $this->get_product();
		if ( $product->exists() ) {
			if ( !empty( $this->get_variation() ) ) {
				$url = $product->get_permalink( [ 'variation' => $this->get_variation() ] );
			} else {
				$url = $product->get_permalink();
			}
		}
		return $url ?? '';
	}

	/**
	 * Get the id of the wishlist the item belongs to
	 *
	 * @return int
	 */
	public function get_wishlist_id() {
		return $this->data[ 'wishlist_id' ];
	}

	/**
	 * Get the date the item was added to the wishlist
	 *
	 * @return Timestamp
	 */
	public function get_date_created() {
		return $this->data[ 'date_created' ];
	}

	public function get_product_or_variation_id() {
		return $this->data[ 'product_or_variation_id' ];
	}

	public function get_product_id() {
		return $this->data[ 'product_id' ];
	}

	/**
	 * Get the id of the product variation this item represents
	 *
	 * @return int
	 */
	public function get_variation_id() {
		return $this->data[ 'variation_id' ];
	}

	/**
	 * Get the variation of the product this item represents
	 *
	 * @return array
	 */
	public function get_variation() {
		return array_filter( ( array ) $this->data[ 'variation' ] );
	}

	/**
	 * Get the quantity of this item in the wishlist
	 *
	 * @return int
	 */
	public function get_quantity() {
		return $this->data[ 'quantity' ];
	}

	/**
	 * Get the purchased quantity of this item in the wishlist
	 *
	 * @return int
	 */
	public function get_purchased_quantity() {
		return ( int ) apply_filters( 'nmgr_item_purchased_quantity', $this->data[ 'purchased_quantity' ], $this );
	}

	/**
	 * Get the unpurchased quantity of the item
	 * This only works if the quantity and purchased quantity columns are visible on the items table
	 *
	 * @return int
	 */
	public function get_unpurchased_quantity() {
		return max( $this->get_quantity() - $this->get_purchased_quantity(), 0 );
	}

	/**
	 * Get the unique id of this item
	 *
	 * @return string
	 */
	public function get_unique_id() {
		return $this->data[ 'unique_id' ];
	}

	/**
	 * Get the quantity reference of this item
	 *
	 * @return array
	 */
	public function get_quantity_reference() {
		return array_filter( ( array ) $this->data[ 'quantity_reference' ] );
	}

	/**
	 * Get the product this item represents
	 * @return WC_Product|false
	 */
	public function get_product() {
		$product = wc_get_product( $this->get_product_or_variation_id() );
		return $product ? $product : new \WC_Product();
	}

	/**
	 * Get the wishlist this item belongs to
	 *
	 * @return NMGR_Wishlist
	 */
	public function get_wishlist() {
		return nmgr_get_wishlist( $this->get_wishlist_id() );
	}

	/**
	 * Get the total cost of the wishlist item (cost of product x qty)
	 *
	 * @param bool $currency_symbol Whether to return the value formatted with the currency symbol
	 * @return string
	 */
	public function get_total( $currency_symbol = false ) {
		$total = nmgr_round( wc_get_price_to_display( $this->get_product(), [
			'qty' => $this->get_quantity(),
			] ) );
		return $currency_symbol ? wc_price( $total, array( 'currency' => get_woocommerce_currency() ) ) : $total;
	}

	public function get_cost( $currency_symbol = false ) {
		$price = nmgr_round( wc_get_price_to_display( $this->get_product() ) );
		return $currency_symbol ? wc_price( $price, array( 'currency' => get_woocommerce_currency() ) ) : $price;
	}

	/**
	 * Get the method used to purchase quantities of the item
	 * @return array
	 */
	public function get_purchase_log() {
		return array_filter( ( array ) $this->data[ 'purchase_log' ] );
	}

	public function get_purchase_log_props() {
		$pro = nmgr()->is_pro;

		return [
			'id' => [
				'label' => $pro ? __( 'ID', 'nm-gift-registry' ) : __( 'ID', 'nm-gift-registry-lite' ),
			],
			'type' => [
				'label' => $pro ? __( 'Type', 'nm-gift-registry' ) : __( 'Type', 'nm-gift-registry-lite' ),
			],
			'quantity' => [
				'label' => $pro ? __( 'Quantity', 'nm-gift-registry' ) : __( 'Quantity', 'nm-gift-registry-lite' ),
			],
			'user_id' => [
				'label' => $pro ? __( 'User ID', 'nm-gift-registry' ) : __( 'User ID', 'nm-gift-registry-lite' ),
			],
			'route' => [
				'label' => $pro ? __( 'Route', 'nm-gift-registry' ) : __( 'Route', 'nm-gift-registry-lite' ),
			],
			'date' => [
				'label' => $pro ? __( 'Date', 'nm-gift-registry' ) : __( 'Date', 'nm-gift-registry-lite' ),
			],
		];
	}

	/**
	 * Add a purchase method for an item when it's purchased quantity has been updated
	 * to log details about the particular update
	 *
	 * @param int $quantity The quantity of the item reflecting the current purchase.
	 * This number should be negative if the purchase is a refund and positive if it
	 * is indeed a purchase.
	 * @param type $method_type The type of purchase. Default values are 'order' and 'manual'
	 * to determine whether the purchased quantity was updated via an order or manually via
	 * the wishlist items table
	 * @param array $args Extra data to add to the purchase method log. For example 'order_id'.
	 */
	public function add_purchase_log( $quantity, $method_type = 'order', $args = [] ) {
		$original = $this->get_purchase_log();
		$id = $this->get_wishlist_id() . '_' . $this->get_id() . '_' . time();
		$default = [
			'id' => $id,
			'type' => $method_type,
			'quantity' => ( int ) $quantity,
			'user_id' => get_current_user_id(),
			'route' => is_nmgr_admin() ? 'admin' : 'frontend',
			'date' => current_time( 'mysql' ),
		];

		$fargs = array_merge( $default, $args );
		$original[ $id ] = $fargs;
		$this->set_purchase_log( $original );
	}

	public function set_purchase_log( $value ) {
		$this->data[ 'purchase_log' ] = $value;
	}

	/**
	 * Get the amount purchased for the item from orders
	 *
	 * This is taken from the actual price of the item in each order
	 * typical made from the checkout page.
	 * @return int|float
	 */
	public function get_purchased_amount() {
		$amount = 0;

		foreach ( $this->get_wishlist()->get_items_purchased_amounts() as $item_amt ) {
			if ( $this->get_id() === ( int ) $item_amt[ 'nmgr_item_id' ] ) {
				/**
				 * We use line_total because that is what is used in the orders table.
				 * We should maybe use line subtotal as that is the actual price of the product times the quantity
				 * as listed in the items table.
				 */
				$amount += ( float ) $item_amt[ '_line_total' ] + ( float ) $item_amt[ '_line_tax' ];
			}
		}

		return nmgr_round( $amount );
	}

	/**
	 * Get the total amount purchased for the item.
	 * This may include amount purchased from orders and other methods such as crowdfunds.
	 * @return float
	 */
	public function get_total_purchased_amount() {
		return nmgr_round( apply_filters( 'nmgr_item_total_purchased_amount', $this->get_purchased_amount(), $this ) );
	}

	/**
	 * Get the amount left to be purchased for the item
	 * @return int|float
	 */
	public function get_unpurchased_amount() {
		return nmgr_round(
			wc_get_price_to_display( $this->get_product(), [ 'qty' => $this->get_unpurchased_quantity() ] )
		);
	}

	/**
	 * Get the total amount left to be purchased for the item
	 * This may include amount unpurchased from orders and other methods such as crowdfunds.
	 * @return int|float
	 */
	public function get_total_unpurchased_amount() {
		return $this->get_unpurchased_amount();
	}

	/**
	 * Get the data that would be used to identify the item in the cart (as cart item data)
	 * or in the order (as order item metadata)
	 *
	 * Wishlist information gotten:
	 * - wishlist_id
	 * - wishlist_item_id
	 * - product_id
	 * - variation_id
	 * - type
	 *
	 * @return array
	 */
	public function get_cart_order_data() {
		return array(
			'wishlist_id' => ( int ) $this->get_wishlist_id(),
			'wishlist_item_id' => ( int ) $this->get_id(),
			'product_id' => $this->get_product_id(),
			'variation_id' => $this->get_variation_id(),
			'type' => 'wishlist_item',
		);
	}

	public function get_paid_order_item_ids() {
		global $wpdb;

		$orders_table = nmgr_orders_table();
		$status_key = false !== strpos( $orders_table, 'posts' ) ? 'post_status' : 'status';
		$order_item_ids = $this->cache_get( 'paid_order_item_ids' );

		if ( false === $order_item_ids ) {
			$order_item_ids = $wpdb->get_col( $wpdb->prepare( "
		SELECT DISTINCT oim.order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim
		LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oim.order_item_id = oi.order_item_id
		LEFT JOIN $orders_table AS po ON oi.order_id = po.ID
		WHERE oim.meta_key = 'nmgr_item_id' AND oim.meta_value = %d
		AND po.$status_key IN ('wc-" . implode( "','wc-", array_map( 'esc_sql', nmgr_is_paid_statuses() ) ) . "')"
					,
					$this->get_id()
				) );

			$this->cache_set( 'paid_order_item_ids', $order_item_ids );
		}

		return is_array( $order_item_ids ) ? array_map( 'absint', $order_item_ids ) : [];
	}

	/*
	  |--------------------------------------------------------------------------
	  | Setters
	  |--------------------------------------------------------------------------
	 */

	public function set_id( $id ) {
		$this->id = ( int ) $id;
	}

	/**
	 * Set wishlist ID.
	 *
	 * @param int $value Wishlist ID.
	 */
	public function set_wishlist_id( $value ) {
		$this->data[ 'wishlist_id' ] = ( int ) $value;
	}

	/**
	 * Set item desired quantity
	 *
	 * @param int $value Desired quantity
	 */
	public function set_quantity( $value ) {
		$this->data[ 'quantity' ] = ( int ) $value;
	}

	/**
	 * Set item purchased quantity
	 *
	 * @param int $value purchased quantity
	 */
	public function set_purchased_quantity( $value ) {
		$this->data[ 'purchased_quantity' ] = ( int ) $value;
	}

	/**
	 * Set item product id
	 *
	 * @param int $value Product id.
	 */
	public function set_product_id( $value ) {
		$this->data[ 'product_id' ] = ( int ) $value;
	}

	/**
	 * Set item variation id
	 * @param int $value Product Id/Variation id
	 */
	public function set_variation_id( $value ) {
		$this->data[ 'variation_id' ] = ( int ) $value;
	}

	/**
	 * Set the item variation
	 * @param array Product variation
	 */
	public function set_variation( $value ) {
		$this->data[ 'variation' ] = $value;
	}

	/**
	 * Set all product details for item at once based on the product the item represents
	 *
	 * This sets the product id, variation id and variation
	 *
	 * @param WC_Product $product Product the item represents
	 */
	public function set_product( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			$this->set_product_id( $product->get_parent_id() );
			$this->set_variation_id( $product->get_id() );
			$this->set_variation( is_callable( array( $product, 'get_variation_attributes' ) ) ?
					$product->get_variation_attributes() : array()
			);
		} else {
			$this->set_product_id( $product->get_id() );
		}
	}

	/**
	 * Set the unique id for the item
	 * @param string $value unique id
	 */
	public function set_unique_id( $value ) {
		$this->data[ 'unique_id' ] = $value;
	}

	/**
	 * Set the quantity reference for the item
	 * @param array $value Quantity reference
	 */
	public function set_quantity_reference( $value ) {
		$this->data[ 'quantity_reference' ] = $value;
	}

	/**
	 * Add the data for this wishlist item as order item meta
	 * Make sure to save the order item ($order_item) afterwards as this
	 * function doesn't save it.
	 * @param WC_Order_Item $order_item
	 */
	public function add_order_item_meta( $order_item ) {
		$order_item->add_meta_data( 'nmgr_item_id', $this->get_id() );
		$order_item->add_meta_data( 'nmgr_wishlist_id', $this->get_wishlist_id() );
	}

	/*
	  |--------------------------------------------------------------------------
	  | Conditionals
	  |--------------------------------------------------------------------------
	 */

	/**
	 * Get whether any quantity of this item has been purchased
	 *
	 * This is only possible if the 'purchased quantity' column is visible on the items table
	 * as it is the column used to determine that item purchased would be accounted for
	 *
	 * @return boolean True or false
	 */
	public function is_purchased() {
		$purchased = ( bool ) $this->get_purchased_quantity();
		return ( bool ) apply_filters( 'nmgr_item_is_purchased', $purchased, $this );
	}

	/**
	 * Get whether the desired quantity of this item has been completely purchased
	 *
	 * This is typically only possible if the 'quantity' and 'purchased_quantity' columns are visible on the items table
	 *
	 * @return boolean
	 */
	public function is_fulfilled() {
		$fulfilled = ( bool ) 0 >= $this->get_unpurchased_quantity();
		return ( bool ) apply_filters( 'nmgr_item_is_fulfilled', $fulfilled, $this );
	}

	public function get_wishlist_type() {
		return nmgr()->wishlist()->get_type_from_db( $this->get_wishlist_id() );
	}

	/**
	 * Wrapper function to update the purchased quantity of an item
	 *
	 * @param array $args Arguments used to perform the update:
	 * - quantity {int} The new purchased quantity.
	 * - paid {boolean} Whether the order should be marked as paid. Default false.
	 * - create_order {boolean} Whether to create an order to reflect the update. Default true.
	 * - apply_price - {boolean) Whether to include the price of the item in the created order. Default true.
	 * - order_note - {string}. Order note that should be added to the order. Default none.
	 * - order_item_meta = {array} Metadata that should be added to the order item if created. Default none.
	 * @return null|WC_Order order object if an order is created, else null
	 */
	public function update_purchased_quantity( $args = [] ) {
		if ( isset( $args[ 'quantity' ] ) ) {
			$update_qty = ( int ) $args[ 'quantity' ];
			$purchased_qty = $this->get_purchased_quantity();

			if ( $update_qty < $purchased_qty ) {
				$this->set_purchased_quantity( $update_qty );
				$this->save();
			} elseif ( $update_qty > $purchased_qty ) {
				if ( false === ( bool ) ( $args[ 'create_order' ] ?? true ) ) {
					$this->set_purchased_quantity( $update_qty );
					$this->save();
				} else {
					$args[ 'quantity' ] = $update_qty - $purchased_qty;
					return $this->create_order( $args );
				}
			}
		}
	}

	/**
	 * Create an order for the wishlist item
	 *
	 * @param array $args Arguments used:
	 * - quantity {int} The order item quantity.
	 * - paid {boolean} Whether the order should be marked as paid. Default false.
	 * - apply_price - {boolean) Whether to include the price of the item in the created order. Default true.
	 * - order_note - {string}. Order note that should be added to the order. Default none.
	 * - order_item_meta = {array} Metadata that should be added to the order item if created. Default none.
	 * - include_tax - {int|boolean} Whether to include tax
	 * - product - {WC_Product|false} The product to associate with the wishlist item. Default, wishlist item product.
	 * - type - {string} The item type. Default wishlist_item.
	 * @return null|WC_Order order object if an order is created, else null
	 */
	public function create_order( $args = [] ) {
		$order = new \WC_Order();
		$order->set_created_via( 'nmgr_wishlist' );
		$order->set_customer_id( get_current_user_id() );
		$status = ( $args[ 'paid' ] ?? false ) ? 'processing' : 'on-hold';
		$order->set_status( apply_filters( 'nmgr_item_purchased_order_status', $status, $args ) );
		$type = ($args[ 'type' ] ?? 'wishlist_item');

		if ( $args[ 'paid' ] ?? false ) {
			$order->set_date_paid( time() );
		}

		if ( 'wishlist_item' === $type ) {
			$shipping = $args[ 'shipping' ] ?? $this->get_wishlist()->get_shipping();
			foreach ( $shipping as $key => $value ) {
				if ( is_callable( array( $order, "set_shipping_{$key}" ) ) ) {
					$order->{"set_shipping_{$key}"}( $value );
				}
			}
		}

		$product = $args[ 'product' ] ?? $this->get_product();

		if ( $product ) {
			$order_item = new \WC_Order_Item_Product();
			$quantity = $args[ 'quantity' ] ?? 1;
			$apply_price = ( bool ) ( $args[ 'apply_price' ] ?? true );
			$subtotal = wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );
			$tax = 0;
			if ( !empty( $args[ 'include_tax' ] ) ) {
				$price_including_tax = wc_get_price_including_tax( $product, [ 'qty' => $quantity ] );
				$tax = $price_including_tax - $subtotal;
			}

			$order_item->set_props( [
				'name' => $product->get_name(),
				'tax_class' => $product->get_tax_class(),
				'product_id' => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				'variation' => $product->is_type( 'variation' ) ? $product->get_attributes() : array(),
				'quantity' => $quantity,
				'subtotal' => $subtotal,
				'total' => (false === $apply_price) ? 0 : $subtotal,
				'subtotal_tax' => $tax,
				'total_tax' => $tax,
			] );

			if ( !empty( $args[ 'order_item_meta' ] ) ) {
				foreach ( $args[ 'order_item_meta' ] as $meta_key => $meta_value ) {
					$order_item->add_meta_data( $meta_key, $meta_value );
				}
			}

			if ( 'wishlist_item' === $type ) {
				$this->add_order_item_meta( $order_item );
			}

			$order->add_item( $order_item );
			$order->calculate_totals();
		}

		if ( 'wishlist_item' === $type ) {
			nmgr()->order()->add_meta_data( $order ); // Order is saved here
		} else {
			$order->save();
		}

		$order->add_order_note( nmgr_get_custom_order_notice() );

		if ( !empty( $args[ 'order_note' ] ) ) {
			$order->add_order_note( $args[ 'order_note' ] );
		}

		return $order;
	}

	public function get_order_created_toast_notice( $order ) {
		if ( is_a( $order, \WC_Order::class ) ) {
			$order_notice = nmgr()->is_pro ?
				__( 'Order created', 'nm-gift-registry' ) :
				__( 'Order created', 'nm-gift-registry-lite' );

			if ( is_nmgr_admin() ) {
				$order_notice .= sprintf( ' <a style="color:#fff;text-decoration:underline;" href="%s" tabindex="1" class="nmgr-view-btn">%s</a>',
					esc_url( get_edit_post_link( $order->get_id() ) ),
					nmgr()->is_pro ? __( 'View', 'nm-gift-registry' ) : __( 'View', 'nm-gift-registry-lite' )
				);
			}

			return nmgr_get_toast_notice( $order_notice );
		}
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
		global $wpdb;

		do_action_deprecated( 'nmgr_data_before_save', [ $this ], '5.4', 'nmgr_item_create' );
		do_action( 'nmgr_item_create', $this );

		$this->data[ 'date_created' ] = current_time( 'mysql', 1 );
		$wpdb->insert( $wpdb->prefix . 'nmgr_wishlist_items', array_map( 'maybe_serialize', $this->data ) );
		$this->set_id( $wpdb->insert_id );
		$this->clear_wishlist_cache();

		do_action_deprecated( 'nmgr_save', [ $this->data, [], $this ], '5.4', 'nmgr_item_created' );
		do_action_deprecated( 'nmgr_data_after_save', [ $this ], '5.4', 'nmgr_item_created' );
		do_action( 'nmgr_item_created', $this );
	}

	public function read() {
		$item = $this->cache_get( 'read' );

		if ( false === $item ) {
			$args = [
				'limit' => 1,
				'where' => 'AND items.wishlist_item_id = ' . $this->get_id(),
			];

			$data = $this->get_from_db( $args );

			if ( $data ) {
				$item_data = reset( $data );
				foreach ( $item_data as $key => $value ) {
					$this->set_prop( $key, maybe_unserialize( $value ) );
				}
				$this->cache_set( 'read', clone $this );
			} else {
				throw new Exception( sprintf(
							/* translators: %s: wishlist type title */
							nmgr()->is_pro ? __( 'Invalid %s item.', 'nm-gift-registry' ) : __( 'Invalid %s item.', 'nm-gift-registry-lite' ),
							nmgr_get_type_title()
						) );
			}
		} else {
			foreach ( get_object_vars( $item ) as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->{$key} = $value;
				}
			}
		}
	}

	public function update() {
		global $wpdb;

		do_action( 'nmgr_data_before_save', [ $this ], '5.4', 'nmgr_item_update' );
		do_action( 'nmgr_item_update', $this );

		$wpdb->update(
			$wpdb->prefix . 'nmgr_wishlist_items',
			array_map( 'maybe_serialize', $this->data ),
			array( 'wishlist_item_id' => $this->get_id() )
		);

		$cache = $this->cache_get( 'read' );
		$this->clear_cache();
		$this->clear_wishlist_cache();

		if ( ($cache) &&
			$cache->get_quantity() !== $this->get_quantity() ||
			$cache->get_purchased_quantity() !== $this->get_purchased_quantity() ) {
			$this->maybe_set_wishlist_as_fulfilled();
		}

		$original_data = $cache ? $cache->get_data() : [];
		do_action_deprecated( 'nmgr_save', [ $this->data, $original_data, $this ], '5.4', 'nmgr_item_updated' );
		do_action_deprecated( 'nmgr_data_after_save', [ $this ], '5.4', 'nmgr_item_updated' );
		do_action( 'nmgr_item_updated', $this, $original_data );
	}

	public function delete() {
		global $wpdb;

		$wishlist_id = $this->get_wishlist_id();

		do_action( 'nmgr_before_delete_wishlist_item', $this->get_id() );

		$this->clear_cache();
		$this->clear_wishlist_cache();

		$wpdb->delete( $wpdb->prefix . 'nmgr_wishlist_items', array( 'wishlist_item_id' => $this->get_id() ) );

		do_action( 'nmgr_wishlist_item_deleted', $this->get_id(), $wishlist_id );

		$this->set_id( 0 );
		return true;
	}

	public function maybe_set_wishlist_as_fulfilled() {
		$wishlist = nmgr_get_wishlist( $this->get_wishlist_id() );

		// If the wishlist is fulfilled we set a fulfilled date for the wishlist if it isn't already set
		if ( $wishlist->is_fulfilled() ) {
			if ( !$wishlist->get_date_fulfilled() ) {
				$wishlist->set_date_fulfilled( time() );
				$wishlist->save();
				/**
				 * Functions hooked into this action should typically only be run once
				 * (except in the case of refunds),
				 */
				do_action( 'nmgr_fulfilled_wishlist', $wishlist->get_id(), $wishlist );
			}
		} else {
			/**
			 * If the wishlist is not fulfilled (perhaps because of refunds)
			 * but it already has a fulfilled date set, remove the date
			 */
			if ( $wishlist->get_date_fulfilled() ) {
				$wishlist->set_date_fulfilled( null );
				$wishlist->save();
			}
		}
	}

	/**
	 * @return NMGR_Wishlist_Item[]|\NMGR\Sub\Wishlist_Item[]
	 */
	public function get_from_db( $args = [] ) {
		global $wpdb;

		$select = 'items.*';

		$limit = !empty( $args[ 'limit' ] ) ? max( 0, ( int ) $args[ 'limit' ] ) : 0;
		$limit_sql = $limit ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';

		$offset = !empty( $args[ 'page' ] ) ? max( 0, (( int ) $args[ 'page' ] - 1) * $limit ) : null;
		$offset_sql = $limit ? $wpdb->prepare( 'OFFSET %d', $offset ) : '';

		$where_sql = $args[ 'where' ] ?? '';
		$join_sql = '';
		$order_sql = '';
		$valid_orderbys = [
			'items.date_created',
			'items.favourite',
			'items.purchased_quantity',
			'total_cost',
			'cost',
			'title',
		];

		$orderby = $args[ 'orderby' ] ?? 'items.date_created';
		if ( in_array( $orderby, $valid_orderbys ) ) {
			$product_table_join = "LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup AS product
				ON items.product_or_variation_id = product.product_id";

			switch ( $orderby ) {
				case 'total_cost':
					$select .= ', product.max_price*items.quantity AS total_cost';
					$join_sql = $product_table_join;
				case 'cost':
					$select .= ", product.max_price AS cost";
					$join_sql = $product_table_join;
					break;
				case 'title':
					$select .= ", posts.post_title AS title";
					$join_sql = "LEFT JOIN $wpdb->posts AS posts ON items.product_or_variation_id = posts.ID";
					break;
			}

			$order_arg = $args[ 'order' ] ?? 'desc';
			$order = in_array( $order_arg, [ 'asc', 'desc' ] ) ? $order_arg : 'desc';
			$order_sql = "ORDER BY $orderby $order";
		}

		$sql = "SELECT $select FROM {$wpdb->prefix}nmgr_wishlist_items AS items $join_sql"
			. " WHERE 1=1 $where_sql $order_sql $limit_sql $offset_sql";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items_data = $wpdb->get_results( $sql );

		return $items_data;
	}

	public function set_prop( $prop, $value ) {
		/**
		 * Left here for crowdfunding plugin.
		 * @todo Remove in crowdfunding version > 4.13
		 * @since version 5.4
		 */
		if ( array_key_exists( $prop, $this->core_data ) ) {
			$this->data[ $prop ] = $value;
			return;
		}

		if ( array_key_exists( $prop, $this->data ) ) {
			if ( is_callable( array( $this, "set_$prop" ) ) ) {
				$this->{"set_$prop"}( $value );
			} else {
				$this->data[ $prop ] = $value;
			}
		}
	}

	/**
	 * Left here for crowdfunding plugin.
	 * @todo Remove in crowdfunding version > 4.13
	 * @since version 5.4
	 * @param type $prop
	 */
	public function get_prop( $prop ) {
		return ($this->data[ $prop ] ?? null);
	}

	public function cache_get( $key ) {
		$data = wp_cache_get( $this->get_id(), 'nmgr_item' );
		return (false !== $data && isset( $data[ $key ] )) ? $data[ $key ] : false;
	}

	public function cache_set( $key, $value ) {
		$data = wp_cache_get( $this->get_id(), 'nmgr_item' );
		if ( false === $data ) {
			$data = [];
		}

		$data[ $key ] = $value;
		wp_cache_set( $this->get_id(), $data, 'nmgr_item' );
	}

	public function clear_cache() {
		wp_cache_delete( $this->get_id(), 'nmgr_item' );
	}

	public function clear_wishlist_cache() {
		$wishlist = nmgr()->wishlist();
		$wishlist->set_id( $this->get_wishlist_id() );
		$wishlist->clear_cache();
	}

}
