<?php

namespace NMGR\Dialog;

class Modal {

	protected $id = '';
	protected $type = 'modal';
	protected $content = '';
	protected $options = [
		'width' => 500,
	];
	protected $container_attributes = [];
	protected $footer = '';

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function set_content( $content ) {
		$this->content = $content;
	}

	public function set_title( $title ) {
		$this->options[ 'title' ] = $title;
	}

	protected function get_container_attributes( $formatted = false ) {
		$atts = array_merge( [ 'id' => $this->id ], $this->container_attributes );
		$options = apply_filters( 'nmgr_dialog_options', $this->get_options() );
		$atts[ 'data-options' ] = htmlspecialchars( json_encode( $options ) );

		return $formatted ? nmgr_utils_format_attributes( $atts ) : $atts;
	}

	/**
	 * Make the modal width large (800px)
	 * Default width is 500px set in javascript dialog options
	 */
	public function make_large() {
		$this->options[ 'width' ] = 800;
	}

	protected function parent_classes() {
		return [
			'nmgr-dialog',
			$this->type,
			$this->id,
		];
	}

	protected function get_options() {
		$this->options[ 'classes' ] = [
			'ui-dialog' => implode( ' ', $this->parent_classes() ),
		];

		return $this->options;
	}

	public function set_footer( $footer ) {
		$this->footer = $footer;
	}

	/**
	 * Get the 'save' button for saving the component contents. Typically used in the footer.
	 * @param array $args Arguments used to compose the button html. Arguments supplied
	 * 									  are merged with the default arguments. Possible arguments:
	 * - text {string} The text to display in the button. Default "Save".
	 * - attributes {array} Attributes to add to the button such as class and data attributes.
	 * @param string $action The type of result to return. Valid values are
	 * - replace : Whether to replace the default arguments with the supplied arguments instead of merging.
	 * - args: Whether to return only the arguments used to compose the button instead of the button html.
	 * Default value is "null" which is to merge the default arguments with the supplied arguments.
	 * @return mixed
	 */
	public function get_save_button( $args = array(), $action = null ) {
		$attributes = $this->get_default_button_attributes();
		$attributes[ 'class' ][] = 'nm-save';
		$text = nmgr()->is_pro ?
			__( 'Save', 'nm-gift-registry' ) :
			__( 'Save', 'nm-gift-registry-lite' );
		$defaults = [ 'text' => $text, 'attributes' => $attributes ];

		return 'args' === $action ? $defaults : $this->compose_button( $defaults, $args, $action );
	}

	protected function get_default_button_attributes() {
		return [
			'type' => 'button',
			'class' => [
				'btn',
				'button',
				'nm-dialog-button',
			],
		];
	}

	protected function compose_button( $defaults, $args, $action ) {
		if ( 'replace' === $action ) {
			$params = $args;
		} else {
			$params = $this->merge_button_args( $defaults, $args );
		}

		return '<button ' . nmgr_utils_format_attributes( $params[ 'attributes' ] ) .
			'>' . esc_html( $params[ 'text' ] ) . '</button>';
	}

	protected function merge_button_args( $defaults, $args ) {
		$result = $defaults;
		if ( isset( $args[ 'attributes' ] ) ) {
			$result[ 'attributes' ] = nmgr_merge_args( $defaults[ 'attributes' ], $args[ 'attributes' ] );
			unset( $args[ 'attributes' ] );
		}
		return nmgr_merge_args( $result, ( array ) $args );
	}

	protected function selector( $selector = '' ) {
		$parent_classes = implode( '.', $this->parent_classes() );
		return ".$parent_classes $selector";
	}

	protected function styles() {
		$width = $this->options[ 'width' ] ?? 0;
		?>
		<style>
		<?php if ( $width && 'modal' === $this->type ) { ?>
				@media (max-width: <?php echo $width; ?>px) {
			<?php echo $this->selector(); ?> {
						left: 50%;
						transform: translateX(-50%);
						max-width: 95vw;
					}
				}
		<?php } ?>

		<?php echo $this->selector(); ?> {
				border-radius: calc(.3rem - 1px);
			}

		<?php
		/**
		 * Run this code on admin area because woocommerce jquery ui css affects the display
		 * of these selectors in admin
		 */
		if ( is_nmgr_admin() ):
			?>
			<?php echo $this->selector( '.ui-dialog-titlebar-close' ); ?> {
					height: 36px;
					top: inherit;
					margin: inherit;
				}
			<?php
		endif;
		?>

		<?php
		/**
		 * Run this code on admin area because woocommerce jquery ui css affects the display
		 * of these selectors in admin
		 */
		if ( is_nmgr_admin() && !empty( $this->options[ 'title' ] ) ) :
			?>
			<?php echo $this->selector( '.ui-dialog-titlebar' ); ?> {
					background: transparent;
					border: none;
					border-bottom: 1px solid #dcdcde;
					border-radius: 0;
					font-weight: 400;
				}
			<?php
		endif;
		?>

		<?php if ( empty( $this->options[ 'title' ] ) ) : ?>
			<?php echo $this->selector( '.ui-dialog-titlebar' ); ?> {
					height: 0;
				}
		<?php endif; ?>

		<?php if ( $this->footer ) : ?>
			<?php echo $this->selector( '.nmgr-footer' ); ?> {
					display: flex;
					flex-wrap: wrap;
					flex-shrink: 0;
					align-items: center;
					justify-content: flex-end;
					padding-top: 16px;
					margin-top: 16px;
					border-top: 1px solid #dee2e6;
				}
		<?php endif; ?>
		</style>
		<?php
	}

	protected function template() {
		?>
		<div <?php echo wp_kses( $this->get_container_attributes( true ), [] ); ?>>
			<?php
			$this->styles();
			echo $this->content;
			echo $this->footer ? '<div class="nmgr-footer">' . $this->footer . '</div>' : '';
			?>
		</div>
		<?php
	}

	public function get() {
		ob_start();
		$this->print();
		return ob_get_clean();
	}

	public function print() {
		$this->template();
	}

}
