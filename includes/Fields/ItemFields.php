<?php

namespace NMGR\Fields;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class ItemFields extends Fields {

	protected $id = 'item';

	/**
	 * @var \NMGR\Tables\ItemsTable | \NMGR\Sub\Tables\ItemsTable
	 */
	public $table;

	public function __construct( $table ) {
		$this->table = $table;
		$this->set_data( $this->data() );
		$this->filter_by_priority();
		$this->set_values( [ $table, 'get_cell_value' ] );
	}

	protected function data() {
		$is_gift_registry = $this->table->is_gift_registry;
		$permitted = !$this->table->is_public;

		$quantity_label = nmgr()->is_pro ?
			__( 'Desired quantity', 'nm-gift-registry' ) :
			__( 'Desired Quantity', 'nm-gift-registry-lite' );

		$purchased_quantity_label = nmgr()->is_pro ?
			__( 'Purchased quantity', 'nm-gift-registry' ) :
			__( 'Purchased quantity', 'nm-gift-registry-lite' );

		$favourite_label = __( 'Favourite', 'nm-gift-registry' );

		$data = [
			'checkbox' => [
				'label' => nmgr()->is_pro ?
				__( 'Select item', 'nm-gift-registry' ) :
				__( 'Select item', 'nm-gift-registry-lite' ),
				'table_header_content' => '',
				'show_in_settings' => nmgr()->is_pro,
				'show' => nmgr()->is_pro,
				'priority' => 10,
			],
			'thumbnail' => [
				'label' => nmgr()->is_pro ?
				__( 'Thumbnail', 'nm-gift-registry' ) :
				__( 'Thumbnail', 'nm-gift-registry-lite' ),
				'table_header_content' => '',
				'show_in_settings' => true,
				'priority' => 20,
			],
			'title' => [
				'label' => nmgr()->is_pro ?
				__( 'Product', 'nm-gift-registry' ) :
				__( 'Product', 'nm-gift-registry-lite' ),
				'show_in_settings' => true,
				'priority' => 30,
				'orderby' => true,
			],
			'cost' => [
				'label' => nmgr()->is_pro ?
				__( 'Cost', 'nm-gift-registry' ) :
				__( 'Cost', 'nm-gift-registry-lite' ),
				'show_in_settings' => true,
				'priority' => 40,
				'orderby' => true,
			],
			'quantity' => [
				'label' => $quantity_label,
				'table_header_content' => '<span>' . nmgr_get_svg( array(
					'icon' => 'cart-empty',
					'size' => 1,
					'fill' => 'currentColor',
					'title' => $quantity_label
				) ) . '</span>',
				'show_in_settings' => $is_gift_registry,
				'show' => $is_gift_registry,
				'priority' => 50,
			],
			'purchased_quantity' => [
				'label' => $purchased_quantity_label,
				'table_header_content' => '<span>' . nmgr_get_svg( array(
					'icon' => 'cart-full',
					'size' => 1,
					'fill' => 'currentColor',
					'title' => $purchased_quantity_label
				) ) . '</span>',
				'show_in_settings' => $is_gift_registry,
				'show' => $is_gift_registry && $permitted,
				'priority' => 60,
				'orderby' => true,
			],
			'favourite' => [
				'label' => $favourite_label,
				'table_header_content' => '<span>' . nmgr_get_svg( array(
					'icon' => 'star-empty',
					'size' => 1,
					'fill' => 'currentColor',
					'class' => 'nmgr-cursor-help',
					'title' => $favourite_label
				) ) . '</span>',
				'show_in_settings' => $is_gift_registry && nmgr()->is_pro,
				'show' => $is_gift_registry && nmgr()->is_pro,
				'priority' => 70,
				'orderby' => true,
			],
			'total_cost' => [
				'label' => nmgr()->is_pro ?
				__( 'Total', 'nm-gift-registry' ) :
				__( 'Total', 'nm-gift-registry-lite' ),
				'show_in_settings' => $is_gift_registry,
				'show' => $is_gift_registry && $permitted,
				'priority' => 80,
				'orderby' => true,
			],
			'add_to_cart_button' => [
				'label' => nmgr()->is_pro ?
				__( 'Add to cart', 'nm-gift-registry' ) :
				__( 'Add to cart', 'nm-gift-registry-lite' ),
				'table_header_content' => '',
				'show_in_settings' => true,
				'show' => !$this->table->is_admin &&
				$this->table->wishlist &&
				!$this->table->wishlist->needs_shipping_address(),
				'priority' => 90,
			],
			'action_buttons' => [
				'label' => nmgr()->is_pro ?
				__( 'Actions', 'nm-gift-registry' ) :
				__( 'Actions', 'nm-gift-registry-lite' ),
				'table_header_content' => '<div>' . nmgr_get_svg( array(
					'icon' => 'gear',
					'size' => 1,
					'fill' => 'currentColor'
				) ) . '</div>',
				'priority' => 100,
				'show_in_settings' => true,
				'show' => $permitted,
			],
		];

		return $data;
	}

	public function filter_showing() {
		parent::filter_showing();

		if ( $this->table->is_gift_registry && ($this->table->is_admin || is_nmgr_wishlist()) ) {
			foreach ( $this->data as $key => $part ) {
				if ( true === ( bool ) ($part[ 'show_in_settings' ] ?? false) &&
					!nmgr_get_type_option( $this->table->wishlist->get_type(), "display_item_$key" ) ) {
					unset( $this->data[ $key ] );
				}
			}
		}
	}

}
