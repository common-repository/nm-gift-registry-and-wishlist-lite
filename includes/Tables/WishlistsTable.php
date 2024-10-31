<?php

namespace NMGR\Tables;

use NMGR\Tables\Table;
use NMGR\Fields\Fields;

class WishlistsTable extends Table {

	protected $id = 'wishlists';

	public function __construct( $wishlists ) {
		$fields = new Fields();
		$fields->set_id( $this->id );
		$fields->set_data( $this->data() );
		$fields->set_values( [ $this, 'column_value' ] );

		$this->set_data( $fields->get_data() );
		$this->set_rows_object( $wishlists );
	}

	protected function data() {
		return [
			'title' => [
				'label' => nmgr()->is_pro ?
				__( 'Title', 'nm-gift-registry' ) :
				__( 'Title', 'nm-gift-registry-lite' ),
			],
			'created' => [
				'label' => nmgr()->is_pro ?
				__( 'Created', 'nm-gift-registry' ) :
				__( 'Created', 'nm-gift-registry-lite' ),
			],
			'items' => [
				'label' => nmgr()->is_pro ?
				__( 'Items', 'nm-gift-registry' ) :
				__( 'Items', 'nm-gift-registry-lite' ),
			],
			'total' => [
				'label' => nmgr()->is_pro ?
				__( 'Total', 'nm-gift-registry' ) :
				__( 'Total', 'nm-gift-registry-lite' ),
			],
			'actions' => [
				'label' => nmgr()->is_pro ?
				__( 'Actions', 'nm-gift-registry' ) :
				__( 'Actions', 'nm-gift-registry-lite' ),
			],
		];
	}

	public function column_value() {
		$key = $this->get_cell_key();
		$wishlist = $this->get_row_object();

		ob_start();

		switch ( $key ) {
			case 'title':
				?>
				<a href="<?php echo esc_url( $wishlist->get_permalink() ); ?>">
					<?php echo esc_html( $wishlist->get_title() ); ?>
				</a>
				<?php
				break;
			case 'created':
				echo wp_kses_post( nmgr_format_date( $wishlist->get_date_created() ) );
				break;
			case 'items':
				echo absint( $wishlist->get_items_quantity_count() );
				break;
			case 'total':
				echo wp_kses_post( $wishlist->get_total( true ) );
				break;
			case 'actions':
				$dropdown_actions = [
					'view' => [
						'text' => nmgr()->is_pro ?
						__( 'View', 'nm-gift-registry' ) :
						__( 'View', 'nm-gift-registry-lite' ),
						'attributes' => [
							'class' => [
								'wishlists-action',
								'view'
							],
							'href' => $wishlist->get_permalink(),
						],
					],
				];

				$dropdown = nmgr_get_dropdown();

				foreach ( $dropdown_actions as $daction ) {
					$dropdown->set_menu_item(
						($daction[ 'text' ] ?? '' ),
						($daction[ 'attributes' ] ?? [] )
					);
				}

				echo $dropdown->get();
				break;
		}

		return ob_get_clean();
	}

}
