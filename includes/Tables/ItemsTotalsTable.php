<?php

namespace NMGR\Tables;

use NMGR\Tables\Table;
use NMGR\Fields\Fields;

class ItemsTotalsTable extends Table {

	public function __construct( Fields $fields ) {
		$this->set_id( $fields->get_id() );
		$this->set_rows_object( $fields->get_data() );

		/**
		 * Create a new fields object just to set common values for the data we need to use
		 * for the table cells
		 */
		$cells_fields = new Fields();
		$data = [
			'label' => [],
			'spacer' => [],
			'content' => [],
		];
		$cells_fields->set_data( $data );
		$cells_fields->set_values( [ $this, 'get_cell_value' ] );

		$this->set_data( $cells_fields->get_data() );
	}

	/**
	 *
	 * @param this $table
	 * @return type
	 */
	protected function get_cell_value( $table ) {
		$key = $table->get_cell_key();
		$row = $table->get_row_object();

		switch ( $key ) {
			case 'label':
				$content = $row[ 'label' ] ?? '';
				break;
			case 'spacer':
				$content = '';
				break;
			case 'content':
				$content = $row[ 'content' ] ?? '';
				break;
		}

		return $content;
	}

	protected function get_table_attributes() {
		$attributes = parent::get_table_attributes();
		$attributes[ 'class' ][] = 'total';

		if ( in_array( 'nmgr-table', $attributes[ 'class' ] ) ) {
			$key = array_search( 'nmgr-table', $attributes[ 'class' ] );
			unset( $attributes[ 'class' ][ $key ] );
		}

		return $attributes;
	}

	protected function get_head() {

	}

	protected function get_row_attributes() {
		$attributes = parent::get_row_attributes();
		$row = $this->get_row_object();
		$key = $this->row_key;

		$attributes[ 'class' ][] = 'nmgr-row';
		$attributes[ 'class' ][] = 'nmgr-row-' . $key;
		$attributes[ 'class' ] = array_merge( $attributes[ 'class' ], ($row[ 'class' ] ?? [] ) );

		return $attributes;
	}

	protected function get_cell_attributes( $key ) {
		$attributes = parent::get_cell_attributes( $key );

		switch ( $key ) {
			case 'spacer':
				$attributes[ 'width' ] = '1%';
				break;
		}

		return $attributes;
	}

}
