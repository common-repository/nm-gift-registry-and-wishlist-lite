<?php
/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Order {

	public static function run() {
		add_filter( 'woocommerce_continue_shopping_redirect', array( __CLASS__, 'continue_shopping_redirect' ) );
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_wishlist_item_cart_item_data' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'maybe_add_wishlist_item_to_cart' ], 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', [ __CLASS__, 'maybe_update_cart_item_quantity' ], 10, 4 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'notify_if_cart_has_wishlist' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'show_wishlist_item_cart_item_data' ), 50, 2 );
		add_action( 'woocommerce_before_checkout_shipping_form', array( __CLASS__, 'notify_wishlist_shipping' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'add_order_item_meta' ], 10, 3 );
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'add_order_meta' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ __CLASS__, 'add_order_meta' ] );
		add_action( 'nmgr_created_order', [ __CLASS__, 'do_process_wishlist_item_payment' ] );
		add_action( 'woocommerce_pre_payment_complete', [ __CLASS__, 'do_process_wishlist_item_payment' ] );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'process_wishlist_item_payment' ), 10 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'process_wishlist_item_payment' ), 10 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'process_wishlist_item_payment' ), 10 );
		add_action( 'woocommerce_refund_deleted', [ __CLASS__, 'process_payment_for_deleted_refunds' ], 10, 2 );
		add_action( 'nmgr_order_payment_complete', [ __CLASS__, 'update_wishlist_item_purchased_quantity' ], 10 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart_items_for_shipping_address' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ __CLASS__, 'format_item_data' ], 10, 2 );
		add_action( 'woocommerce_before_single_product', [ __CLASS__, 'add_to_cart_from_product_page' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_custom_order_notice' ] );
		add_action( 'woocommerce_email_customer_details', [ __CLASS__, 'show_custom_order_notice_in_email' ], 30, 3 );
	}

	/**
	 * If the product was added to the cart from the wishlist page, return to the wishlist page
	 */
	public static function continue_shopping_redirect( $url ) {
		$item_data = self::get_add_to_cart_item_data();

		if ( empty( $item_data[ 'nmgr-add-to-cart-wishlist' ] ) ) {
			return $url;
		}

		$wishlist = nmgr_get_wishlist( $item_data[ 'nmgr-add-to-cart-wishlist' ] );
		return $wishlist ? $wishlist->get_permalink() : $url;
	}

	/**
	 * Get the data of the wishlist items we are adding to the cart from the request array
	 *
	 * This is necessary as the wishlist items can be added to the cart individually or in bulk.
	 * In each of these cases, the request array is different. But we need to make it consistent
	 * in order to apply the same functions to them. Hence this function.
	 * @return array
	 */
	public static function get_add_to_cart_item_data( $product_id = 0 ) {
		$item_data = array();

		if ( isset( $_REQUEST[ 'nmgr_add_to_cart_item_data' ] ) ) {
			$bulk_data = $_REQUEST[ 'nmgr_add_to_cart_item_data' ];
		} elseif ( isset( $_REQUEST[ 'nmgr_add_to_cart_item_data_string' ] ) ) {
			$bulk_data = json_decode( stripslashes( $_REQUEST[ 'nmgr_add_to_cart_item_data_string' ] ) );
		}

		if ( isset( $_REQUEST[ 'nmgr-add-to-cart-wishlist' ] ) ) {
			$item_data = $_REQUEST;
		} elseif ( isset( $bulk_data ) ) {
			foreach ( $bulk_data as $item_mixed ) {
				/**
				 * Cast to array as $item_mixed might be an object depending on the source
				 */
				$item = ( array ) $item_mixed;
				if ( $product_id && isset( $item[ 'nmgr-add-to-cart-product-id' ] ) && ( int ) $product_id === ( int ) $item[ 'nmgr-add-to-cart-product-id' ] ) {
					$item_data = $item;
					break;
				}
			}

			if ( empty( $item_data ) && isset( $_REQUEST[ 'items' ] ) ) {
				$item_data = reset( $_REQUEST[ 'items' ] );
			}
		}

		return apply_filters( 'nmgr_get_add_to_cart_item_data', $item_data, $product_id );
	}

	/**
	 * Add information about the wishlist to the cart item when it is added to the cart
	 */
	public static function add_wishlist_item_cart_item_data( $cart_item_data, $product_id ) {
		$item_data = self::get_add_to_cart_item_data( $product_id );

		if ( !empty( $item_data[ 'nmgr-add-to-cart-wishlist-item' ] ) ) {
			$wishlist_item = nmgr_get_wishlist_item( $item_data[ 'nmgr-add-to-cart-wishlist-item' ] );

			if ( !empty( $wishlist_item ) && $wishlist_item->get_wishlist()->is_type( 'gift-registry' ) ) {
				$cart_item_data[ 'nm_gift_registry' ] = $wishlist_item->get_cart_order_data();
			}
		}

		return $cart_item_data;
	}

	/**
	 * Add the wishlist item to the cart if the quantity being added is not more than the quantity requested in the wishlist
	 * This function is used typically on the wishlist page where a wishlist item is being added to the cart
	 *
	 * @param bool $passed Whether the validation passes or fails
	 * @param int $product_id Item product id
	 * @param int $quantity Item quantity being added to cart
	 * @return boolean Validation passed or failed
	 */
	public static function maybe_add_wishlist_item_to_cart( $passed, $product_id, $quantity ) {
		$item_data = self::get_add_to_cart_item_data( $product_id );

		if ( empty( $item_data ) ) {
			return $passed;
		}

		$wishlist_id = ( int ) $item_data[ 'nmgr-add-to-cart-wishlist' ];
		$wishlist_item_id = ( int ) $item_data[ 'nmgr-add-to-cart-wishlist-item' ];

		// check if the wishlist item is already in the cart
		$item_in_cart = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$nmgr_data = nmgr_get_cart_item_data( $cart_item, 'wishlist_item' );
			if ( $nmgr_data ) {
				if ( ( int ) $nmgr_data[ 'wishlist_id' ] === $wishlist_id &&
					( int ) $nmgr_data[ 'wishlist_item_id' ] === $wishlist_item_id ) {
					$item_in_cart = $cart_item;
					break;
				}
			}
		}

		return self::validate_wishlist_item_cart_quantity( $passed, $wishlist_item_id, $quantity, $item_in_cart );
	}

	/**
	 * Update the wishlist item in the cart if the quantity being added doesn't exceed the quantity requested in the wishlist
	 * This function is used mainly on the cart page where only the item quantity is updated
	 *
	 * @param bool $passed Whether the validation passes or fails
	 * @param string $cart_item_key Unique id of the cart item
	 * @param array $values Cart item properties
	 * @param int $quantity Item quantity being added to cart
	 * @return boolean Validation passed or failed
	 */
	public static function maybe_update_cart_item_quantity( $passed, $cart_item_key, $values, $quantity ) {
		$nmgr_data = nmgr_get_cart_item_data( $values, 'wishlist_item' );

		if ( $nmgr_data ) {
			$passed = self::validate_wishlist_item_cart_quantity( $passed, $nmgr_data[ 'wishlist_item_id' ], $quantity );
		}

		return $passed;
	}

	/**
	 * Validate the quantity of a wishlist item added to the cart based on its existing cart quantity if present and desired quantity
	 *
	 * @param bool $passed Whether the validation passes or fails
	 * @param int $wishlist_item_id The item id in the wishlist
	 * @param int $quantity The quantity of the item being added
	 * @param array $item_in_cart The item contents if the item is already in the cart
	 * @return boolean Validation passed or failed
	 */
	private static function validate_wishlist_item_cart_quantity( $passed, $wishlist_item_id, $quantity, $item_in_cart = false ) {
		$item = nmgr_get_wishlist_item( $wishlist_item_id );

		if ( $item && 'gift-registry' === $item->get_wishlist_type() ) {
			$item_title = sprintf(
				/* translators: %s: item name */
				nmgr()->is_pro ? _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'nm-gift-registry' ) : _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'nm-gift-registry-lite' ),
				$item->get_product()->get_name()
			);

			$desired_item_qty = $item->get_unpurchased_quantity();

			if ( $item_in_cart ) {
				$item_cart_quantity = $item_in_cart[ 'quantity' ]; // the current quantity of the item in the cart

				if ( ($quantity + $item_cart_quantity) > $desired_item_qty ) {
					$passed = false;
					$message = sprintf(
						/* translators:
						 * 1: item quantity to add to cart,
						 * 2: item title,
						 * 3: item quantity in cart,
						 * 4: item quantity requested in wishlist,
						 * 5: wishlist type title
						 */
						nmgr()->is_pro ? __( 'You cannot add %1$d of %2$s to the cart as you have %3$d already in the cart with %4$d requested in the %5$s.', 'nm-gift-registry' ) : __( 'You cannot add %1$d of %2$s to the cart as you have %3$d already in the cart with %4$d requested in the %5$s.', 'nm-gift-registry-lite' ),
						$quantity,
						$item_title,
						$item_cart_quantity,
						$desired_item_qty,
						nmgr_get_type_title( '', false, 'gift-registry' )
					);

					wc_add_notice( $message, 'error' );
				}
			} else {
				if ( $quantity > $desired_item_qty ) {
					$passed = false;
					$message = sprintf(
						/* translators: 1: item title, 2: wishlist type title */
						nmgr()->is_pro ? __( 'Please choose a quantity of %1$s that is not greater than the quantity requested in the %2$s.', 'nm-gift-registry' ) : __( 'Please choose a quantity of %1$s that is not greater than the quantity requested in the %2$s.', 'nm-gift-registry-lite' ),
						$item_title,
						nmgr_get_type_title( '', false, 'gift-registry' )
					);

					wc_add_notice( $message, 'error' );
				}
			}
		}
		return $passed;
	}

	/**
	 * Add a simple notice on the cart page to notify that the cart contains at least one wishlist
	 */
	public static function notify_if_cart_has_wishlist() {
		$cart_has_wishlist = nmgr_get_wishlist_in_cart();
		$message = '';

		if ( nmgr()->is_pro &&
			function_exists( 'is_nmgr_cart_shipping_to_wishlist_address' ) &&
			is_nmgr_cart_shipping_to_wishlist_address() ) {
			$message = sprintf(
				/* translators: 1, 2: wishlist type title */
				__( 'You have %1$s items in your cart. Please note that all items in the cart are shipping to the address of the %s owner.', 'nm-gift-registry' ),
				nmgr_get_type_title(),
				nmgr_get_type_title()
			);
		} elseif ( nmgr()->is_pro && $cart_has_wishlist && nmgr_get_option( 'shipping_calculate' ) ) {
			$message = sprintf(
				/* translators: %s: wishlist type title */
				__( 'You have %s items in your cart. Please note that shipping for these items is set up separately.', 'nm-gift-registry' ),
				nmgr_get_type_title()
			);
		} elseif ( $cart_has_wishlist ) {
			$message = sprintf(
				/* translators: 1, 2: wishlist type title */
				nmgr()->is_pro ? __( 'You have %1$s items in your cart.', 'nm-gift-registry' ) : __( 'You have %1$s items in your cart.', 'nm-gift-registry-lite' ),
				nmgr_get_type_title()
			);
		}

		$notice = apply_filters( 'nmgr_cart_has_wishlist_notice', $message );

		if ( $notice ) {
			wc_print_notice( $notice, 'notice' );
		}
	}

	/**
	 * Display information about the associated wishlist for an item in the cart
	 *
	 * @param array $item_data key value pair of Information to display
	 * @param array $cart_item_data Cart item data
	 * @return array $item_data
	 */
	public static function show_wishlist_item_cart_item_data( $item_data, $cart_item_data ) {
		$nmgr_data = nmgr_get_cart_item_data( $cart_item_data, 'wishlist_item' );

		if ( !$nmgr_data || empty( $nmgr_data ) || empty( array_filter( $nmgr_data ) ) ) {
			return $item_data;
		}

		$wishlist = nmgr_get_wishlist( $nmgr_data[ 'wishlist_id' ], true );

		if ( !$wishlist ) {
			return $item_data;
		}

		$item = nmgr_get_wishlist_item( $nmgr_data[ 'wishlist_item_id' ] );

		if ( !$item ) {
			return $item_data;
		}

		$title = sprintf(
			/* translators: %s: wishlist type title */
			nmgr()->is_pro ? __( 'You are buying this item for this %s', 'nm-gift-registry' ) : __( 'You are buying this item for this %s', 'nm-gift-registry-lite' ),
			esc_html( nmgr_get_type_title() )
		);
		$data = array(
			'key' => sprintf(
				/* translators: %s: wishlist type title */
				nmgr()->is_pro ? __( 'For %s', 'nm-gift-registry' ) : __( 'For %s', 'nm-gift-registry-lite' ),
				esc_html( nmgr_get_type_title() )
			),
			'value' => nmgr_get_wishlist_link( $wishlist, array( 'title' => $title ) ),
			'display' => '',
		);
		$item_data[] = apply_filters( 'nmgr_get_item_data_content', $data, $item, $wishlist );

		return $item_data;
	}

	/**
	 * Displays simple notice on checkout page shipping section concerning the wishlist's shipping details
	 */
	public static function notify_wishlist_shipping() {
		$cart_wishlist_ids = nmgr()->is_pro ?
			is_nmgr_cart_shipping_to_wishlist_address( false ) :
			nmgr_get_wishlist_in_cart();

		if ( !empty( $cart_wishlist_ids ) ) {
			$default_msg = '';

			if ( nmgr()->is_pro ) {
				/* translators: %1$s: wishlist type title, %2$s: wishlist type title */
				$default_msg = sprintf( __( 'The items in your cart are for a %1$s and they would be shipped separately to the address of the %2$s owner.', 'nm-gift-registry' ),
					esc_html( nmgr_get_type_title() ),
					esc_html( nmgr_get_type_title() )
				);
			}

			$msg = apply_filters( 'nmgr_checkout_shipping_message', $default_msg );

			if ( $msg ) {
				wc_print_notice( $msg, 'notice' );
			}

			if ( nmgr()->is_pro ) {
				foreach ( $cart_wishlist_ids as $id ) {
					$wishlist = nmgr_get_wishlist( $id, true );

					if ( $wishlist ) {
						echo '<p>' . nmgr_get_wishlist_link( $wishlist ) . '<br>' .
						wc()->countries->get_formatted_address( $wishlist->get_shipping() ) . '</p>';
					}
				}
			}
		}
	}

	/**
	 * Add a wishlist's data as order itemmeta
	 */
	public static function add_order_item_meta( $item, $cart_item_key, $values ) {
		$nmgr_data = nmgr_get_cart_item_data( $values, 'wishlist_item' );
		if ( !empty( $nmgr_data[ 'wishlist_item_id' ] ) ) {
			$wishlist_item = nmgr_get_wishlist_item( $nmgr_data[ 'wishlist_item_id' ] );
			if ( $wishlist_item ) {
				$wishlist_item->add_order_item_meta( $item );
				$item->save();
			}
		}
	}

	/**
	 * Add data concerning the wishlists in the order as order meta data
	 * once after the order has been saved
	 *
	 * This information would serve as the main data store from which we can
	 * perform gift registry related wishlist actions on the order later
	 *
	 * @param WC_Order $order The order object
	 */
	public static function add_order_meta( $order ) {
		nmgr()->order()->add_meta_data( $order );
	}

	/**
	 * When a refund has been deleted, process payments for the wishlist items in the order
	 */
	public static function process_payment_for_deleted_refunds( $refund_id, $order_id ) {
		self::process_wishlist_item_payment( $order_id );
	}

	/**
	 * Process payments for the wishlist items in an order
	 *
	 * This is the main entry point for actions relating to nm gift registry for WooCommerce orders.
	 *
	 * This function determines if we have any wishlist items in the order, if so it gets their data in the order
	 * and sets up some actions, based on whether the order is paid or refunded,
	 * that we can use to simplify the subsequent processing of these items in the order
	 *
	 * @param int $order_id The order id
	 */
	public static function process_wishlist_item_payment( $order_id ) {
		$order_object = new NMGR_Order_Data( $order_id );
		$order = $order_object->get_order();

		if ( !$order ) {
			return;
		}

		$order_wishlist_data = $order_object->get_meta();

		if ( !$order_wishlist_data && false === apply_filters( 'nmgr_do_order_payment_actions', false, $order ) ) {
			return;
		}

		/**
		 * At this point we confirm that there are wishlist items in the order
		 * so we can set up our actions
		 */
		$payment_cancelled_statuses = nmgr_get_payment_cancelled_order_statuses();
		$process_wishlist_item_payment_status = apply_filters(
			'nmgr_do_order_payment_complete',
			($order->get_date_paid() &&
			($order->is_paid() ||
			$order->has_status( $payment_cancelled_statuses ) ||
			doing_action( 'woocommerce_order_refunded' ) ||
			doing_action( 'woocommerce_refund_deleted' )
			) ),
			$order
		);

		if ( $process_wishlist_item_payment_status ) {
			do_action( 'nmgr_order_payment_complete', $order_id, $order_wishlist_data, $order );
		} else {
			do_action( 'nmgr_order_payment_incomplete', $order_id, $order_wishlist_data, $order );
		}
	}

	/**
	 * Update the purchased quantity of a wishlist item based on order status or action
	 *
	 * This function only runs if the 'purchased quantity' column is visible on the items table
	 * and if the order is paid for or refunded, that is typically on these statuses:
	 * - processing, completed, refunded, cancelled.
	 *
	 * Any other statuses of the order would not trigger an update of the wishlist item's purchased quantity.
	 * This is because the purchased quantity of a wishlist item is meant to be updated, as in the real shopping sense,
	 * only when an order has been paid for or refunded.
	 */
	public static function update_wishlist_item_purchased_quantity( $order_id ) {
		$order_data = new NMGR_Order_Data( $order_id );
		$order_wishlist_item_ids = $order_data->get_wishlist_item_ids();
		$refunded_items = [];

		foreach ( $order_wishlist_item_ids as $wishlist_item_id ) {
			$wishlist_item = nmgr_get_wishlist_item( $wishlist_item_id );
			$order_item_ids = $wishlist_item->get_paid_order_item_ids();

			$total_ordered_qty = $total_refunded_qty = 0;

			foreach ( $order_item_ids as $order_item_id ) {
				$order = wc_get_order( wc_get_order_id_by_order_item_id( $order_item_id ) );
				$order_item = $order->get_item( $order_item_id );
				$total_ordered_qty += $order_item->get_quantity();
				$total_refunded_qty += absint( $order->get_qty_refunded_for_item( $order_item_id ) );
			}

			$total_purchased_qty = ( int ) ($total_ordered_qty - $total_refunded_qty);
			$current_purchased_qty = $wishlist_item->get_purchased_quantity();

			if ( $total_purchased_qty !== $current_purchased_qty ) {
				$wishlist_item->set_purchased_quantity( $total_purchased_qty );

				// Item purchase log should always be updated after updating the purchased quantity
				$qty_diff = $total_purchased_qty - $current_purchased_qty;
				$wishlist_item->add_purchase_log( $qty_diff, 'order', [ 'order_id' => $order_id ] );
				$wishlist_item->save();

				// Refund
				if ( $total_purchased_qty < $current_purchased_qty ) {
					$refunded_items[ $wishlist_item->get_id() ] = $current_purchased_qty - $total_purchased_qty;
				}
			}
		}

		/**
		 * If we have items in the refunded_items array, set up the refund action.
		 */
		if ( !empty( $refunded_items ) ) {
			if ( has_action( 'nmgr_order_items_refunded' ) ) {
				$order = wc_get_order( $order_id );
				do_action_deprecated( 'nmgr_order_items_refunded',
					[ $refunded_items, $order_id, $order_data->get_meta(), $order ],
					'5.3.0',
					'nmgr_order_refunded_items'
				);
			}
			do_action( 'nmgr_order_refunded_items', $order_id );
		}
	}

	/**
	 * Make sure the wishlist data in the order matches the data in the wishlist itself
	 *
	 * This function is used on the following hooks:
	 * - nmgr_created_order: To update the ordered quantity for a wishlist
	 * item when an order is created
	 * - woocommerce_pre_payment_complete: To update the quantity reference when an order has
	 * been paid for. It is used on this hook as a precaution to make sure the update happens
	 * even though the hook 'woocommerce_payment_complete' also updates the quantity reference.
	 * -
	 */
	public static function do_process_wishlist_item_payment( $order_id ) {
		self::process_wishlist_item_payment( $order_id );
	}

	/**
	 * Remove wishlist items from the cart if their wishlist shipping address is
	 * required but not properly filled.
	 */
	public static function check_cart_items_for_shipping_address() {
		foreach ( wc()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$nmgr_data = nmgr_get_cart_item_data( $cart_item, 'wishlist_item' );
			if ( $nmgr_data ) {
				$wishlist = nmgr_get_wishlist( $nmgr_data[ 'wishlist_id' ] );
				if ( $wishlist && $wishlist->needs_shipping_address() ) {
					wc()->cart->set_quantity( $cart_item_key, 0 );
					$message = sprintf(
						/* translators:
						 * 1: wishlist type title,
						 * 2: cart item name,
						 * 3: wishlist type title,
						 * 4: wishlist title
						 */
						nmgr()->is_pro ? __( 'The %1$s item %2$s has been removed from your cart as the shipping addresss for the %3$s %4$s is needed.', 'nm-gift-registry' ) : __( 'The %1$s item %2$s has been removed from your cart as the shipping addresss for the %3$s %4$s is needed.', 'nm-gift-registry-lite' ),
						esc_html( nmgr_get_type_title() ),
						'<strong>' . wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $cart_item[ 'data' ]->get_name(), $cart_item, $cart_item_key ) ) . '</strong>',
						esc_html( nmgr_get_type_title() ),
						'<strong>' . esc_html( $wishlist->get_title() ) . '</strong>'
					);
					wc_add_notice( $message, 'error' );
				}
			}
		}
	}

	/**
	 * Show a button in the edit order screen to allow syncing of order wishlist data
	 * if the order has wishlist items.
	 *
	 * This function provides a last attempt to sync order wishlist data with
	 * stored wishlist data in situation where the standard methods have failed.
	 *
	 * The function has to be enabled manually by hooking into wordpress. E.g.
	 * add_action( 'woocommerce_order_actions_end', [ __CLASS__, 'sync_order_wishlists_metabox' ] );
	 *
	 * If enabled, the other method of this class 'manually_update_order_wishlist_data()'
	 * should also be enabled
	 *
	 */
	public static function sync_order_wishlists_metabox( $order_id ) {
		$order = wc_get_order( $order_id );
		$meta = $order->get_meta( 'nm_gift_registry' );

		if ( !empty( $meta ) ) {
			if ( nmgr()->is_pro ) {
				$text = __( 'You have wishlist items in this order. Click this button to manually update these wishlists to reflect their status in this order. It is only necessary to do this if you notice that the wishlist information in this order does not match the data in the wishlist itself.', 'nm-gift-registry' );
			} else {
				$text = __( 'You have wishlist items in this order. Click this button to manually update these wishlists to reflect their status in this order. It is only necessary to do this if you notice that the wishlist information in this order does not match the data in the wishlist itself.', 'nm-gift-registry-lite' );
			}

			echo '<li><button type="submit" name="nmgr_sync_order" value="' . esc_attr( $order_id ) . '" class="button button-primary nmgr-tip" title="' . esc_attr( $text ) . '">' . esc_html( nmgr()->is_pro ?
					__( 'Sync wishlist data', 'nm-gift-registry' ) :
					__( 'Sync wishlist data', 'nm-gift-registry-lite' ) ) . '</button></li>';
		}
	}

	/**
	 * Sync order wishlist data with stored wishlist data
	 *
	 * This function checks if the order id is in the $_POST variable 'nmgr_sync_order' and
	 * performs the sync if so.
	 *
	 * It has to be manually enabled by hooking into wordpress. E.g.
	 * add_action( 'wp_loaded', [ __CLASS__, 'manually_update_order_wishlist_data' ] );
	 *
	 * It is typically enabled with the other method of this class 'sync_order_wishlists_metabox()'
	 */
	public static function manually_update_order_wishlist_data() {
		if ( !empty( $_POST[ 'nmgr_sync_order' ] ) ) {
			nmgr()->order()->update_wishlist_item_purchased_quantity( ( int ) $_POST[ 'nmgr_sync_order' ] );
		}
	}

	/**
	 * Show the wishlist title as item meta data whenever the item meta data is displayed
	 */
	public static function format_item_data( $meta_array, $item ) {
		foreach ( $meta_array as $id => $meta ) {
			if ( 'nmgr_wishlist_id' === $meta->key ) {
				$wishlist = nmgr_get_wishlist( $meta->value, true );

				if ( $wishlist ) {
					$title = sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'This item is bought for this %s', 'nm-gift-registry' ) : __( 'This item is bought for this %s', 'nm-gift-registry-lite' ),
						nmgr_get_type_title()
					);

					$show_link = true;
					if ( is_admin() ) {
						$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
						if ( !$screen ||
							($screen && !in_array( $screen->id,
								[ 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ] )
							) ) {
							$show_link = false;
						}
					}

					$meta_array[ $id ]->display_key = nmgr_get_type_title( 'c' );
					$meta_array[ $id ]->display_value = $show_link ?
						nmgr_get_wishlist_link( $wishlist, [ 'title' => $title ] ) :
						$wishlist->get_title();
				}
			}

			if ( in_array( $meta->key, [ 'nmgr_item_id' ] ) ) {
				unset( $meta_array[ $id ] );
			}

			// pro version
			if ( 'nmgr_shipping_address' === $meta->key ) {
				$meta_array[ $id ]->display_key = __( 'Shipping address', 'nm-gift-registry' );
			}
		}

		return $meta_array;
	}

	public static function add_to_cart_from_product_page() {
		$item = nmgr_get_wishlist_item( ( int ) ($_GET[ 'nmgr_item_id' ] ?? 0) );

		if ( !$item ) {
			return;
		}

		$wishlist = $item->get_wishlist();
		$table = nmgr()->items_table( $wishlist );
		$table->set_row_object( $item );
		$button = $table->get_item_add_to_cart_button();

		$message = sprintf(
			/* translators: 1: product name, 2: wishlist type title, 3: wishlist title */
			nmgr()->is_pro ? __( 'Purchase %1$s for the %2$s %3$s.', 'nm-gift-registry' ) : __( 'Purchase %1$s for the %2$s %3$s.', 'nm-gift-registry-lite' ),
			'<strong>' . $item->get_product()->get_name() . '</strong>',
			nmgr_get_type_title(),
			'<strong>' . $wishlist->get_title() . '</strong>'
		);
		?>
		<style>
			.nmgr-atc-product-page {
				background-color: #f8f8f8;
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin: 20px 0;
				padding: 20px;
			}
		</style>
		<div class="nmgr-atc-product-page">
			<div class="nmgr-atc-product-page-msg">
				<?php echo wp_kses_post( $message ); ?>
			</div>
			<div class="nmgr-atc-product-page-btn">
				<?php echo $button; ?>
			</div>
		</div>
		<?php
	}

	public static function show_custom_order_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ] ) &&
			'edit' === ( $_GET[ 'action' ] ?? '' ) ) {
			$id = $_GET[ 'id' ] ?? ($_GET[ 'post' ] ?? 0);
			$order = $id ? wc_get_order( ( int ) $id ) : false;
			if ( $order && $order->is_created_via( 'nmgr_wishlist' ) ) {
				echo '<div class="notice-info notice"><p>' . wp_kses_post( nmgr_get_custom_order_notice() ) . '</p></div>';
			}
		}
	}

	public static function show_custom_order_notice_in_email( $order, $sent_to_admin, $plain_text ) {
		if ( $order && $order->is_created_via( 'nmgr_wishlist' ) ) {
			$notice = nmgr_get_custom_order_notice();
			echo $plain_text ? (esc_html( $notice ) . "\n\n") : ('<p>' . esc_html( $notice ) . '</p>');
		}
	}

	/**
	 * Get variation attributes posted by a form
	 *
	 * @param int $variation_id Id of the variation product
	 * @param array $post posted data contaIning variations
	 * @return array
	 */
	public static function get_posted_variations( $variation_id, $post = '' ) {
		$variations = array();
		$posted_data = array();

		if ( !$variation_id ) {
			return $variations;
		}

		if ( empty( $post ) ) {
			$posted_data = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( is_string( $post ) ) {
			parse_str( $post, $posted_data );
		} elseif ( is_array( $post ) ) {
			$posted_data = $post;
		}

		$product = wc_get_product( $variation_id );
		$product_parent_id = $product->get_parent_id();
		$product_parent = wc_get_product( $product_parent_id );

		if ( !$product_parent ) {
			return $variations;
		}

		foreach ( $posted_data as $key => $value ) {
			if ( false === strpos( $key, 'attribute_' ) ) {
				unset( $posted_data[ $key ] );
			}
		}

		foreach ( $product_parent->get_attributes() as $attribute ) {
			if ( !$attribute[ 'is_variation' ] ) {
				continue;
			}
			$attribute_key = 'attribute_' . sanitize_title( $attribute[ 'name' ] );

			if ( isset( $posted_data[ $attribute_key ] ) ) {
				if ( $attribute[ 'is_taxonomy' ] ) {
					$value = sanitize_title( wp_unslash( $posted_data[ $attribute_key ] ) );
				} else {
					$value = html_entity_decode( wc_clean( wp_unslash( $posted_data[ $attribute_key ] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
				}
				$variations[ $attribute_key ] = $value;
			}
		}

		return $variations;
	}

	/**
	 * The core action used to add wishlist products to the cart via http or ajax
	 *
	 * @param array $items_data Array of data of each item to be added to the cart
	 * @return array $data Array of data for each item that was supplied to be added to the cart
	 */
	public static function add_to_cart( $items_data ) {

		/**
		 * Destroy the add to cart item reference data just in case it exists.
		 * We start afresh as we are starting a new add to cart action.
		 */
		if ( wp_doing_ajax() && isset( $GLOBALS[ 'nmgr_add_to_cart_ref_data' ] ) ) {
			unset( $GLOBALS[ 'nmgr_add_to_cart_ref_data' ] );
		}

		/**
		 * Enable custom add to cart action.
		 * Allow add to cart items data to be modified
		 *
		 * 'do_action_ref_array' is used rather than 'do_action'
		 * because it maintains the state of $items_data
		 * which may be an array of objects.
		 */
		do_action_ref_array( 'nmgr_add_to_cart_action', array( &$items_data ) );

		foreach ( ( array ) $items_data as $item_mixed ) {
			/**
			 * $item_mixed may be an object typically if adding to cart in bulk
			 * so we cast to array
			 */
			$item = ( array ) $item_mixed;

			if ( !isset( $item[ 'nmgr-add-to-cart-product-id' ] ) ) {
				continue;
			}

			$product_id = ( int ) $item[ 'nmgr-add-to-cart-product-id' ];
			$quantity = empty( $item[ 'quantity' ] ) ? 1 : wc_stock_amount( wp_unslash( $item[ 'quantity' ] ) );
			$variation_id = isset( $item[ 'variation_id' ] ) ? ( int ) $item[ 'variation_id' ] : 0;
			$variation = nmgr()->order()->get_posted_variations( $variation_id, $item );
			$wishlist_id = ( int ) ($item[ 'nmgr-add-to-cart-wishlist' ] ?? 0);
			$wishlist_item_id = ( int ) ($item[ 'nmgr-add-to-cart-wishlist-item' ] ?? 0);
			$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

			if ( $passed_validation && is_a( wc()->cart, 'WC_Cart' ) &&
				false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) ) {
				// wc filter (check this filter on updates)

				if ( wp_doing_ajax() ) {
					do_action( 'woocommerce_ajax_added_to_cart', $product_id );
				}

				wc_add_to_cart_message( array( $product_id => $quantity ), true );
			}

			if ( wp_doing_ajax() ) {
				$data = array(
					'product_id' => $product_id,
					'quantity' => $quantity,
					'wishlist_id' => $wishlist_id,
					'wishlist_item_id' => $wishlist_item_id,
				);
				nmgr()->order()->add_add_to_cart_item_ref_data( $data );
			}
		}

		/**
		 * Return the reference data for all items that have been added to the cart.
		 *
		 * Typically used when adding to car via ajax if dom elements related
		 * to the item being added need to be updated afterwards
		 */
		return isset( $GLOBALS[ 'nmgr_add_to_cart_ref_data' ] ) ? $GLOBALS[ 'nmgr_add_to_cart_ref_data' ] : '';
	}

	/**
	 * Add reference details of an item that has been added to the cart
	 * to the global reference array
	 *
	 * Typically used when adding to car via ajax if dom elements related
	 * to the item being added need to be updated afterwards
	 *
	 * @param array $data
	 */
	public static function add_add_to_cart_item_ref_data( $data ) {
		if ( isset( $GLOBALS[ 'nmgr_add_to_cart_ref_data' ] ) ) {
			$GLOBALS[ 'nmgr_add_to_cart_ref_data' ][] = $data;
		} else {
			$GLOBALS[ 'nmgr_add_to_cart_ref_data' ] = array( $data );
		}
	}

	/**
	 * Add data concerning the wishlists in the order as order meta data
	 *
	 * This should typically be done once immediately after the order has been created.
	 * This information would serve as the main data store from which we can
	 * perform gift registry related wishlist actions on the order later
	 *
	 * @param WC_Order $created_order The order object
	 */
	public static function add_meta_data( $order ) {
		$wishlist_items_data = array();

		if ( !$order || $order->meta_exists( 'nm_gift_registry' ) ) {
			return;
		}

		foreach ( $order->get_items() as $order_item ) {
			$wishlist_item_id_for_order_item = $order_item->get_meta( 'nmgr_item_id' );

			if ( $wishlist_item_id_for_order_item ) {
				$wishlist_item = nmgr_get_wishlist_item( $wishlist_item_id_for_order_item );

				if ( !$wishlist_item ) {
					continue;
				}

				$arr = array(
					'wishlist_item_id' => $wishlist_item_id_for_order_item, // wishlist_item_id
					'order_item_quantity' => $order_item->get_quantity(), // quantity of item ordered
					'order_item_id' => $order_item->get_id(), // order item id
					'wishlist_id' => $wishlist_item->get_wishlist_id(), // id of wishlist the item belongs to
				);
				$wishlist_items_data[] = $arr;
			}
		}

		if ( !empty( $wishlist_items_data ) ) {
			$order_meta = array();
			foreach ( $wishlist_items_data as $data ) {
				$order_meta[ $data[ 'wishlist_id' ] ][ 'wishlist_id' ] = $data[ 'wishlist_id' ];
				$order_meta[ $data[ 'wishlist_id' ] ][ 'wishlist_item_ids' ][] = $data[ 'wishlist_item_id' ];
				$order_meta[ $data[ 'wishlist_id' ] ][ 'order_item_ids' ][ $data[ 'wishlist_item_id' ] ] = $data[ 'order_item_id' ];
				$order_meta[ $data[ 'wishlist_id' ] ][ 'sent_customer_purchased_items_email' ] = 'no';

				if ( nmgr()->is_pro && function_exists( 'is_nmgr_cart_shipping_to_wishlist_address' ) ) {
					$shipping_to_wishlist_address = is_nmgr_cart_shipping_to_wishlist_address() ? 'yes' : 'no';
					$order_meta[ $data[ 'wishlist_id' ] ][ 'shipping_to_wishlist_address' ] = $shipping_to_wishlist_address;
				}
			}

			$order->add_meta_data( 'nm_gift_registry', $order_meta );
			$order->save();
			do_action( 'nmgr_created_order', $order->get_id() );
		}
	}

}
