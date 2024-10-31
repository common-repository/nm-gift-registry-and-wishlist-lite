<?php

namespace NMGR\Fields;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class ItemStatusesFields extends Fields {

	protected $id = 'item_statuses';

	/**
	 * @var \NMGR\Tables\ItemsTable | \NMGR\Sub\Tables\ItemsTable
	 */
	public $table;

	public function __construct( $table ) {
		$this->table = $table;
		$this->set_data( $this->data() );
		$this->filter_showing();
		$this->filter_by_priority();
	}

	protected function data() {
		$item = $this->table->get_row_object();
		$product = $item->get_product();
		$data = [];

		$data[ 'item-purchased' ] = [
			'label' => nmgr()->is_pro ?
			__( 'Purchased', 'nm-gift-registry' ) :
			__( 'Purchased', 'nm-gift-registry-lite' ),
			'show_notice' => true,
			'show' => $item->is_purchased(),
		];

		$data[ 'item-fulfilled' ] = [
			'label' => nmgr()->is_pro ?
			__( 'Fulfilled', 'nm-gift-registry' ) :
			__( 'Fulfilled', 'nm-gift-registry-lite' ),
			'blur' => true,
			'show_notice' => true,
			'show' => $item->is_fulfilled(),
			'show_in_add_to_cart_column' => true,
			'add_to_cart_column_attributes' => [
				'class' => 'nmgr-tip',
				'title' => apply_filters( 'nmgr_item_fulfilled_notice',
					sprintf(
						/* translators: %s : wishlist type title */
						nmgr()->is_pro ? __( 'This item has been bought for the %s owner.', 'nm-gift-registry' ) : __( 'This item has been bought for the %s owner.', 'nm-gift-registry-lite' ),
						nmgr_get_type_title( '', 0, $this->table->wishlist->get_type() )
					)
				),
			],
		];

		$data[ 'item-archived' ] = [
			'label' => nmgr()->is_pro ?
			__( 'Archived', 'nm-gift-registry' ) :
			__( 'Archived', 'nm-gift-registry-lite' ),
			'blur' => true,
			'show' => method_exists( $item, 'is_archived' ) && $item->is_archived(),
			'show_notice' => true,
			'show_in_add_to_cart_column' => true,
		];

		$data[ 'out-of-stock' ] = [
			'label' => nmgr()->is_pro ?
			__( 'Out of stock', 'nm-gift-registry' ) :
			__( 'Out of stock', 'nm-gift-registry-lite' ),
			'blur' => true,
			'show' => !$product->is_in_stock(),
			'show_notice' => true,
			'show_in_add_to_cart_column' => true,
		];

		$data[ 'not-purchasable' ] = [
			'label' => nmgr()->is_pro ?
			__( 'Not purchasable', 'nm-gift-registry' ) :
			__( 'Not purchasable', 'nm-gift-registry-lite' ),
			'blur' => true,
			'show_notice' => true,
			'show' => !$product->is_purchasable(),
			'show_in_add_to_cart_column' => true,
		];

		return $data;
	}

}
