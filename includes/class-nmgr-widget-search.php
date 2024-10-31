<?php
/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Widget_Search extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'nmgr_search',
			nmgr()->is_pro ?
				__( 'NM Gift Registry Search', 'nm-gift-registry' ) :
				__( 'NM Gift Registry Search', 'nm-gift-registry-lite' ),
			array(
				'description' => sprintf(
					/* translators: %s wishlist type title */
					nmgr()->is_pro ? __( '%s search functionality.', 'nm-gift-registry' ) : __( '%s search functionality.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( 'c' )
				),
			)
		);

		add_action( 'widgets_init', function() {
			register_widget( 'NMGR_Widget_Search' );
		} );
	}

	protected function get_form_fields() {
		$search_args = \NMGR\Lib\Archive::get_search_template_args();

		return array(
			'title' => array(
				'type' => 'text',
				'default' => '',
				'label' => nmgr()->is_pro ?
				__( 'Title', 'nm-gift-registry' ) :
				__( 'Title', 'nm-gift-registry-lite' ),
			),
			'hide_form' => array(
				'type' => 'checkbox',
				'default' => 0,
				'label' => nmgr()->is_pro ?
				__( 'Hide form', 'nm-gift-registry' ) :
				__( 'Hide form', 'nm-gift-registry-lite' ),
			),
			'show_results' => array(
				'type' => 'checkbox',
				'default' => 0,
				'label' => nmgr()->is_pro ?
				__( 'Show results', 'nm-gift-registry' ) :
				__( 'Show results', 'nm-gift-registry-lite' ),
			),
			'show_results_if_no_search_query' => array(
				'type' => 'checkbox',
				'default' => 0,
				'label' => nmgr()->is_pro ?
				__( 'Show all results if no search query', 'nm-gift-registry' ) :
				__( 'Show all results if no search query', 'nm-gift-registry-lite' ),
			),
			'input_required' => array(
				'type' => 'checkbox',
				'default' => 0,
				'label' => nmgr()->is_pro ?
				__( 'Input required', 'nm-gift-registry' ) :
				__( 'Input required', 'nm-gift-registry-lite' ),
			),
			'input_placeholder' => array(
				'type' => 'text',
				'default' => $search_args[ 'input_placeholder' ] ?? '',
				'label' => nmgr()->is_pro ?
				__( 'Input placeholder', 'nm-gift-registry' ) :
				__( 'Input placeholder', 'nm-gift-registry-lite' ),
			),
			'submit_button_text' => array(
				'type' => 'text',
				'default' => $search_args[ 'submit_button_text' ] ?? '',
				'label' => nmgr()->is_pro ?
				__( 'Submit button text', 'nm-gift-registry' ) :
				__( 'Submit button text', 'nm-gift-registry-lite' ),
			),
			'form_action' => array(
				'type' => 'text',
				'default' => $search_args[ 'form_action' ],
				'placeholder' => sprintf(
					/* translators: %s: site url */
					nmgr()->is_pro ? __( 'e.g. %s', 'nm-gift-registry' ) : __( 'e.g. %s', 'nm-gift-registry-lite' ),
					$search_args[ 'form_action' ]
				),
				'label' => nmgr()->is_pro ?
				__( 'Form action', 'nm-gift-registry' ) :
				__( 'Form action', 'nm-gift-registry-lite' ),
			),
		);
	}

	public function widget( $args, $instance ) {
		echo $args[ 'before_widget' ];

		if ( !empty( $instance[ 'title' ] ) ) {
			echo $args[ 'before_title' ] . apply_filters( 'widget_title', $instance[ 'title' ] ) . $args[ 'after_title' ];
		}

		$template_args = array(
			'show_form' => empty( $instance[ 'hide_form' ] ) ? 1 : 0,
			'show_results_if_no_search_query' => empty( $instance[ 'show_results_if_no_search_query' ] ) ? 0 : 1,
			'show_results' => empty( $instance[ 'show_results' ] ) ? 0 : 1,
			'input_required' => empty( $instance[ 'input_required' ] ) ? 0 : 1,
			'input_placeholder' => empty( $instance[ 'input_placeholder' ] ) ? '' : $instance[ 'input_placeholder' ],
			'submit_button_text' => empty( $instance[ 'submit_button_text' ] ) ? '' : $instance[ 'submit_button_text' ],
			'form_action' => empty( $instance[ 'form_action' ] ) ? '' : $instance[ 'form_action' ],
		);

		echo '<div class="widget_nmgr_search_content">';

		echo \NMGR\Lib\Archive::get_search_template( $template_args );

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
			$placeholder = isset( $setting[ 'placeholder' ] ) ? $setting[ 'placeholder' ] : '';
			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $setting[ 'default' ];

			switch ( $setting[ 'type' ] ) {
				case 'text':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo wp_kses_post( $setting[ 'label' ] ); ?></label>
						<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" />
						<?php if ( isset( $setting[ 'desc' ] ) ) : ?>
							<small><?php echo esc_html( $setting[ 'desc' ] ); ?></small>
						<?php endif; ?>
					</p>
					<?php
					break;

				case 'checkbox':
					?>
					<p>
						<input class="checkbox <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="checkbox" value="1" <?php checked( $value, 1 ); ?> />
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting[ 'label' ]; /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></label>
					</p>
					<?php
					break;

				case 'textarea':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting[ 'label' ]; /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></label>
						<textarea class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" cols="20" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
						<?php if ( isset( $setting[ 'desc' ] ) ) : ?>
							<small><?php echo esc_html( $setting[ 'desc' ] ); ?></small>
						<?php endif; ?>
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
				case 'textarea':
					$instance[ $key ] = wp_kses( trim( wp_unslash( $new_instance[ $key ] ) ), wp_kses_allowed_html( 'post' ) );
					break;
				case 'checkbox':
					$instance[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
					break;
				default:
					$instance[ $key ] = isset( $new_instance[ $key ] ) ? sanitize_text_field( $new_instance[ $key ] ) : $setting[ 'default' ];
					break;
			}
		}

		return $instance;
	}

}
