<?php
/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Widget_Cart extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'nmgr_cart',
			nmgr()->is_pro ?
				__( 'NM Gift Registry Cart', 'nm-gift-registry' ) :
				__( 'NM Gift Registry Cart', 'nm-gift-registry-lite' ),
			array(
				'description' => nmgr()->is_pro ?
					__( 'Display a user\'s wishlists like a cart.', 'nm-gift-registry' ) :
					__( 'Display a user\'s wishlists like a cart.', 'nm-gift-registry-lite' ),
			)
		);

		add_action( 'widgets_init', function () {
			register_widget( 'NMGR_Widget_Cart' );
		} );
	}

	protected function get_form_fields() {
		return array(
			'type' => array(
				'type' => 'select',
				'label' => nmgr()->is_pro ?
				__( 'Wishlist type', 'nm-gift-registry' ) :
				__( 'Wishlist type', 'nm-gift-registry-lite' ),
				'options' => [
					'gift-registry' => nmgr()->is_pro ?
					__( 'Gift Registry', 'nm-gift-registry' ) :
					__( 'Gift Registry', 'nm-gift-registry-lite' ),
					'wishlist' => nmgr()->is_pro ?
					__( 'Wishlist', 'nm-gift-registry' ) :
					__( 'Wishlist', 'nm-gift-registry-lite' ),
				],
			),
		);
	}

	public function widget( $args, $instance ) {
		$template_args = array(
			'type' => $instance[ 'type' ] ?? 'gift-registry',
		);

		if ( !is_nmgr_enabled( $template_args[ 'type' ] ) ) {
			return;
		}

		echo $args[ 'before_widget' ];
		echo '<div class="widget_nmgr_cart_content">';
		echo static::template( $template_args );
		echo '</div>';
		echo $args[ 'after_widget' ];
	}

	public function form( $instance ) {
		$fields = $this->get_form_fields();

		if ( empty( $fields ) ) {
			return;
		}

		foreach ( $fields as $key => $setting ) {
			$class = isset( $setting[ 'class' ] ) ? $setting[ 'class' ] : '';
			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : (isset( $setting[ 'std' ] ) ? $setting[ 'std' ] : '');

			switch ( $setting[ 'type' ] ) {
				case 'text':
				case 'number':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo wp_kses_post( $setting[ 'label' ] ); ?></label>
						<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="<?php echo $setting[ 'type' ]; ?>" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
					break;

				case 'select':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting[ 'label' ]; /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></label>
						<select class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>">
							<?php foreach ( $setting[ 'options' ] as $option_key => $option_value ) : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $value ); ?>><?php echo esc_html( $option_value ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<?php
					break;
			}
		}
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		if ( empty( $this->get_form_fields() ) ) {
			return $instance;
		}

		foreach ( $this->get_form_fields() as $key => $setting ) {
			if ( !isset( $setting[ 'type' ] ) ) {
				continue;
			}

			switch ( $setting[ 'type' ] ) {
				default:
					$instance[ $key ] = isset( $new_instance[ $key ] ) ? sanitize_text_field( $new_instance[ $key ] ) : $setting[ 'std' ];
					break;
			}
		}

		return $instance;
	}

	public static function template( $atts = '' ) {
		$args = array( 'type' => 'gift-registry' );

		$vars = shortcode_atts( $args, $atts, 'nmgr_cart' );

		$type = $vars[ 'type' ];

		if ( !is_nmgr_enabled( $type ) ) {
			return;
		}

		$vars[ 'cart_qty' ] = 0;
		$vars[ 'url' ] = nmgr_get_url( $type, 'home' );

		foreach ( nmgr_get_user_wishlist_ids( '', $type ) as $wishlist_id ) {
			$wishlist = nmgr()->wishlist();
			$wishlist->set_id( $wishlist_id );
			$vars[ 'cart_qty' ] = $vars[ 'cart_qty' ] + ( int ) $wishlist->get_items_quantity_count();
		}

		if ( !is_nmgr_user( $type ) ) {
			$vars[ 'url' ] = add_query_arg( array(
				'nmgr-notice' => 'login-to-access',
				'nmgr-redirect' => $_SERVER[ 'REQUEST_URI' ],
				'nmgr-type' => $type,
				), wc_get_page_permalink( 'myaccount' ) );
		}

		ob_start();
		$url = $vars[ 'url' ];
		$cart_qty = $vars[ 'cart_qty' ];
		?>
		<div id="nmgr-cart-<?php echo rand(); ?>" class="nmgr-cart" data-type="<?php echo esc_attr( $type ); ?>">
			<a href="<?php echo esc_url( $url ); ?>"
				 title="<?php
				 printf(
					 /* translators %s: wishlist type title */
					 esc_attr( nmgr()->is_pro ? __( 'View your %s items', 'nm-gift-registry' ) : __( 'View your %s items', 'nm-gift-registry-lite' ) ),
					 esc_attr( nmgr_get_type_title( '', false, $type ) )
				 );
				 ?>"
				 class="nmgr-show-cart-contents nmgr-tip">
					 <?php
					 $svg = array(
						 'icon' => 1 > ( int ) $cart_qty ? 'heart-empty' : 'heart',
						 'size' => 2,
						 'fill' => 'currentColor',
					 );

					 echo wp_kses( nmgr_get_svg( $svg ), nmgr_allowed_post_tags() );
					 ?>
				</span>
				<span class="count"><?php echo absint( $cart_qty ); ?></span>
			</a>
		</div>
		<?php
		$template = ob_get_clean();

		return apply_filters( 'nmgr_cart_template', $template, $vars );
	}

}
