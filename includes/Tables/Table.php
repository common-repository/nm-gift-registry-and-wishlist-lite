<?php

namespace NMGR\Tables;

defined( 'ABSPATH' ) || exit;

class Table {

	protected $id;
	protected $args;
	protected $data = [];
	protected $row_key;
	protected $cell_key;
	protected $cell_tag = 'td';
	protected $row_tag = 'tr';
	protected $body_tag = 'tbody';
	protected $header_tag = 'th';
	protected $head_tag = 'thead';
	protected $table_tag = 'table';
	protected $table_attributes = [];
	protected $rows_object = []; // Items to create table rows for
	protected $row_object;
	protected $head = true;
	protected $pagination_args = [];
	protected $order;
	protected $orderby;
	protected $page = 1; // The current page to display
	protected $items_per_page;

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_html_id() {
		return 'nmgr_table_' . $this->id;
	}

	public function set_args( $args ) {
		$this->args = $args;
	}

	public function get_args() {
		return $this->args;
	}

	public function set_order( $order ) {
		$this->order = $order;
	}

	public function set_orderby( $orderby ) {
		$this->orderby = $orderby;
	}

	/**
	 * Set the data used to compose the table cells.
	 * This data should be an array with each key representing each cell key.
	 * @param array $data
	 */
	public function set_data( $data ) {
		$this->data = $data;

		/**
		 * Allow users to set default table properties here.
		 * We're doing this in the set_data() method because this method is called once for every instantiated table
		 * and the table id is already set by the time the method is called. The table id is needed for the filter
		 * to target a specific table.
		 * Maybe in the future we can find a better place (maybe constructor) to set the default table properties.
		 */
		$props = apply_filters( 'nmgr_table_default_props', [], $this );
		if ( $props ) {
			foreach ( $props as $prop => $value ) {
				if ( property_exists( $this, $prop ) ) {
					if ( method_exists( $this, "set_{$prop}" ) ) {
						$this->{"set_$prop"}( $value );
					} else {
						$this->{$prop} = $value;
					}
				}
			}
		}
	}

	public function get_data() {
		return $this->data;
	}

	public function set_attributes( string $key, array $attributes ) {
		$prop = $key . '_attributes';
		$this->{$prop} = array_merge_recursive( $this->{$prop}, $attributes );
	}

	public function set_table_attributes( $attributes ) {
		$this->set_attributes( 'table', $attributes );
	}

	/**
	 * Whether to show the header
	 * @param boolean $bool
	 */
	public function set_head( $bool ) {
		$this->head = $bool;
	}

	public function get_cell_key() {
		return $this->cell_key;
	}

	public function get_table() {
		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->table_tag,
			nmgr_format_attributes( $this->get_table_attributes() ),
			$this->get_head() . $this->get_rows(),
			$this->table_tag
		);
	}

	public function get() {
		return $this->get_table();
	}

	public function get_template() {
		$template = $this->get_nav();
		$template .= $this->get_table();
		$template .= $this->get_nav();
		return $template;
	}

	public function show() {
		echo wp_kses( $this->get_table(), nmgr_allowed_post_tags() );
	}

	protected function get_head() {
		if ( !$this->head ) {
			return;
		}

		$cells = '';
		foreach ( array_keys( $this->get_data() ) as $key ) {
			$cells .= $this->get_header( $key );
		}

		if ( $cells ) {
			return sprintf(
				'<%1$s>%2$s</%3$s>',
				$this->head_tag,
				$cells,
				$this->head_tag
			);
		}
	}

	protected function get_header( $key ) {
		$value = $this->get_data()[ $key ][ 'table_header_content' ] ?? $this->get_data()[ $key ][ 'label' ] ?? null;

		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->header_tag,
			nmgr_format_attributes( $this->get_header_attributes( $key ) ),
			$value,
			$this->header_tag
		);
	}

	protected function rows_object() {
		return [];
	}

	/**
	 * Array of objects to create rows for
	 * @param array $object
	 */
	public function set_rows_object( $object = [] ) {
		$this->rows_object = $object;
	}

	/**
	 * The object to create a single row for
	 * @param array|object $object
	 */
	public function set_row_object( $object ) {
		$this->row_object = $object;
	}

	public function get_row_object() {
		return $this->row_object;
	}

	/**
	 * Get the table rows
	 * Typically this should be used after setting the objects to create rows for
	 * with $this->set_rows_object().
	 */
	protected function get_rows() {
		$rows = '';
		foreach ( $this->rows_object as $key => $object ) {
			$this->row_key = $key;
			$this->set_row_object( $object );
			$rows .= $this->get_row();
		}

		if ( $rows ) {
			return !$this->body_tag ? $rows : sprintf(
					'<%1$s>%2$s</%3$s>',
					$this->body_tag,
					$rows,
					$this->body_tag
			);
		}
	}

	/**
	 * Get the table row
	 * Typically this should be used after setting the row object with $this->set_row_object().
	 */
	public function get_row() {
		$cells = '';
		foreach ( array_keys( $this->get_data() ) as $key ) {
			$cells .= $this->get_cell( $key );
		}

		if ( $cells ) {
			return sprintf(
				'<%1$s %2$s>%3$s</%4$s>',
				$this->row_tag,
				nmgr_format_attributes( $this->get_row_attributes() ),
				$cells,
				$this->row_tag
			);
		}
	}

	public function get_cell( $key ) {
		$value = $this->get_data()[ $key ][ 'value' ] ?? null;

		if ( is_callable( $value ) ) {
			$this->cell_key = $key;
			$value = call_user_func( $value, $this );
		}

		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->cell_tag,
			nmgr_format_attributes( $this->get_cell_attributes( $key ) ),
			$value,
			$this->cell_tag
		);
	}

	protected function get_table_attributes() {
		$attributes = [
			'id' => $this->get_html_id(),
			'data-page' => $this->pagination_args[ 'page' ] ?? null,
			'data-order' => $this->order,
			'data-orderby' => $this->orderby,
			'data-class' => get_class( $this ),
			'class' => [
				'nmgr-table',
			],
		];

		return nmgr_merge_args( $attributes, $this->table_attributes );
	}

	protected function get_header_attributes( $key ) {
		$data = $this->get_data()[ $key ] ?? [];

		$attributes = [
			'class' => [
				'nmgr_' . $key
			],
		];

		if ( !empty( $data[ 'desc' ] ) ) {
			$attributes[ 'title' ] = $data[ 'desc' ];
			$attributes[ 'class' ][] = 'nmgr-tip';
		}

		return $attributes;
	}

	protected function get_row_attributes() {
		return [];
	}

	protected function get_cell_attributes( $key ) {
		$data = $this->get_data()[ $key ] ?? [];

		$attributes = [
			'class' => [
				'nmgr_' . $key
			],
		];

		if ( !empty( $data[ 'label' ] ) &&
			(!array_key_exists( 'table_header_content', $data ) || !empty( $data[ 'table_header_content' ] )) ) {
			$attributes[ 'data-title' ] = $data[ 'label' ];
		}

		return $attributes;
	}

	protected function get_items_count() {
		return 0;
	}

	protected function get_items_per_page() {
		return nmgr()->is_pro ?
			apply_filters( 'nmgr_items_per_page', $this->items_per_page, $this->id ) :
			$this->items_per_page;
	}

	/**
	 * Set the current page we want to display
	 * @param int $page
	 */
	public function set_page( int $page ) {
		$this->page = $page;
	}

	protected function set_pagination_args() {
		$items_per_page = $this->get_items_per_page();
		if ( $items_per_page ) {
			$total_pages = ceil( ( int ) $this->get_items_count() / ( int ) $items_per_page );
			if ( 1 < $total_pages ) {
				$current_page = ( float ) $this->page;
				$page = min( $current_page, $total_pages );
				$this->pagination_args = [
					'total_pages' => $total_pages,
					'limit' => ( int ) $items_per_page,
					'page' => $page,
				];
			}
		}
	}

	public function setup() {
		$this->set_pagination_args();
		$this->set_rows_object( $this->rows_object() );
		return $this;
	}

	/**
	 * Get the navigation buttons for the account section table.
	 *
	 * For this to work, the property $pagination_args must be set and this
	 * can only be done with the function $this->process_pagination() is run.
	 * This function is usually run in order to get the table in the first place.
	 * Therefore the table must be gotten before this function can be run.
	 *
	 * @return string|void
	 */
	public function get_nav() {
		if ( empty( $this->pagination_args ) ) {
			return;
		}

		$defaults = [
			'page' => 1,
			'total_pages' => 1,
			'wrapper_attributes' => [
				'class' => [
					'nmgr-navs',
					$this->id,
				],
			],
		];
		$pargs = nmgr_merge_args( $defaults, $this->pagination_args );
		$page = ( int ) $pargs[ 'page' ];
		$total_pages = ( int ) $pargs[ 'total_pages' ];

		if ( 2 > $total_pages ) {
			return;
		}

		ob_start();
		?>
		<div <?php echo wp_kses( nmgr_format_attributes( $pargs[ 'wrapper_attributes' ] ), [] ); ?>>
			<span class="page-info">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					nmgr()->is_pro ? __( 'Page %1$d of %2$d', 'nm-gift-registry' ) : __( 'Page %1$d of %2$d', 'nm-gift-registry-lite' ),
					$page,
					$total_pages
				);
				?>
			</span>
			<?php if ( 1 !== $page ) : ?>
				<a href="#"
					 class="nmgr-tip previous nmgr-nav"
					 data-action="previous"
					 data-target_id="<?php echo esc_attr( $this->get_html_id() ); ?>"
					 title="<?php
					 echo esc_html( nmgr()->is_pro ?
							 __( 'Previous', 'nm-gift-registry' ) :
							 __( 'Previous', 'nm-gift-registry-lite' ) );
					 ?>">&#10092;</a>
				 <?php endif; ?>
				 <?php if ( $page !== $total_pages ) : ?>
				<a href="#"
					 class="nmgr-tip next nmgr-nav"
					 data-action="next"
					 data-target_id="<?php echo esc_attr( $this->get_html_id() ); ?>"
					 title="<?php
					 echo esc_html( nmgr()->is_pro ?
							 __( 'Next', 'nm-gift-registry' ) :
							 __( 'Next', 'nm-gift-registry-lite' ) );
					 ?>">&#10093;</a>
				 <?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

}
