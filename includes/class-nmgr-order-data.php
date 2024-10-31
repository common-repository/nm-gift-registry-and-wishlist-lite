<?php

/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Order_Data {

	private $order_id;

	/**
	 * @var \WC_Order
	 */
	private $order;

	/**
	 * @param int|\WC_Order $order_id Order id or object
	 */
	public function __construct( $order_id = 0 ) {
		if ( is_a( $order_id, \WC_Order::class ) ) {
			$this->order = $order_id;
			$this->order_id = $order_id->get_id();
		} elseif ( $order_id ) {
			$this->order_id = $order_id;
			$this->order = wc_get_order( $order_id );
		}
	}

	/**
	 * @return WC_Order
	 */
	public function get_order() {
		return $this->order;
	}

	public function get_meta() {
		return $this->get_order() ? array_filter( ( array ) $this->get_order()->get_meta( 'nm_gift_registry' ) ) : [];
	}

	public function get_wishlist_ids() {
		$wishlist_ids = [];
		if ( $this->get_order() ) {
			foreach ( $this->get_order()->get_items() as $order_item ) {
				$wishlist_id = ( int ) $order_item->get_meta( 'nmgr_wishlist_id' );
				if ( $wishlist_id && nmgr_get_wishlist( $wishlist_id, true ) ) {
					$wishlist_ids[] = $wishlist_id;
				}
			}
		}
		return array_unique( $wishlist_ids );
	}

	public function get_wishlist_item_ids() {
		$wishlist_item_ids = [];
		if ( $this->get_order() ) {
			foreach ( $this->get_order()->get_items() as $order_item ) {
				$wishlist_item_id = ( int ) $order_item->get_meta( 'nmgr_item_id' );
				if ( $wishlist_item_id && nmgr_get_wishlist_item( $wishlist_item_id ) ) {
					$wishlist_item_ids[] = $wishlist_item_id;
				}
			}
		}
		return $wishlist_item_ids;
	}

	public function get_order_item_ids_for_wishlist( $wishlist_id ) {
		$item_ids = [];
		if ( $this->get_order() ) {
			foreach ( $this->get_order()->get_items() as $order_item ) {
				if ( ( int ) $order_item->get_meta( 'nmgr_wishlist_id' ) === ( int ) $wishlist_id ) {
					$item_ids[] = $order_item->get_id();
				}
			}
		}
		return $item_ids;
	}

	public function get_order_item_id_for_wishlist_item_id( $wishlist_item_id ) {
		$item_id = null;
		if ( $this->get_order() ) {
			foreach ( $this->get_order()->get_items() as $order_item ) {
				if ( ( int ) $order_item->get_meta( 'nmgr_item_id' ) === ( int ) $wishlist_item_id ) {
					$item_id = $order_item->get_id();
					break;
				}
			}
		}
		return $item_id;
	}

}
