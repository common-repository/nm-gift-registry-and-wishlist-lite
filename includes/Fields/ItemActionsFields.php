<?php

namespace NMGR\Fields;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class ItemActionsFields extends Fields {

	protected $id = 'item_actions';

	/**
	 * @var \NMGR\Tables\ItemsTable | \NMGR\Sub\Tables\ItemsTable
	 */
	public $table;

	public function __construct( $table ) {
		$this->table = $table;
		$this->set_data( $this->data() );
		$this->filter_by_priority();
	}

	protected function data() {
		$item = $this->table->get_row_object();
		$item_id = $item ? $item->get_id() : 0;
		$is_gift_registry = $this->table->is_gift_registry;
		$item_has_archive_prop = $item && method_exists( $item, 'is_archived' );
		$item_is_archived = $item_has_archive_prop && $item->is_archived();
		$show_favourite = $is_gift_registry && nmgr()->is_pro &&
			nmgr_get_type_option( $this->table->wishlist->get_type(), 'display_item_favourite' );

		$data = [
			'delete' => [
				'text' => nmgr()->is_pro ?
				__( 'Delete', 'nm-gift-registry' ) :
				__( 'Delete', 'nm-gift-registry-lite' ),
				'priority' => 20,
				'attributes' => [
					'class' => [
						'delete-wishlist-item',
						'nmgr-post-action',
					],
					'href' => '#',
					'data-notice' => $item ? $this->get_delete_item_notice() : '',
					'data-nmgr_post_action' => 'delete_item',
					'data-wishlist_item_id' => $item_id,
				],
				'show' => !$item_is_archived && ($this->table->is_admin || (!$this->table->is_admin && $item && (!$item->is_fulfilled() &&
				!$item->is_purchased() ))),
				'show_in_bulk_actions' => true,
			],
			'purchase_refund' => [
				'text' => nmgr()->is_pro ?
				__( 'Update purchased quantity', 'nm-gift-registry' ) :
				__( 'Update purchased quantity', 'nm-gift-registry-lite' ),
				'priority' => 25,
				'attributes' => [
					'class' => [
						'purchase-refund-wishlist-item',
						'nmgr-post-action',
					],
					'href' => '#',
					'data-nmgr_post_action' => 'show_purchase_refund_item_dialog',
					'data-wishlist_item_id' => $item_id,
				],
				'show' => $is_gift_registry && $this->table->is_admin && !$item_is_archived,
			],
			'favourite' => [
				'text' => __( 'Toggle favourite status', 'nm-gift-registry' ),
				'priority' => 30,
				'attributes' => [
					'class' => [
						'favourite-wishlist-item',
						'nmgr-post-action',
					],
					'href' => '#',
					'data-nmgr_post_action' => 'toggle_item_favourite',
					'data-wishlist_item_id' => $item_id,
				],
				'show' => $is_gift_registry && $show_favourite && !$item_is_archived,
				'show_in_bulk_actions' => $is_gift_registry && $show_favourite,
			],
			'archive' => [
				'text' => __( 'Toggle archive status', 'nm-gift-registry' ),
				'priority' => 40,
				'attributes' => [
					'class' => [
						'archive-wishlist-item',
						'nmgr-post-action',
					],
					'data-archive_action' => 'archive',
					'data-nmgr_post_action' => 'toggle_item_archive',
					'data-wishlist_item_id' => $item_id,
					'href' => '#'
				],
				'show' => $is_gift_registry && $this->table->is_admin && $item_has_archive_prop,
			],
		];

		return $data;
	}

	protected function get_delete_item_notice() {
		$item = $this->table->get_row_object();

		return apply_filters( 'nmgr_delete_item_notice', sprintf(
				/* translators: %s: wishlist type title */
				nmgr()->is_pro ? __( 'Are you sure you want to remove the %s item?', 'nm-gift-registry' ) : __( 'Are you sure you want to remove the %s item?', 'nm-gift-registry-lite' ),
				nmgr_get_type_title( '', false, $this->table->wishlist->get_type() )
			), $item );
	}

}
