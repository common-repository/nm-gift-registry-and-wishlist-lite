<?php
/**
 * Sync
 */

namespace NMGR\Setup\Wizard;

use NMGR\Setup\Wizard\Wizard;

defined( 'ABSPATH' ) || exit;

class Fields {

	private $settings_fields;
	private $type;

	public function set_type( $type ) {
		$this->type = $type;
	}

	public function get_type() {
		return $this->type;
	}

	/**
	 * Let us know if we are currently updating the plugin for
	 * users that have used previous versions only as a gift registry
	 */
	public function is_gift_registry_plugin_update() {
		return 'gift-registry' === $this->get_type() && Wizard::is_plugin_update();
	}

	public function get_settings_fields() {
		if ( !$this->settings_fields ) {
			$this->settings_fields = array_merge(
				nmgr()->gift_registry_settings()->get_fields(),
				nmgr()->wishlist_settings()->get_fields()
			);
		}
		return $this->settings_fields;
	}

	private function get_settings_field( $key ) {
		foreach ( $this->get_settings_fields() as $field ) {
			if ( $key === ($field[ 'id' ] ?? '') ) {
				return $field;
			}
		}
		return [];
	}

	private function merge_with_settings_fields( $fields ) {
		foreach ( $fields as $key => $field ) {
			$fields[ $key ] = array_merge( $this->get_settings_field( $key ), $field );
		}
		return $fields;
	}

	private function update_with_saved_values( $fields ) {
		$options = nmgr_get_option();
		foreach ( array_keys( $fields ) as $key ) {
			if ( array_key_exists( $key, $options ) ) {
				$fields[ $key ][ 'default' ] = $options[ $key ];
			} elseif ( !array_key_exists( 'default', $fields[ $key ] ) ) {
				$fields[ $key ][ 'default' ] = '';
			}
		}
		return $fields;
	}

	public function get( $fieldset = null ) {
		$page_id_text = nmgr()->is_pro ?
			__( 'IMPORTANT! This page must be set as it is where wishlists would be viewed and managed.', 'nm-gift-registry' ) :
			__( 'IMPORTANT! This page must be set as it is where wishlists would be viewed and managed.', 'nm-gift-registry-lite' );

		$page_id_note = nmgr()->is_pro ?
			__( 'Page contents <code>[nmgr_wishlist]</code> or <code>[nmgr_archive]</code>.', 'nm-gift-registry' ) :
			__( 'Page contents <code>[nmgr_wishlist]</code> or <code>[nmgr_archive]</code>.', 'nm-gift-registry-lite' );

		if ( 'wishlist' === $this->get_type() ) {
			$page_id_note = nmgr()->is_pro ?
				__( 'Page contents <code>[nmgr_wishlist]</code>.', 'nm-gift-registry' ) :
				__( 'Page contents <code>[nmgr_wishlist]</code>.', 'nm-gift-registry-lite' );
		}

		$fields = [
			'wishlist_enable' => [
				'label' => nmgr()->is_pro ?
				__( 'I want to use the plugin as a Wishlist', 'nm-gift-registry' ) :
				__( 'I want to use the plugin as a Wishlist', 'nm-gift-registry-lite' ),
				'prefix' => false,
				'fieldset' => 'wishlist',
			],
			'enable' => [
				'label' => nmgr()->is_pro ?
				__( 'I want to use the plugin as a Gift Registry', 'nm-gift-registry' ) :
				__( 'I want to use the plugin as a Gift Registry', 'nm-gift-registry-lite' ),
				'fieldset' => 'gift-registry',
			],
			'page_id' => [
				'type' => 'select',
				'options' => $this->get_pages_for_selection(),
				'content' => $this->create_wishlist_page_input(),
				'fieldset' => 'general',
				'custom_attributes' => [
					'class' => [
						'nmgr-select-page',
					],
				],
				'description' => $page_id_text . '<div>' . $page_id_note . '</div>',
			],
			'enable_archives' => [
				'type' => 'checkbox',
				'label' => nmgr()->is_pro ?
				__( 'Enable wishlist archives', 'nm-gift-registry' ) :
				__( 'Enable wishlist archives', 'nm-gift-registry-lite' ),
				'show' => 'gift-registry' === $this->get_type(),
				'default' => 1,
				'fieldset' => 'general',
				'description' => nmgr()->is_pro ?
				__( 'Select this if you want to also show the list of all wishlists on the frontend.', 'nm-gift-registry' ) :
				__( 'Select this if you want to also show the list of all wishlists on the frontend.', 'nm-gift-registry-lite' ),
			],
			'allow_multiple_wishlists' => [
				'fieldset' => 'general',
				'show' => nmgr()->is_pro,
			],
			'allow_guest_wishlists' => [
				'fieldset' => 'general',
			],
			'default_wishlist_title' => [
				'fieldset' => 'add_to_wishlist',
			],
			'add_to_wishlist_button_text' => [
				'fieldset' => 'add_to_wishlist',
			],
			'add_to_new_wishlist_button_text' => [
				'fieldset' => 'add_to_wishlist',
				'show' => nmgr()->is_pro,
			],
			'add_to_wishlist_button_position_archive' => [
				'fieldset' => 'add_to_wishlist',
			],
			'add_to_wishlist_button_position_single' => [
				'fieldset' => 'add_to_wishlist',
			],
			'share_on_facebook' => [
				'type' => 'checkbox',
				'default' => 1,
				'fieldset' => 'sharing',
				'label' => nmgr()->is_pro ?
				__( 'Allow sharing on Facebook', 'nm-gift-registry' ) :
				__( 'Allow sharing on Facebook', 'nm-gift-registry-lite' ),
			],
			'share_on_twitter' => [
				'type' => 'checkbox',
				'default' => 1,
				'fieldset' => 'sharing',
				'label' => nmgr()->is_pro ?
				__( 'Allow sharing on Twitter', 'nm-gift-registry' ) :
				__( 'Allow sharing on Twitter', 'nm-gift-registry-lite' ),
			],
			'share_on_pinterest' => [
				'type' => 'checkbox',
				'default' => 1,
				'fieldset' => 'sharing',
				'label' => nmgr()->is_pro ?
				__( 'Allow sharing on Pinterest', 'nm-gift-registry' ) :
				__( 'Allow sharing on Pinterest', 'nm-gift-registry-lite' ),
			],
			'share_on_whatsapp' => [
				'type' => 'checkbox',
				'default' => 1,
				'fieldset' => 'sharing',
				'label' => nmgr()->is_pro ?
				__( 'Allow sharing on WhatsApp', 'nm-gift-registry' ) :
				__( 'Allow sharing on WhatsApp', 'nm-gift-registry-lite' ),
			],
			'share_on_email' => [
				'type' => 'checkbox',
				'default' => 1,
				'fieldset' => 'sharing',
				'label' => nmgr()->is_pro ?
				__( 'Allow sharing on Email', 'nm-gift-registry' ) :
				__( 'Allow sharing on Email', 'nm-gift-registry-lite' ),
			],
			'shipping_address_required' => [
				'type' => 'checkbox',
				'default' => '',
				'fieldset' => 'shipping',
				'label' => nmgr()->is_pro ?
				__( 'Make shipping address required', 'nm-gift-registry' ) :
				__( 'Make shipping address required', 'nm-gift-registry-lite' ),
			],
			'shipping_to_wishlist_address' => [
				'type' => 'checkbox',
				'default' => '',
				'fieldset' => 'shipping',
				'label' => nmgr()->is_pro ?
				__( 'Ship cart items to the wishlist\'s owner\'s address', 'nm-gift-registry' ) :
				__( 'Ship cart items to the wishlist\'s owner\'s address', 'nm-gift-registry-lite' ),
			],
		];

		$requested = [];

		// Requested
		if ( is_string( $fieldset ) ) {
			foreach ( $fields as $key => $args ) {
				if ( isset( $args[ 'fieldset' ] ) && ($args[ 'fieldset' ] === $fieldset) ) {
					$requested[ $key ] = $args;
				}
			}
		} elseif ( is_array( $fieldset ) ) {
			foreach ( $fields as $key => $args ) {
				if ( in_array( $key, $fieldset ) ) {
					$requested[ $key ] = $args;
				}
			}
		} else {
			$requested = $fields;
		}

		// Prefix
		if ( 'wishlist' === $this->get_type() ) {
			$prefixed = [];
			foreach ( $requested as $key => $args ) {
				if ( false !== ( bool ) ($args[ 'prefix' ] ?? true) ) {
					$prefixed[ 'wishlist_' . $key ] = $args;
				} else {
					$prefixed[ $key ] = $args;
				}
			}
			$requested = $prefixed;
		}

		// Hide
		foreach ( $requested as $key => $args ) {
			if ( false === ( bool ) ($args[ 'show' ] ?? true) ) {
				unset( $requested[ $key ] );
			}
		}

		$merged = $this->merge_with_settings_fields( $requested );
		$updated = $this->update_with_saved_values( $merged );

		foreach ( $updated as $key => $args ) {
			if ( empty( $args[ 'id' ] ) ) {
				$updated[ $key ][ 'id' ] = $key;
			}
		}

		return $updated;
	}

	private function get_pages_for_selection() {
		$pages_arr = [
			'create' => nmgr()->is_pro ?
			__( 'Create new page', 'nm-gift-registry' ) :
			__( 'Create new page', 'nm-gift-registry-lite' ),
		];

		$pages = get_pages();
		foreach ( $pages as $page ) {
			$pages_arr[ $page->ID ] = $page->post_title;
		}

		return $pages_arr;
	}

	private function create_wishlist_page_input() {
		ob_start();
		$placeholder = nmgr()->is_pro ?
			__( 'Set page title', 'nm-gift-registry' ) :
			__( 'Set page title', 'nm-gift-registry-lite' );

		$prefix = 'wishlist' === $this->get_type() ? 'wishlist_' : '';
		?>
		<input class="nmgr_set_page_title" type="text"
					 name="<?php echo esc_attr( $prefix ); ?>page_title"
					 placeholder="<?php echo esc_attr( $placeholder ); ?>"
					 style="display:none;"
					 required disabled>
					 <?php
					 return ob_get_clean();
				 }

				 public function sanitize( $var ) {
					 if ( is_array( $var ) ) {
						 return array_map( [ $this, 'sanitize' ], $var );
					 } else {
						 return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
					 }
				 }

				 public function output_table( $fields ) {
					 ?>
		<div class="nmgr-setup-table">
			<?php
			foreach ( $fields as $key => $field ) {
				$input_id = 'nmgr-' . $key;
				$type = $field[ 'type' ] ?? '';
				$attributes = $field[ 'custom_attributes' ] ?? [];
				$attrs = nmgr_utils_format_attributes( $attributes );
				?>
				<div class="nmgr-row">
					<div class="nmgr-cell">
						<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo wp_kses_post( $field[ 'label' ] ?? ''  ); ?></label>
						<?php if ( !empty( $field[ 'description' ] ) ) : ?>
							<div class="nmgr-desc"><?php echo wp_kses_post( $field[ 'description' ] ); ?></div>
						<?php endif; ?>
					</div>
					<div class="nmgr-cell">
						<?php
						switch ( $type ) {
							case 'checkbox':
								$checkbox_args = array(
									'input_name' => $key,
									'input_id' => $input_id,
									'checked' => checked( ( int ) ($field[ 'default' ] ?? ''), 1, false ),
									'show_hidden_input' => true,
								);
								echo nmgr_get_checkbox_switch( $checkbox_args );
								break;

							case 'select':
								?>
								<select id="<?php echo esc_attr( $input_id ); ?>"
												name="<?php echo esc_attr( $key ); ?>"
												<?php echo $attrs; ?>>
													<?php
													foreach ( $field[ 'options' ] as $key => $value ) {
														?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, ($field[ 'default' ] ?? '' ) ); ?>>
											<?php echo esc_html( $value ); ?>
										</option>
										<?php
									}
									?>
								</select>
								<?php
								break;

							case 'text':
								?>
								<input type="text"
											 id="<?php echo esc_attr( $input_id ); ?>"
											 name="<?php echo esc_attr( $key ); ?>"
											 value="<?php echo esc_attr( $field[ 'default' ] ?? ''  ); ?>">
											 <?php
											 break;

										 default:
											 break;
									 }
									 ?>

						<?php
						if ( !empty( $field[ 'content' ] ) ) {
							echo $field[ 'content' ];
						}
						?>
					</div>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}

}
