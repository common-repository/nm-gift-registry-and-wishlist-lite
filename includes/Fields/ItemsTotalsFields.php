<?php

namespace NMGR\Fields;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class ItemsTotalsFields extends Fields {

	protected $id = 'items_totals';

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
		$wishlist = $this->table->wishlist;
		$has_quantity_mismatch = $wishlist->has_quantity_mismatch();

		$total_title = sprintf(
			/* translators: %s: wishlist type title */
			nmgr()->is_pro ? __( 'The total cost of all the items in this %s.', 'nm-gift-registry' ) : __( 'The total cost of all the items in this %s.', 'nm-gift-registry-lite' ),
			nmgr_get_type_title()
		);

		if ( $has_quantity_mismatch ) {
			$total_title = nmgr()->is_pro ?
				__( 'The purchased quantity of some of the items is greater than the desired quantity. This may affect the totals calculations. Adjust the desired quantity to match the purchased quantity in order to correct this.', 'nm-gift-registry' ) :
				__( 'The purchased quantity of some of the items is greater than the desired quantity. This may affect the totals calculations. Adjust the desired quantity to match the purchased quantity in order to correct this.', 'nm-gift-registry-lite' );
		}

		$total_help_tip = nmgr_get_help_tip( $total_title );

		$rows = [
			'total' => [
				'priority' => 10,
				'label' => (nmgr()->is_pro ?
				__( 'Total', 'nm-gift-registry' ) :
				__( 'Total', 'nm-gift-registry-lite' )) .
				$total_help_tip . ' :',
				'content' => wc_price( $wishlist->get_total() ),
				'class' => [ $has_quantity_mismatch ? 'nmgr-settings-error' : '' ],
			],
			'amount_purchased' => [
				'priority' => 20,
				'label' => (nmgr()->is_pro ?
				__( 'Amount purchased', 'nm-gift-registry' ) :
				__( 'Amount purchased', 'nm-gift-registry-lite' )) .
				nmgr_get_help_tip( sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'The value of all the purchased items in this %s.', 'nm-gift-registry' ) : __( 'The value of all the purchased items in this %s.', 'nm-gift-registry-lite' ),
						nmgr_get_type_title()
					) ) . ' :',
				'content' => wc_price( $wishlist->get_total_purchased_amount() ),
				'show' => $wishlist->has_items(),
			],
			'amount_needed' => [
				'priority' => 30,
				'label' => (nmgr()->is_pro ?
				__( 'Amount needed', 'nm-gift-registry' ) :
				__( 'Amount needed', 'nm-gift-registry-lite' )) .
				nmgr_get_help_tip( sprintf(
						/* translators: %s: wishlist type title */
						nmgr()->is_pro ? __( 'The amount needed to completely purchase all the items in this %s.', 'nm-gift-registry' ) : __( 'The amount needed to completely purchase all the items in this %s.', 'nm-gift-registry-lite' ),
						nmgr_get_type_title()
					) ) . ' :',
				'content' => wc_price( $wishlist->get_total_unpurchased_amount() ),
				'show' => $wishlist->has_items(),
				'class' => [ 'nmgr-grey', 'nmgr-border-top' ],
			],
		];

		return $rows;
	}

}
