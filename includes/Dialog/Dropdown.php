<?php

namespace NMGR\Dialog;

defined( 'ABSPATH' ) || exit;

class Dropdown {

	protected $toggle_btn = '';
	protected $menu_items = [];
	protected $container_attributes = array(
		'class' => array(
			'nmgr-dropdown',
		),
	);
	protected $toggle_btn_attributes = array(
		'class' => array(
			'nmgr-dropdown-btn',
		),
	);
	protected $menu_attributes = array(
		'class' => array(
			'nmgr-submenu'
		),
	);

	private function set_default_toggler() {
		$this->toggle_btn = nmgr_get_svg( [
			'icon' => 'dropdown-toggle',
			'fill' => 'currentColor',
			] );

		$this->toggle_btn_attributes[ 'class' ][] = 'nmgr-default';

		$this->container_attributes[ 'title' ] = nmgr()->is_pro ?
			__( 'Actions', 'nm-gift-registry' ) :
			__( 'Actions', 'nm-gift-registry-lite' );

		$this->container_attributes[ 'class' ][] = 'nmgr-tip';
	}

	/**
	 * Set the toggle button or text used to open and close the dropdown
	 * @param string $text
	 */
	public function set_toggler( string $text ) {
		$this->toggle_btn = $text;
	}

	public function add_container_class( $class ) {
		$this->container_attributes[ 'class' ] = array_merge(
			$this->container_attributes[ 'class' ],
			( array ) $class
		);
	}

	/**
	 * Add a dropdown menu item
	 * @param string $text The text of the item
	 * @param array $attributes Html attributes for the menu item element
	 * @param string $tag The element tag to use for the menu item
	 * @return $this
	 */
	private function add_menu_item( $text, $attributes, $tag ) {
		$item = [
			'tag' => $tag,
			'text' => $text,
			'attributes' => $attributes
		];

		$this->menu_items[] = $item;
		return $this;
	}

	/**
	 * Add a normal menu item to the dropdown menu
	 * @param string $text The text of the item
	 * @param array $attributes Html attributes for the menu item element
	 * @param string $tag Html tag to use for the menu item
	 * @return $this
	 */
	public function set_menu_item( string $text, array $attributes = [], $tag = 'a' ) {
		return $this->add_menu_item( $text, $attributes, $tag );
	}

	/**
	 * Add a text menu item to the dropdown menu.
	 * This is simply a text that doesn't link to anywhere
	 *
	 * @param string $text The text of the item
	 * @param array $attributes Html attributes for the menu item element
	 * @param string $tag Html tag to use for the menu item
	 * @return $this
	 */
	public function set_menu_text( string $text, array $attributes = [], $tag = 'span' ) {
		$attr = nmgr_merge_args( [ 'class' => [ 'nmgr-text', 'nmgr-disabled' ] ], $attributes );
		return $this->add_menu_item( $text, $attr, $tag );
	}

	/**
	 * Add a header to the dropdown menu
	 * @param string $text The text of the header
	 * @param array $attributes Html attributes for the header element
	 * @param string $tag Html tag to use for the header
	 * @return $this
	 */
	public function set_menu_header( string $text, array $attributes = [], $tag = 'h6' ) {
		$attr = nmgr_merge_args( [ 'class' => [ 'nmgr-header', 'nmgr-disabled' ] ], $attributes );
		return $this->add_menu_item( $text, $attr, $tag );
	}

	/**
	 * Add a divider to separate the dropdown menu items
	 * @return $this
	 */
	public function set_menu_divider() {
		return $this->add_menu_item( '', [], '' );
	}

	/**
	 * Get the html element for a menu item
	 * @param array $item The menu item argument
	 * @return string
	 */
	private function get_menu_item_html( $item ) {
		$text = $item[ 'text' ] ?? null;
		$attr = isset( $item[ 'attributes' ] ) ? nmgr_utils_format_attributes( $item[ 'attributes' ] ) : null;
		$opening_tag = "<{$item[ 'tag' ]} ";
		$closing_tag = $text ? " </{$item[ 'tag' ]}>" : null;
		return $opening_tag . $attr . '>' . $text . $closing_tag;
	}

	public function has_items() {
		return !empty( $this->menu_items );
	}

	protected function template() {
		if ( !$this->toggle_btn ) {
			$this->set_default_toggler();
		}

		$container_attr = nmgr_utils_format_attributes( $this->container_attributes );
		$toggle_attr = nmgr_utils_format_attributes( $this->toggle_btn_attributes );
		?>
		<ul <?php echo wp_kses( $container_attr, [] ); ?>>
			<li>
				<div <?php echo wp_kses( $toggle_attr, [] ); ?>>
					<?php echo wp_kses( $this->toggle_btn, nmgr_allowed_post_tags() ); ?>
				</div>

				<?php if ( !empty( $this->menu_items ) ) : ?>
					<ul <?php echo wp_kses( nmgr_utils_format_attributes( $this->menu_attributes ), [] ); ?>>
						<?php foreach ( $this->menu_items as $item ): ?>
							<li>
								<?php echo wp_kses( $this->get_menu_item_html( $item ), nmgr_allowed_post_tags() ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

			</li>
		</ul>
		<?php
	}

	public function get() {
		ob_start();
		$this->template();
		return ob_get_clean();
	}

}
