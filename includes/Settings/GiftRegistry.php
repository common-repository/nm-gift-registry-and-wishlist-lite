<?php

namespace NMGR\Settings;

use NMGR\Events\DeleteExtraUserWishlists;
use NMGR\Fields\ItemFields;
use NMGR\Settings\Admin;
use NMGR\Sub\Email;

defined( 'ABSPATH' ) || exit;

class GiftRegistry extends Admin {

	public function run() {
		parent::run();
		add_action( $this->page_slug() . '_tab_emails', [ $this, 'do_settings_sections_emails' ] );
	}

	public function get_heading() {
		return nmgr()->is_pro ?
			__( 'Gift Registry Settings', 'nm-gift-registry' ) :
			__( 'Gift Registry Settings', 'nm-gift-registry-lite' );
	}

	public function page_slug() {
		return 'nmgr-settings';
	}

	public function pre_update_option( $new_value, $old_value ) {
		$dval = $this->get_default_field_values();

		if ( array_key_exists( 'enable_messages', $old_value ) &&
			$old_value[ 'enable_messages' ] !== $new_value[ 'enable_messages' ] ) {
			if ( empty( $new_value[ 'enable_messages' ] ) ) {
				$new_value[ 'email_customer_new_message_enabled' ] = '';
				$new_value[ 'email_customer_ordered_items_checkout_message' ] = '';
				$new_value[ 'email_customer_purchased_items_checkout_message' ] = '';
			} else {
				$new_value[ 'email_customer_new_message_enabled' ] = $dval[ 'email_customer_new_message_enabled' ] ?? null;
				$new_value[ 'email_customer_ordered_items_checkout_message' ] = $dval[ 'email_customer_ordered_items_checkout_message' ] ?? null;
				$new_value[ 'email_customer_purchased_items_checkout_message' ] = $dval[ 'email_customer_purchased_items_checkout_message' ] ?? null;
			}
		}

		if ( !empty( $new_value[ 'shipping_to_wishlist_address' ] ) ) {
			$new_value[ 'shipping_address_required' ] = 1;
		}

		if ( array_key_exists( 'enable_shipping', $old_value ) &&
			$old_value[ 'enable_shipping' ] !== $new_value[ 'enable_shipping' ] ) {
			if ( empty( $new_value[ 'enable_shipping' ] ) ) {
				$new_value[ 'shipping_address_required' ] = '';
			}
		}

		if ( !empty( $new_value[ 'shipping_methods' ] ) ) {
			$new_value[ 'shipping_calculate' ] = 1;
		}

		return $new_value;
	}

	public function update_option( $old_value, $new_value ) {
		if ( array_key_exists( 'allow_multiple_wishlists', $old_value ) &&
			($old_value[ 'allow_multiple_wishlists' ] != $new_value[ 'allow_multiple_wishlists' ]) &&
			!$new_value[ 'allow_multiple_wishlists' ] ) {
			(new DeleteExtraUserWishlists( $this->get_type() ) )->run();
		}

		if ( array_key_exists( 'page_id', $new_value ) ) {
			if ( nmgr_update_pagename( $new_value[ 'page_id' ] ?? 0, $this->get_type() ) ) {
				nmgr()->flush_rewrite_rules();
			}
		}
	}

	public function get_custom_tabs() {
		$tabs = [
			'general' => array(
				'tab_title' => nmgr()->is_pro ?
				__( 'General', 'nm-gift-registry' ) :
				__( 'General', 'nm-gift-registry-lite' ),
				'sections_title' => '',
				'show_sections' => true,
				'priority' => 10,
				'sections' => array(
					'general' => nmgr()->is_pro ?
					__( 'General', 'nm-gift-registry' ) :
					__( 'General', 'nm-gift-registry-lite' ),
					'add_to_wishlist' => nmgr()->is_pro ?
					__( 'Add to wishlist', 'nm-gift-registry' ) :
					__( 'Add to wishlist', 'nm-gift-registry-lite' ),
					'add_to_cart' => nmgr()->is_pro ?
					__( 'Add to cart', 'nm-gift-registry' ) :
					__( 'Add to cart', 'nm-gift-registry-lite' ),
				),
			),
			'modules' => array(
				'tab_title' => nmgr()->is_pro ?
				__( 'Modules', 'nm-gift-registry' ) :
				__( 'Modules', 'nm-gift-registry-lite' ),
				'sections_title' => '',
				'show_sections' => true,
				'priority' => 20,
				'sections' => array(
					'profile_form' => nmgr()->is_pro ?
					__( 'Profile form', 'nm-gift-registry' ) :
					__( 'Profile form', 'nm-gift-registry-lite' ),
					'items_table' => nmgr()->is_pro ?
					__( 'Items table', 'nm-gift-registry' ) :
					__( 'Items table', 'nm-gift-registry-lite' ),
					'shipping' => nmgr()->is_pro ?
					__( 'Shipping', 'nm-gift-registry' ) :
					__( 'Shipping', 'nm-gift-registry-lite' ),
					'images' => nmgr()->is_pro ?
					__( 'Images', 'nm-gift-registry' ) :
					__( 'Images', 'nm-gift-registry-lite' ),
					'messages' => nmgr()->is_pro ?
					__( 'Messages', 'nm-gift-registry' ) :
					__( 'Messages', 'nm-gift-registry-lite' ),
					'settings_form' => nmgr()->is_pro ?
					__( 'Settings form', 'nm-gift-registry' ) :
					__( 'Settings form', 'nm-gift-registry-lite' ),
				),
			),
			'emails' => array(
				'tab_title' => nmgr()->is_pro ?
				__( 'Emails', 'nm-gift-registry' ) :
				__( 'Emails', 'nm-gift-registry-lite' ),
				'priority' => 30,
				'sections_title' => '',
				'show_sections' => false,
				'submit_button' => nmgr()->is_pro
			),
		];

		return $tabs;
	}

	public function get_profile_form_fields() {
		$settings_fields = array();
		$form = new \NMGR_Form();
		$form->set_type( $this->get_type() );
		$fields = $form->get_fields( 'profile', '', false, false );

		foreach ( $fields as $key => $args ) {
			if ( false === ($args[ 'show_in_settings' ] ?? true) ) {
				continue;
			}

			$composed_field = array(
				'id' => "display_form_{$key}",
				'label' => isset( $args[ 'label' ] ) ? $args[ 'label' ] : '',
				'type' => 'select',
				'options' => array(
					'optional' => nmgr()->is_pro ?
					__( 'Optional', 'nm-gift-registry' ) :
					__( 'Optional', 'nm-gift-registry-lite' ),
					'required' => nmgr()->is_pro ?
					__( 'Required', 'nm-gift-registry' ) :
					__( 'Required', 'nm-gift-registry-lite' ),
					'no' => nmgr()->is_pro ?
					__( 'Hidden', 'nm-gift-registry' ) :
					__( 'Hidden', 'nm-gift-registry-lite' ),
				),
				'default' => 'optional',
			);

			switch ( $key ) {
				case 'email':
					$composed_field = array_merge( $composed_field, array(
						'default' => 'required',
						'desc_tip' => nmgr()->is_pro ?
						__( 'The email field should typically be required as it is needed to send customer email notifications if applicable.', 'nm-gift-registry' ) :
						__( 'The email field should typically be required as it is needed to send customer email notifications if applicable.', 'nm-gift-registry-lite' ),
						) );
					break;
			}

			$settings_fields[] = $composed_field;
		}

		return $settings_fields;
	}

	/**
	 * Default columns on wishlist items table
	 *
	 * (We're hardcoding this for now)
	 */
	public function get_items_table_columns() {
		$settings = array();
		$fields = new ItemFields( nmgr()->items_table( nmgr()->wishlist() ) );
		$fields->filter_showing_in_settings();

		foreach ( $fields->get_data() as $key => $data ) {
			$args = array(
				'id' => "display_item_{$key}",
				'type' => 'checkbox',
				'default' => 1,
				'label' => $data[ 'label' ] ?? '',
			);

			switch ( $key ) {
				case 'checkbox':
					$args[ 'desc_tip' ] = nmgr()->is_pro ?
						__( 'The checkbox for selecting the wishlist item.', 'nm-gift-registry' ) :
						__( 'The checkbox for selecting the wishlist item.', 'nm-gift-registry-lite' );
					$args[ 'pro' ] = 1;
					break;

				case 'action_buttons':
					$args[ 'desc_tip' ] = nmgr()->is_pro ?
						__( 'This column allows actions such as editing and deleting to be performed on the wishlist items from the frontend. Unchecking it would prevent these actions from being able to be performed.', 'nm-gift-registry' ) :
						__( 'This column allows actions such as editing and deleting to be performed on the wishlist items from the frontend. Unchecking it would prevent these actions from being able to be performed.', 'nm-gift-registry-lite' );
					break;
			}

			$settings[ $key ] = $args;
		}
		return $settings;
	}

	private function get_wishlist_page_desc() {
		$desc = nmgr()->is_pro ?
			__( 'must contain the <code>[nmgr_archive]</code> or <code>[nmgr_wishlist]</code> shortcode', 'nm-gift-registry' ) :
			__( 'must contain the <code>[nmgr_archive]</code> or <code>[nmgr_wishlist]</code> shortcode', 'nm-gift-registry-lite' );

		if ( nmgr()->is_pro && nmgr_get_type_option( $this->get_type(), 'page_id' ) ) {
			$desc .= '<div class="wishlist-page-links" style="background:#dfdfdf;padding:18px;display:inline-block;margin-top:15px;">';
			$desc .= '<h4 style="margin-top:0px;">Links</h4>';
			$desc .= '<ul style="margin-bottom:0px;">';

			$links = [];
			foreach ( array_merge( [ '' ], nmgr_get_base_actions() ) as $key ) {
				$links[] = nmgr_get_url( $this->get_type(), $key );
			}

			foreach ( array_unique( $links ) as $link ) {
				$desc .= '<li><a href="' . $link . '">' . $link . '</a></li>';
			}

			$desc .= '</ul>';
			$desc .= '</div>';
		}

		return $desc;
	}

	public function general_tab_sections() {
		$sections = array();

		$sections[ 'general' ] = array(
			'title' => '',
			'description' => '',
			'section' => 'general',
			'fields' => array(
				array(
					'label' => nmgr()->is_pro ?
					__( 'Enable', 'nm-gift-registry' ) :
					__( 'Enable', 'nm-gift-registry-lite' ),
					'id' => 'enable',
					'default' => 1,
					'type' => 'checkbox',
				),
				array(
					'id' => 'page_id',
					'label' => nmgr()->is_pro ?
					__( 'Page for viewing wishlists', 'nm-gift-registry' ) :
					__( 'Page for viewing wishlists', 'nm-gift-registry-lite' ),
					'type' => 'select_page',
					'default' => '',
					'class' => 'wc-enhanced-select-nostd',
					'css' => 'min-width:300px;',
					'placeholder' => nmgr()->is_pro ?
					__( 'None', 'nm-gift-registry' ) :
					__( 'None', 'nm-gift-registry-lite' ),
					'desc' => self::get_wishlist_page_desc(),
					'desc_tip' => nmgr()->is_pro ?
					__( 'Use <code>[nmgr_archive]</code> if you want to show wishlist archives. Use <code>[nmgr_wishlist]</code> if you want to show only single wishlists.', 'nm-gift-registry' ) :
					__( 'Use <code>[nmgr_archive]</code> if you want to show wishlist archives. Use <code>[nmgr_wishlist]</code> if you want to show only single wishlists.', 'nm-gift-registry-lite' ),
					'error_codes' => [
						'no-page-id'
					],
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Sharing on social networks', 'nm-gift-registry' ) :
					__( 'Sharing on social networks', 'nm-gift-registry-lite' ),
					'id' => 'share_on_facebook',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on Facebook', 'nm-gift-registry' ) :
					__( 'Allow sharing on Facebook', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
				),
				array(
					'label' => '',
					'id' => 'share_on_twitter',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on X', 'nm-gift-registry' ) :
					__( 'Allow sharing on X', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
					'show_in_group' => true,
				),
				array(
					'label' => '',
					'id' => 'share_on_pinterest',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on Pinterest', 'nm-gift-registry' ) :
					__( 'Allow sharing on Pinterest', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
					'show_in_group' => true,
				),
				array(
					'label' => '',
					'id' => 'share_on_whatsapp',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on WhatsApp', 'nm-gift-registry' ) :
					__( 'Allow sharing on WhatsApp', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
					'show_in_group' => true,
				),
				array(
					'label' => '',
					'id' => 'share_on_email',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on Email', 'nm-gift-registry' ) :
					__( 'Allow sharing on Email', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
					'show_in_group' => true,
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Allow guests to create wishlists', 'nm-gift-registry' ) :
					__( 'Allow guests to create wishlists', 'nm-gift-registry-lite' ),
					'id' => 'allow_guest_wishlists',
					'default' => '',
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Guests can create and manage wishlists just like logged in users', 'nm-gift-registry' ) :
					__( 'Guests can create and manage wishlists just like logged in users', 'nm-gift-registry-lite' ),
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Allow users to have multiple wishlists', 'nm-gift-registry' ) :
					__( 'Allow users to have multiple wishlists', 'nm-gift-registry-lite' ),
					'id' => 'allow_multiple_wishlists',
					'default' => '',
					'pro' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'If not checked, each user can only have one wishlist', 'nm-gift-registry' ) :
					__( 'If not checked, each user can only have one wishlist', 'nm-gift-registry-lite' ),
				),
			)
		);

		$sections[ 'add_to_wishlist' ] = array(
			'title' => '',
			'description' => '',
			'section' => 'add_to_wishlist',
			'fields' => array(
				array(
					'type' => 'heading',
					'label' => nmgr()->is_pro ?
					__( 'Button display options', 'nm-gift-registry' ) :
					__( 'Button display options', 'nm-gift-registry-lite' ),
					'desc' => nmgr()->is_pro ?
					__( 'Set the display options for the add to wishlist button.', 'nm-gift-registry' ) :
					__( 'Set the display options for the add to wishlist button.', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'add_to_wishlist_button_type',
					'label' => nmgr()->is_pro ?
					__( 'Display type', 'nm-gift-registry' ) :
					__( 'Display type', 'nm-gift-registry-lite' ),
					'type' => 'radio_with_image',
					'inline' => true,
					'default' => 'button',
					'options' => array(
						'button' => array(
							'label' => nmgr()->is_pro ?
							__( 'Button', 'nm-gift-registry' ) :
							__( 'Button', 'nm-gift-registry-lite' ),
							'image' => sprintf(
								'<div class="button">%s</div>',
								nmgr()->is_pro ?
								__( 'Add to wishlist', 'nm-gift-registry' ) :
								__( 'Add to wishlist', 'nm-gift-registry-lite' )
							),
							'label_title' => nmgr()->is_pro ?
							__( 'Use a standard button.', 'nm-gift-registry' ) :
							__( 'Use a standard button.', 'nm-gift-registry-lite' ),
						),
						'icon-heart' => array(
							'label' => nmgr()->is_pro ?
							__( 'Icon', 'nm-gift-registry' ) :
							__( 'Icon', 'nm-gift-registry-lite' ),
							'label_title' => nmgr()->is_pro ?
							__( 'Use the heart icon.', 'nm-gift-registry' ) :
							__( 'Use the heart icon.', 'nm-gift-registry-lite' ),
							'image' => '<img src="' . nmgr()->url . 'assets/svg/heart-empty.svg">',
						),
						'custom' => array(
							'label' => nmgr()->is_pro ?
							__( 'Custom html', 'nm-gift-registry' ) :
							'<span>' . __( 'Custom html', 'nm-gift-registry-lite' ) . ' <strong>' . $this->get_pro_version_text() . '</strong></span>',
							'pro' => 1,
							'label_title' => nmgr()->is_pro ?
							__( 'You can use any html normally accepted in post content as the button, only add the class <code>nmgr-add-to-wishlist-button</code> to it. To optionally show the default icon animation, also add the class <code>nmgr-animate</code> to it. See the documentation for more options.', 'nm-gift-registry' ) :
							__( 'You can use any html normally accepted in post content as the button, only add the class <code>nmgr-add-to-wishlist-button</code> to it. To optionally show the default icon animation, also add the class <code>nmgr-animate</code> to it. See the documentation for more options.', 'nm-gift-registry-lite' ),
							'image' => sprintf( '<textarea name="nmgr_settings[%s]" cols="30">%s</textarea>',
								'add_to_wishlist_button_custom_html',
								$this->get_option( 'add_to_wishlist_button_custom_html' )
							),
						),
					),
				),
				array(
					'id' => 'add_to_wishlist_button_position_archive',
					'label' => nmgr()->is_pro ?
					__( 'Button display position on archive pages', 'nm-gift-registry' ) :
					__( 'Button display position on archive pages', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'default' => 'woocommerce_after_shop_loop_item',
					'options' => array(
						'' => nmgr()->is_pro ?
						__( 'None', 'nm-gift-registry' ) :
						__( 'None', 'nm-gift-registry' ),
						'woocommerce_before_shop_loop_item' => nmgr()->is_pro ?
						__( 'Before thumbnail', 'nm-gift-registry' ) :
						__( 'Before thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_top_left' => nmgr()->is_pro ?
						__( 'Top left of thumbnail', 'nm-gift-registry' ) :
						__( 'Top left of thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_top_right' => nmgr()->is_pro ?
						__( 'Top right of thumbnail', 'nm-gift-registry' ) :
						__( 'Top right of thumbnail', 'nm-gift-registry-lite' ),
						'woocommerce_before_shop_loop_item_title' => nmgr()->is_pro ?
						__( 'Before title', 'nm-gift-registry' ) :
						__( 'Before title', 'nm-gift-registry-lite' ),
						'woocommerce_shop_loop_item_title' => nmgr()->is_pro ?
						__( 'After title', 'nm-gift-registry' ) :
						__( 'After title', 'nm-gift-registry-lite' ),
						'woocommerce_after_shop_loop_item_title' => nmgr()->is_pro ?
						__( 'After price', 'nm-gift-registry' ) :
						__( 'After price', 'nm-gift-registry-lite' ),
						'woocommerce_after_shop_loop_item' => nmgr()->is_pro ?
						__( 'After add to cart button', 'nm-gift-registry' ) :
						__( 'After add to cart button', 'nm-gift-registry-lite' ),
					),
				),
				array(
					'id' => 'add_to_wishlist_button_position_single',
					'label' => nmgr()->is_pro ?
					__( 'Button display position on single pages', 'nm-gift-registry' ) :
					__( 'Button display position on single pages', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'default' => 35,
					'options' => array(
						'' => nmgr()->is_pro ?
						__( 'None', 'nm-gift-registry' ) :
						__( 'None', 'nm-gift-registry' ),
						'woocommerce_before_single_product_summary' => nmgr()->is_pro ?
						__( 'Before thumbnail', 'nm-gift-registry' ) :
						__( 'Before thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_top_left' => nmgr()->is_pro ?
						__( 'Top left of thumbnail', 'nm-gift-registry' ) :
						__( 'Top left of thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_top_right' => nmgr()->is_pro ?
						__( 'Top right of thumbnail', 'nm-gift-registry' ) :
						__( 'Top right of thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_bottom_left' => nmgr()->is_pro ?
						__( 'Bottom left of thumbnail', 'nm-gift-registry' ) :
						__( 'Bottom left of thumbnail', 'nm-gift-registry-lite' ),
						'thumbnail_bottom_right' => nmgr()->is_pro ?
						__( 'Bottom right of thumbnail', 'nm-gift-registry' ) :
						__( 'Bottom right of thumbnail', 'nm-gift-registry-lite' ),
						1 => nmgr()->is_pro ?
						__( 'Before title', 'nm-gift-registry' ) :
						__( 'Before title', 'nm-gift-registry-lite' ),
						6 => nmgr()->is_pro ?
						__( 'After title', 'nm-gift-registry' ) :
						__( 'After title', 'nm-gift-registry-lite' ),
						15 => nmgr()->is_pro ?
						__( 'After price', 'nm-gift-registry' ) :
						__( 'After price', 'nm-gift-registry-lite' ),
						25 => nmgr()->is_pro ?
						__( 'After excerpt', 'nm-gift-registry' ) :
						__( 'After excerpt', 'nm-gift-registry-lite' ),
						35 => nmgr()->is_pro ?
						__( 'After add to cart button', 'nm-gift-registry' ) :
						__( 'After add to cart button', 'nm-gift-registry-lite' ),
						45 => nmgr()->is_pro ?
						__( 'After meta information', 'nm-gift-registry' ) :
						__( 'After meta information', 'nm-gift-registry-lite' ),
					),
				),
				array(
					'type' => 'heading',
					'label' => nmgr()->is_pro ?
					__( 'Additional options', 'nm-gift-registry' ) :
					__( 'Additional options', 'nm-gift-registry-lite' ),
					'desc' => nmgr()->is_pro ?
					__( 'Set up additional add to wishlist options', 'nm-gift-registry' ) :
					__( 'Set up additional add to wishlist options', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'default_wishlist_title',
					'label' => nmgr()->is_pro ?
					__( 'Default wishlist title', 'nm-gift-registry' ) :
					__( 'Default wishlist title', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'default' => nmgr()->is_pro ?
					__( 'Gift Registry {wishlist_id}', 'nm-gift-registry' ) :
					__( 'Gift Registry {wishlist_id}', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'Gift Registry {wishlist_id}', 'nm-gift-registry' ) :
					__( 'Gift Registry {wishlist_id}', 'nm-gift-registry-lite' ),
					'desc_tip' => nmgr()->is_pro ?
					__( ' Leave empty if you want users without a wishlist to create one themselves. Available placeholders: {wishlist_type_title}, {wishlist_id}, {site_title}.', 'nm-gift-registry' ) :
					__( ' Leave empty if you want users without a wishlist to create one themselves. Available placeholders: {wishlist_type_title}, {wishlist_id}, {site_title}.', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'add_to_wishlist_button_text',
					'label' => nmgr()->is_pro ?
					__( 'Add to wishlist button text', 'nm-gift-registry' ) :
					__( 'Add to wishlist button text', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'default' => nmgr()->is_pro ?
					__( 'Add to gift registry', 'nm-gift-registry' ) :
					__( 'Add to gift registry', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'Add to gift registry', 'nm-gift-registry' ) :
					__( 'Add to gift registry', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'add_to_new_wishlist_button_text',
					'label' => nmgr()->is_pro ?
					__( 'Add to new wishlist button text', 'nm-gift-registry' ) :
					__( 'Add to new wishlist button text', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'pro' => 1,
					'default' => nmgr()->is_pro ?
					__( 'Add to new gift registry', 'nm-gift-registry' ) :
					__( 'Add to new gift registry', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'Add to new gift registry', 'nm-gift-registry' ) :
					__( 'Add to new gift registry', 'nm-gift-registry-lite' ),
					'desc_tip' => nmgr()->is_pro ?
					__( 'When adding an item to the wishlist the user can create a new wishlist to add the item to instead of using an existing wishlist. This only applies if users are allowed to have multiple wishlists. Available placeholders: {wishlist_type_title}.', 'nm-gift-registry' ) :
					__( 'When adding an item to the wishlist the user can create a new wishlist to add the item to instead of using an existing wishlist. This only applies if users are allowed to have multiple wishlists. Available placeholders: {wishlist_type_title}.', 'nm-gift-registry-lite' ),
				),
				array(
					'type' => 'heading',
					'label' => nmgr()->is_pro ?
					__( 'Products and categories', 'nm-gift-registry' ) :
					__( 'Products and categories', 'nm-gift-registry-lite' ),
					'desc' => nmgr()->is_pro ?
					__( 'Set up the products and categories that should be added to the wishlist', 'nm-gift-registry' ) :
					__( 'Set up the products and categories that should be added to the wishlist', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'add_to_wishlist_include_products',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Allow only these products to be added to the wishlist', 'nm-gift-registry' ) :
					__( 'Allow only these products to be added to the wishlist', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'class' => 'nmgr-product-search',
					'css' => 'min-width:300px;',
					'options' => $this->get_product_select_options( 'add_to_wishlist_include_products' ),
					'default' => '',
					'option_group' => true,
					'custom_attributes' => array(
						'data-nonce' => wp_create_nonce( 'nmgr-search-products' ),
						'data-ajax_url' => admin_url( 'admin-ajax.php' ),
						'data-placeholder' => nmgr()->is_pro ?
						__( 'Search for a product&hellip;', 'nm-gift-registry' ) :
						__( 'Search for a product&hellip;', 'nm-gift-registry-lite' ),
						'data-text-input-too-short' => sprintf(
							/* translators: %d character length */
							__( 'Please enter %d or more characters', 'nm-gift-registry' ),
							3
						),
						'multiple' => 'multiple'
					),
				),
				array(
					'id' => 'add_to_wishlist_exclude_products',
					'label' => nmgr()->is_pro ?
					__( 'Prevent these products from being added to the wishlist', 'nm-gift-registry' ) :
					__( 'Prevent these products from being added to the wishlist', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'pro' => 1,
					'class' => 'nmgr-product-search',
					'css' => 'min-width:300px;',
					'options' => $this->get_product_select_options( 'add_to_wishlist_exclude_products' ),
					'default' => '',
					'option_group' => true,
					'custom_attributes' => array(
						'data-ajax_url' => admin_url( 'admin-ajax.php' ),
						'data-nonce' => wp_create_nonce( 'nmgr-search-products' ),
						'data-placeholder' => nmgr()->is_pro ?
						__( 'Search for a product&hellip;', 'nm-gift-registry' ) :
						__( 'Search for a product&hellip;', 'nm-gift-registry-lite' ),
						'data-text-input-too-short' => sprintf(
							/* translators: %d character length */
							__( 'Please enter %d or more characters', 'nm-gift-registry' ),
							3
						),
						'multiple' => 'multiple'
					),
				),
				array(
					'id' => 'add_to_wishlist_include_categories',
					'label' => nmgr()->is_pro ?
					__( 'Allow only products from these categories to be added to the wishlist', 'nm-gift-registry' ) :
					__( 'Allow only products from these categories to be added to the wishlist', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'pro' => 1,
					'class' => 'wc-enhanced-select',
					'css' => 'min-width:300px;',
					'options' => $this->get_product_category_select_options( 'add_to_wishlist_include_categories' ),
					'default' => '',
					'option_group' => true,
					'custom_attributes' => array(
						'data-placeholder' => nmgr()->is_pro ?
						__( 'All categories', 'nm-gift-registry' ) :
						__( 'All categories', 'nm-gift-registry-lite' ),
						'multiple' => 'multiple'
					),
				),
				array(
					'id' => 'add_to_wishlist_exclude_categories',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Prevent products in these categories from being added to the wishlist', 'nm-gift-registry' ) :
					__( 'Prevent products in these categories from being added to the wishlist', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'css' => 'min-width:300px;',
					'options' => $this->get_product_category_select_options( 'add_to_wishlist_exclude_categories' ),
					'default' => '',
					'option_group' => true,
					'custom_attributes' => array(
						'data-placeholder' => nmgr()->is_pro ?
						__( 'No categories', 'nm-gift-registry' ) :
						__( 'No categories', 'nm-gift-registry-lite' ),
						'multiple' => 'multiple'
					),
				),
			),
		);

		$sections[ 'add_to_cart' ] = array(
			'title' => '',
			'description' => '',
			'section' => 'add_to_cart',
			'fields' => array(
				array(
					'label' => nmgr()->is_pro ?
					__( 'Wishlist item add to cart action', 'nm-gift-registry' ) :
					__( 'Wishlist item add to cart action', 'nm-gift-registry-lite' ),
					'id' => 'ajax_add_to_cart',
					'default' => 1,
					'type' => 'checkbox',
					'pro' => 1,
					'desc' => nmgr()->is_pro ?
					__( 'AJAX enabled', 'nm-gift-registry' ) :
					__( 'AJAX enabled', 'nm-gift-registry-lite' ),
					'desc_tip' => nmgr()->is_pro ?
					__( 'How wishlist items can be added to the cart from the single wishlist page.', 'nm-gift-registry' ) :
					__( 'How wishlist items can be added to the cart from the single wishlist page.', 'nm-gift-registry-lite' ),
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Prevent the cart from having both wishlist items and non-wishlist items', 'nm-gift-registry' ) :
					__( 'Prevent the cart from having both wishlist items and non-wishlist items', 'nm-gift-registry-lite' ),
					'id' => 'cart_prevent_mixed_items',
					'default' => '',
					'type' => 'checkbox',
					'pro' => 1,
					'desc' => nmgr()->is_pro ?
					__( 'Customers cannot add wishlist items to the cart if it has non-wishlist items, and vice-versa.', 'nm-gift-registry' ) :
					__( 'Customers cannot add wishlist items to the cart if it has non-wishlist items, and vice-versa.', 'nm-gift-registry-lite' ),
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Prevent the cart from having items from different wishlists at the same time', 'nm-gift-registry' ) :
					__( 'Prevent the cart from having items from different wishlists at the same time', 'nm-gift-registry-lite' ),
					'id' => 'cart_prevent_multiple_wishlists',
					'default' => '',
					'pro' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Only allow items from one wishlist to be added to the cart at any particular time.', 'nm-gift-registry' ) :
					__( 'Only allow items from one wishlist to be added to the cart at any particular time.', 'nm-gift-registry-lite' ),
				),
			),
		);

		return $sections;
	}

	public function modules_tab_sections() {
		$modules_tab_sections = array();

		$modules_tab_sections[ 'profile_form' ] = array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist profile form fields', 'nm-gift-registry' ) :
			__( 'Wishlist profile form fields', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Set the display type for the wishlist profile form fields.', 'nm-gift-registry' ) :
			__( 'Set the display type for the wishlist profile form fields.', 'nm-gift-registry-lite' ),
			'section' => 'profile_form',
			'fields' => $this->get_profile_form_fields(),
		);

		$modules_tab_sections[ 'items_table' ] = array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist items table', 'nm-gift-registry' ) :
			__( 'Wishlist items table', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Set the visibility of default columns on the items table. For some of these columns their visibility controls related plugin functionality so changing it may adjust how the plugin works. Please see the documentation for full explanation.', 'nm-gift-registry' ) :
			__( 'Set the visibility of default columns on the items table. For some of these columns their visibility controls related plugin functionality so changing it may adjust how the plugin works. Please see the documentation for full explanation.', 'nm-gift-registry-lite' ),
			'section' => 'items_table',
			'fields' => array_merge(
				$this->get_items_table_columns(),
				array(
					array(
						'id' => 'hide_fulfilled_items',
						'label' => nmgr()->is_pro ?
						__( 'Hide fulfilled items from the table on the single wishlist page', 'nm-gift-registry' ) :
						__( 'Hide fulfilled items from the table on the single wishlist page', 'nm-gift-registry-lite' ),
						'type' => 'checkbox',
						'default' => '',
						'desc_tip' => nmgr()->is_pro ?
						__( 'Fulfilled items are items which have all their desired quantities purchased.', 'nm-gift-registry' ) :
						__( 'Fulfilled items are items which have all their desired quantities purchased.', 'nm-gift-registry-lite' ),
					),
				)
			),
		);

		$modules_tab_sections[ 'shipping_section' ] = array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist shipping', 'nm-gift-registry' ) :
			__( 'Wishlist shipping', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Configure the wishlist shipping module. Please note that changing some of these settings may result in other plugin settings being automatically changed if applicable in order to enable the functionality.', 'nm-gift-registry' ) :
			__( 'Configure the wishlist shipping module. Please note that changing some of these settings may result in other plugin settings being automatically changed if applicable in order to enable the functionality.', 'nm-gift-registry-lite' ),
			'section' => 'shipping',
			'fields' => array(
				array(
					'id' => 'enable_shipping',
					'label' => nmgr()->is_pro ?
					__( 'Enable', 'nm-gift-registry' ) :
					__( 'Enable', 'nm-gift-registry-lite' ),
					'type' => 'checkbox',
					'default' => 1,
				),
				array(
					'id' => 'shipping_address_required',
					'label' => nmgr()->is_pro ?
					__( 'Make shipping address required', 'nm-gift-registry' ) :
					__( 'Make shipping address required', 'nm-gift-registry-lite' ),
					'type' => 'checkbox',
					'default' => '',
					'desc' => nmgr()->is_pro ?
					__( 'If checked the user would have to fill in his shipping address before he can add items to his wishlist.', 'nm-gift-registry' ) :
					__( 'If checked the user would have to fill in his shipping address before he can add items to his wishlist.', 'nm-gift-registry-lite' ),
					'desc_tip' => __( 'Automatically enabled if wishlist items are being shipped to the wishlist\'s owner\'s address.', 'nm-gift-registry' ),
					'custom_attributes' => array(
						'disabled' => $this->get_option( 'shipping_to_wishlist_address' ) || !$this->get_option( 'enable_shipping' ) ? 'disabled' : false,
					),
				),
				'shipping_calculate' => array(
					'id' => 'shipping_calculate',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Calculate shipping for wishlist items separately from normal items on cart and checkout pages', 'nm-gift-registry' ) :
					__( 'Calculate shipping for wishlist items separately from normal items on cart and checkout pages', 'nm-gift-registry-lite' ),
					'type' => 'checkbox',
					'default' => '',
					'desc' => nmgr()->is_pro ?
					__( 'This allows separate shipping methods and rates to be used for wishlist and normal items.', 'nm-gift-registry' ) :
					__( 'This allows separate shipping methods and rates to be used for wishlist and normal items.', 'nm-gift-registry-lite' ),
					'desc_tip' => nmgr()->is_pro ?
					__( 'Check this box typically if you want to calculate the shipping costs accurately for the wishlist items based on the wishlist\'s owner\'s shipping address rather than the address of the person buying the items. This setting is automatically enabled if specific registered shipping methods have been chosen for wishlist items.', 'nm-gift-registry' ) :
					__( 'Check this box typically if you want to calculate the shipping costs accurately for the wishlist items based on the wishlist\'s owner\'s shipping address rather than the address of the person buying the items. This setting is automatically enabled if specific registered shipping methods have been chosen for wishlist items.', 'nm-gift-registry-lite' ),
					'custom_attributes' => array(
						'disabled' => !empty( $this->get_option( 'shipping_methods' ) ) ? 'disabled' : false,
					),
				),
			),
		);

		foreach ( $this->get_shipping_methods_settings() as $key => $setting ) {
			$modules_tab_sections[ 'shipping_section' ][ 'fields' ][] = $setting;
		}

		$modules_tab_sections[ 'shipping_section' ][ 'fields' ][] = array(
			'id' => 'shipping_to_wishlist_address',
			'pro' => 1,
			'label' => nmgr()->is_pro ?
			__( 'Ship cart items to the wishlist\'s owner\'s address', 'nm-gift-registry' ) :
			__( 'Ship cart items to the wishlist\'s owner\'s address', 'nm-gift-registry-lite' ),
			'type' => 'checkbox',
			'default' => '',
			'desc' => nmgr()->is_pro ?
			__( 'This allows the main shipping address for the cart to be set to the wishlist\'s owner\'s address if the cart contains wishlist items.', 'nm-gift-registry' ) :
			__( 'This allows the main shipping address for the cart to be set to the wishlist\'s owner\'s address if the cart contains wishlist items.', 'nm-gift-registry-lite' ),
			'desc_tip' => nmgr()->is_pro ?
			__( 'This only works if the cart contains wishlist items. Checking this box would automatically set shipping to a different address on the checkout page  to the wishlist\'s owner\'s address and prevent the customer from modifying this address.', 'nm-gift-registry' ) :
			__( 'This only works if the cart contains wishlist items. Checking this box would automatically set shipping to a different address on the checkout page  to the wishlist\'s owner\'s address and prevent the customer from modifying this address.', 'nm-gift-registry-lite' ),
		);

		foreach ( $this->get_shipping_address_fields() as $key => $setting ) {
			$modules_tab_sections[ 'shipping_section' ][ 'fields' ][] = $setting;
		}

		$modules_tab_sections[ 'shipping_section' ][ 'fields' ][] = array(
			'id' => 'shipping_address_replacement_text',
			'pro' => 1,
			'label' => nmgr()->is_pro ?
			__( 'Replace wishlist\'s owner\'s shipping address on the frontend', 'nm-gift-registry' ) :
			__( 'Replace wishlist\'s owner\'s shipping address on the frontend', 'nm-gift-registry-lite' ),
			'type' => 'textarea',
			'placeholder' => nmgr()->is_pro ?
			__( 'E.g. Items ship to {shipping_address_1}, {shipping_city}, the address of {full_name} and {partner_full_name}, the {wishlist_type_title} owners.', 'nm-gift-registry' ) :
			__( 'E.g. Items ship to {shipping_address_1}, {shipping_city}, the address of {full_name} and {partner_full_name}, the {wishlist_type_title} owners.', 'nm-gift-registry-lite' ),
			'desc_tip' => nmgr()->is_pro ?
			__( 'Use this when you want to override the default display of the wishlist\'s owner\'s shipping address instead of hiding it. Available placeholders: {wishlist_type_title}, {shipping_first_name}, {shipping_last_name}, {shipping_company}, {shipping_address_1}, {shipping_address_2}, {shipping_city}, {shipping_state}, {shipping_postcode}, {shipping_country}, {first_name}, {last_name}, {full_name}, {partner_first_name}, {partner_last_name}, {partner_full_name}, {display_name}, {email}.', 'nm-gift-registry' ) :
			__( 'Use this when you want to override the default display of the wishlist\'s owner\'s shipping address instead of hiding it. Available placeholders: {wishlist_type_title}, {shipping_first_name}, {shipping_last_name}, {shipping_company}, {shipping_address_1}, {shipping_address_2}, {shipping_city}, {shipping_state}, {shipping_postcode}, {shipping_country}, {first_name}, {last_name}, {full_name}, {partner_first_name}, {partner_last_name}, {partner_full_name}, {display_name}, {email}.', 'nm-gift-registry-lite' ),
		);

		$modules_tab_sections[ 'images_section' ] = $this->get_tab_section_images();
		$modules_tab_sections[ 'messages_section' ] = $this->get_tab_section_messages();
		$modules_tab_sections[ 'settings_section' ] = $this->get_tab_section_settings();

		return $modules_tab_sections;
	}

	/**
	 *  Validate custom fields that have not been captured above in the standard tab section fields
	 * @todo make this process more dynamic in the future
	 */
	public function extra_validate_fields_to_save( $input ) {
		$field_1 = 'add_to_wishlist_button_custom_html';
		if ( array_key_exists( $field_1, $input ) ) {
			$input[ $field_1 ] = wp_kses( trim( $input[ $field_1 ] ), nmgr_allowed_post_tags() );
		}

		return $input;
	}

	public function get_shipping_methods_settings() {
		if ( !function_exists( 'wc' ) || !is_a( wc()->shipping, 'WC_Shipping' ) ) {
			return array();
		}

		$shipping_methods = wc()->shipping()->get_shipping_methods();
		$array = array();

		foreach ( $shipping_methods as $id => $object ) {
			$settings_id = 'shipping_method_' . $id;
			$array[ $settings_id ] = array(
				'label' => '',
				'id' => $settings_id,
				'default' => '',
				'option_name' => 'shipping_methods',
				'option_group' => true, // save fields with the same option_name
				'value' => $id,
				'type' => 'checkbox',
				'desc' => $object->get_method_title(),
				'checkboxgroup' => 'shipping_methods',
			);
		}

		if ( !empty( $array ) ) {
			$first = key( $array );

			foreach ( $array as $key => $value ) {
				if ( $first === $key ) {
					$array[ $key ][ 'pro' ] = 1;
					$array[ $key ][ 'label' ] = nmgr()->is_pro ?
						__( 'Registered shipping methods that should only be available for wishlist items in cart and checkout', 'nm-gift-registry' ) :
						__( 'Registered shipping methods that should only be available for wishlist items in cart and checkout', 'nm-gift-registry-lite' );
					$array[ $key ][ 'desc_tip' ] = nmgr()->is_pro ?
						__( 'By default all registered and enabled shipping methods would be available for wishlist items but you can select those which should only be available for them. For example if you want all wishlist items in the cart to be free shipping only, select free shipping. This would then exclude all the other shipping methods from being selected for the wishlist items in the cart.', 'nm-gift-registry' ) :
						__( 'By default all registered and enabled shipping methods would be available for wishlist items but you can select those which should only be available for them. For example if you want all wishlist items in the cart to be free shipping only, select free shipping. This would then exclude all the other shipping methods from being selected for the wishlist items in the cart.', 'nm-gift-registry-lite' );
				} else {
					$array[ $key ][ 'show_in_group' ] = true;
				}
			}
		}

		return $array;
	}

	public function get_shipping_address_fields() {
		if ( !function_exists( 'wc' ) || !is_a( wc()->countries, 'WC_Countries' ) ) {
			return array();
		}

		$address_fields = wc()->countries->get_default_address_fields();
		$array = array(
			'shipping_address_hidden_all' => array(
				'pro' => 1,
				'label' => nmgr()->is_pro ?
				__( 'Hide wishlist\'s owner\'s shipping address fields on the frontend', 'nm-gift-registry' ) :
				__( 'Hide wishlist\'s owner\'s shipping address fields on the frontend', 'nm-gift-registry-lite' ),
				'id' => 'shipping_address_hidden_all',
				'default' => '',
				'option_name' => 'shipping_address_hidden',
				'option_group' => true,
				'value' => 'all',
				'type' => 'checkbox',
				'desc' => nmgr()->is_pro ?
				__( 'Hide all fields', 'nm-gift-registry' ) :
				__( 'Hide all fields', 'nm-gift-registry-lite' ),
				'checkboxgroup' => 'shipping_address_hidden',
				'desc_tip' => nmgr()->is_pro ?
				__( 'Hide all or parts of the address fields. The fields would be still visible to the admin in the order screen.', 'nm-gift-registry' ) :
				__( 'Hide all or parts of the address fields. The fields would be still visible to the admin in the order screen.', 'nm-gift-registry-lite' ),
			)
		);

		foreach ( $address_fields as $key => $args ) {
			$settings_id = 'shipping_address_hidden_' . $key;
			$array[ $settings_id ] = array(
				'label' => '',
				'id' => $settings_id,
				'default' => '',
				'option_name' => 'shipping_address_hidden',
				'option_group' => true, // save fields with the same option_name
				'value' => $key,
				'type' => 'checkbox',
				'desc' => isset( $args[ 'label' ] ) ? $args[ 'label' ] : (isset( $args[ 'placeholder' ] ) ? $args[ 'placeholder' ] : $key),
				'show_in_group' => true,
				'checkboxgroup' => 'shipping_address_hidden',
			);
		}

		return $array;
	}

	public function get_tab_section_images() {
		return array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist images', 'nm-gift-registry' ) :
			__( 'Wishlist images', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Set the display type for the wishlist images.', 'nm-gift-registry' ) :
			__( 'Set the display type for the wishlist images.', 'nm-gift-registry-lite' ),
			'section' => 'images',
			'fields' => array(
				array(
					'id' => 'display_image_thumbnail',
					'label' => nmgr()->is_pro ?
					__( 'Thumbnail image', 'nm-gift-registry' ) :
					__( 'Thumbnail image', 'nm-gift-registry-lite' ),
					'pro' => 1,
					'type' => 'select',
					'options' => array(
						'square' => nmgr()->is_pro ?
						__( 'Square', 'nm-gift-registry' ) :
						__( 'Square', 'nm-gift-registry-lite' ),
						'circle' => nmgr()->is_pro ?
						__( 'Circle', 'nm-gift-registry' ) :
						__( 'Circle', 'nm-gift-registry-lite' ),
						'no' => nmgr()->is_pro ?
						__( 'Hidden', 'nm-gift-registry' ) :
						__( 'Hidden', 'nm-gift-registry-lite' ),
					),
					'default' => 'square',
				),
				array(
					'id' => 'display_image_thumbnail_position',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Thumbnail image position against background image', 'nm-gift-registry' ) :
					__( 'Thumbnail image position against background image', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'options' => array(
						'center' => nmgr()->is_pro ?
						__( 'Center', 'nm-gift-registry' ) :
						__( 'Center', 'nm-gift-registry-lite' ),
						'left' => nmgr()->is_pro ?
						__( 'Left', 'nm-gift-registry' ) :
						__( 'Left', 'nm-gift-registry-lite' ),
						'right' => nmgr()->is_pro ?
						__( 'Right', 'nm-gift-registry' ) :
						__( 'Right', 'nm-gift-registry-lite' )
					),
					'default' => 'center',
				),
				array(
					'id' => 'display_image_background',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Background image', 'nm-gift-registry' ) :
					__( 'Background image', 'nm-gift-registry-lite' ),
					'type' => 'select',
					'options' => array(
						'yes' => nmgr()->is_pro ?
						__( 'Visible', 'nm-gift-registry' ) :
						__( 'Visible', 'nm-gift-registry-lite' ),
						'no' => nmgr()->is_pro ?
						__( 'Hidden', 'nm-gift-registry' ) :
						__( 'Hidden', 'nm-gift-registry-lite' )
					),
					'default' => 'yes',
				),
			),
		);
	}

	public function get_tab_section_messages() {
		return array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist messages', 'nm-gift-registry' ) :
			__( 'Wishlist messages', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Configure the wishlist messages module.', 'nm-gift-registry' ) :
			__( 'Configure the wishlist messages module.', 'nm-gift-registry-lite' ),
			'section' => 'messages',
			'fields' => array(
				array(
					'id' => 'enable_messages',
					'pro' => 1,
					'label' => nmgr()->is_pro ?
					__( 'Enable', 'nm-gift-registry' ) :
					__( 'Enable', 'nm-gift-registry-lite' ),
					'type' => 'checkbox',
					'default' => 1,
					'desc' => nmgr()->is_pro ?
					__( 'Unchecking this disables the messages module completely for the site so guests would not be able to send messages to the wishlist\'s owner from the checkout page and messages would be excluded from emails.', 'nm-gift-registry' ) :
					__( 'Unchecking this disables the messages module completely for the site so guests would not be able to send messages to the wishlist\'s owner from the checkout page and messages would be excluded from emails.', 'nm-gift-registry-lite' ),
				),
			),
		);
	}

	public function get_tab_section_settings() {
		$settings_fields = array();
		$form = new \NMGR_Form();
		$form->set_type( $this->get_type() );
		$fields = $form->get_fields( 'settings', '', false, false );

		foreach ( $fields as $key => $args ) {
			if ( false === ($args[ 'show_in_settings' ] ?? true) ) {
				continue;
			}

			$composed_field = array(
				'id' => "display_form_{$key}",
				'label' => isset( $args[ 'label' ] ) ?
				str_ireplace( nmgr_get_type_title( '', false, $this->get_type() ),
					(nmgr()->is_pro ? __( 'wishlist', 'nm-gift-registry' ) : __( 'wishlist', 'nm-gift-registry-lite' ) ),
					$args[ 'label' ]
				) :
				'',
				'type' => 'select',
				'pro' => 1,
				'options' => array(
					'yes' => nmgr()->is_pro ?
					__( 'Visible', 'nm-gift-registry' ) :
					__( 'Visible', 'nm-gift-registry-lite' ),
					'no' => nmgr()->is_pro ?
					__( 'Hidden', 'nm-gift-registry' ) :
					__( 'Hidden', 'nm-gift-registry-lite' )
				),
				'default' => 'yes',
			);

			switch ( $key ) {
				case 'exclude_from_search':
					$composed_field[ 'desc_tip' ] = nmgr()->is_pro ?
						__( 'If hidden, the user would not be able to exclude his individual wishlist from appearing in the search results. Only the admin would be able to do this from the backend.', 'nm-gift-registry' ) :
						__( 'If hidden, the user would not be able to exclude his individual wishlist from appearing in the search results. Only the admin would be able to do this from the backend.', 'nm-gift-registry-lite' );
					break;
				case 'delete_wishlist':
					$composed_field[ 'desc_tip' ] = nmgr()->is_pro ?
						__( 'If hidden, the user would not be able to delete his wishlist from the frontend. Only the admin would be able to do it from the backend.', 'nm-gift-registry' ) :
						__( 'If hidden, the user would not be able to delete his wishlist from the frontend. Only the admin would be able to do it from the backend.', 'nm-gift-registry-lite' );
					break;
			}

			$settings_fields[] = $composed_field;
		}

		return array(
			'title' => nmgr()->is_pro ?
			__( 'Wishlist settings form fields', 'nm-gift-registry' ) :
			__( 'Wishlist settings form fields', 'nm-gift-registry-lite' ),
			'description' => nmgr()->is_pro ?
			__( 'Set the visibility of the wishlist settings form fields.', 'nm-gift-registry' ) :
			__( 'Set the visibility of the wishlist settings form fields.', 'nm-gift-registry-lite' ),
			'section' => 'settings_form',
			'fields' => $settings_fields
		);
	}

	/**
	 * Get the option key value pair for a select field that gets product ids
	 *
	 * @param string $option_name The name of the plugin option
	 * @return array
	 */
	public function get_product_select_options( $option_name ) {
		$options = array();
		if ( did_action( 'woocommerce_init' ) && function_exists( 'wc_get_product' ) ) {
			$product_ids = ( array ) $this->get_option( $option_name );
			foreach ( $product_ids as $id ) {
				$product = wc_get_product( $id );
				if ( is_object( $product ) ) {
					$options[ $id ] = $product->get_formatted_name();
				}
			}
		}
		return $options;
	}

	/**
	 * Get the option key value pair for a select field that gets product category ids
	 *
	 * @param string $option_name The name of the plugin option
	 * @return array
	 */
	public function get_product_category_select_options() {
		$options = array();
		$categories = get_terms( 'product_cat', 'orderby=name&hide_empty=0' );

		if ( $categories && !is_wp_error( $categories ) ) {
			foreach ( $categories as $cat ) {
				$options[ $cat->term_id ] = $cat->name;
			}
		}
		return $options;
	}

	public function emails_tab_sections() {
		$sections = array(
			'email_admin_new_wishlist' => array(
				'title' => __( 'New wishlist', 'nm-gift-registry' ),
				'description' => __( 'Email the chosen recipient(s) when a new wishlist is created.', 'nm-gift-registry' ),
				'is_customer_email' => false,
				'fields' => array(
					array(
						'id' => 'email_admin_new_wishlist_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
					),
					array(
						'id' => 'email_admin_new_wishlist_recipient',
						'label' => __( 'Recipient(s)', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: admin email */
							__( 'Recipients should be separated by a comma. Defaults to %s.', 'nm-gift-registry' ), esc_attr( get_option( 'admin_email' ) ) ),
						'default' => get_option( 'admin_email' ),
					),
					array(
						'id' => 'email_admin_new_wishlist_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: New %s', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_admin_new_wishlist_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( 'New %s: {wishlist_title}', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'c' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_admin_new_wishlist_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_new_wishlist' => array(
				'title' => __( 'New wishlist', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when he has created a new wishlist.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_new_wishlist_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
					),
					array(
						'id' => 'email_customer_new_wishlist_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: Your new %s has been created', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_new_wishlist_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( 'New %s: {wishlist_title}', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'c' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_new_wishlist_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_ordered_items' => array(
				'title' => __( 'Ordered items', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when items have been ordered at the checkout for his wishlist. They may not have been paid for or marked as processing or completed by this time.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_ordered_items_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
					),
					array(
						'id' => 'email_customer_ordered_items_checkout_message',
						'label' => __( 'Show checkout message', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'desc' => __( 'If a message was sent to the wishlist\'s owner at checkout during the order, show it in this email', 'nm-gift-registry' ),
						'desc_tip' => __( 'This only works if the "messages" module is enabled.', 'nm-gift-registry' ),
						'custom_attributes' => array(
							'disabled' => $this->get_option( 'enable_messages', 1 ) ? false : true,
						),
						'default' => 1,
					),
					array(
						'id' => 'email_customer_ordered_items_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: Items have been ordered for your %s', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_ordered_items_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => __( 'New Ordered Items: {wishlist_title}', 'nm-gift-registry' ),
						'default' => '',
					),
					array(
						'id' => 'email_customer_ordered_items_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_purchased_items' => array(
				'title' => __( 'Purchased items', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when items ordered for his wishlist have been paid for. They are marked as processing or completed at this time. This only works if the "purchased quantity" column on the items table is visible.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_purchased_items_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
						'desc_tip' => __( 'This would be automatically disabled if the "purchased quantity" column on the items table is hidden.' ),
					),
					array(
						'id' => 'email_customer_purchased_items_checkout_message',
						'label' => __( 'Show checkout message', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'desc' => __( 'If a message was sent to the wishlist\'s owner at checkout during the order, show it in this email', 'nm-gift-registry' ),
						'desc_tip' => __( 'This only works if the "messages" module is enabled.', 'nm-gift-registry' ),
						'custom_attributes' => array(
							'disabled' => $this->get_option( 'enable_messages', 1 ) ? false : true,
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_purchased_items_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: Items have been purchased for your %s', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_purchased_items_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => __( 'New Purchased Items: {wishlist_title}', 'nm-gift-registry' ),
						'default' => '',
					),
					array(
						'id' => 'email_customer_purchased_items_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_refunded_items' => array(
				'title' => __( 'Refunded items', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when items purchased for his wishlist in an order have been refunded. This means that the stock of the items in the order has been reduced. This only works if the "purchased quantity" column on the items table is visible.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_refunded_items_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
						'desc_tip' => __( 'This would be automatically disabled if the "purchased quantity" column on the items table is hidden.', 'nm-gift-registry' ),
					),
					array(
						'id' => 'email_customer_refunded_items_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: %s items refunded', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'cf' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_refunded_items_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => __( 'Refunded Items: {wishlist_title}', 'nm-gift-registry' ),
						'default' => '',
					),
					array(
						'id' => 'email_customer_refunded_items_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_fulfilled_wishlist' => array(
				'title' => __( 'Fulfilled wishlist', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when all items in his wishlist have been purchased. This only works if the "quantity" and "purchased quantity" columns on the items table are visible.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_fulfilled_wishlist_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
						'desc_tip' => __( 'This would be automatically disabled if the "quantity" and "purchased quantity" columns on the items table are hidden.' ),
					),
					array(
						'id' => 'email_customer_fulfilled_wishlist_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: Your %s has been fulfilled', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_fulfilled_wishlist_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}'
						),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( 'Fulfilled %s: {wishlist_title}', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'c' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_fulfilled_wishlist_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_admin_fulfilled_wishlist' => array(
				'title' => __( 'Fulfilled wishlist', 'nm-gift-registry' ),
				'description' => __( 'Email the chosen recipients when all items in a customer\'s wishlist have been purchased. This only works if the "quantity" and "purchased quantity" columns on the items table are visible.', 'nm-gift-registry' ),
				'is_customer_email' => false,
				'fields' => array(
					array(
						'id' => 'email_admin_fulfilled_wishlist_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
						'desc_tip' => __( 'This would be automatically disabled if the "quantity" and "purchased quantity" columns on the items table are hidden.' ),
					),
					array(
						'id' => 'email_admin_fulfilled_wishlist_recipient',
						'label' => __( 'Recipient(s)', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: admin email */
							__( 'Recipients should be separated by a comma. Defaults to %s.', 'nm-gift-registry' ), esc_attr( get_option( 'admin_email' ) ) ),
						'default' => get_option( 'admin_email' ),
					),
					array(
						'id' => 'email_admin_fulfilled_wishlist_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: A %s has been fulfilled', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_admin_fulfilled_wishlist_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( 'Fulfilled %s: {wishlist_title}', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'c' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_admin_fulfilled_wishlist_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_new_message' => array(
				'title' => __( 'New message', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when a new message has been sent to him from the checkout page during an order. This only works if the "messages" module is enabled.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_new_message_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
						'desc_tip' => __( 'This would be automatically disabled if the "messages" module is disabled.', 'nm-gift-registry' ),
						'custom_attributes' => array(
							'disabled' => $this->get_option( 'enable_messages', 1 ) ? false : true,
						),
					),
					array(
						'id' => 'email_customer_new_message_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: New %s message', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_new_message_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => __( 'New Message: {wishlist_title}', 'nm-gift-registry' ),
						'default' => '',
					),
					array(
						'id' => 'email_customer_new_message_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
			'email_customer_deleted_wishlist' => array(
				'title' => __( 'Deleted wishlist', 'nm-gift-registry' ),
				'description' => __( 'Email the customer when he has deleted his wishlist. This email is only sent if the wishlist is deleted from the frontend.', 'nm-gift-registry' ),
				'is_customer_email' => true,
				'fields' => array(
					array(
						'id' => 'email_customer_deleted_wishlist_enabled',
						'label' => __( 'Enable/Disable', 'nm-gift-registry' ),
						'type' => 'checkbox',
						'default' => 1,
					),
					array(
						'id' => 'email_customer_deleted_wishlist_subject',
						'label' => __( 'Subject', 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( '[{site_title}]: Deleted %s', 'nm-gift-registry' ), esc_html( nmgr_get_type_title() )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_deleted_wishlist_heading',
						'label' => __( "Email heading", 'nm-gift-registry' ),
						'type' => 'text',
						'desc_tip' => sprintf(
							/* translators: %s: Available placeholders for use */
							__( 'Available placeholders: %s.', 'nm-gift-registry' ), '{site_title}, {wishlist_title}, {wishlist_type_title}' ),
						'placeholder' => sprintf(
							/* translators: %s: wishlist type title */
							__( 'Deleted %s: {wishlist_title}', 'nm-gift-registry' ), esc_html( nmgr_get_type_title( 'c' ) )
						),
						'default' => '',
					),
					array(
						'id' => 'email_customer_deleted_wishlist_email_type',
						'label' => __( "Email type", 'nm-gift-registry' ),
						'type' => 'select',
						'desc_tip' => __( 'Choose which format of email to send.', 'nm-gift-registry' ),
						'class' => 'email_type wc-enhanced-select',
						'options' => self::get_email_type_options(),
						'default' => __( 'html', 'nm-gift-registry' ),
					),
				)
			),
		);

		foreach ( $sections as $key => $args ) {
			foreach ( array_keys( $args[ 'fields' ] ?? [] ) as $fields_key ) {
				$sections[ $key ][ 'fields' ][ $fields_key ][ 'pro' ] = 1;
			}
		}
		return $sections;
	}

	public function do_settings_sections_emails() {
		printf( "<h2>%s</h2>", esc_html__( 'Email notifications', 'nm-gift-registry' ) );

		echo "<p>" . sprintf(
			/* translators: %s: Plugin name */
			esc_html__( 'Emails sent by %s are listed below. Click on an email to configure it.', 'nm-gift-registry' ), esc_html( nmgr()->name ) ) . "</p>";
		?>
		<p><?php
			/* translators: %s: Woocommerce email settings page */
			printf( wp_kses_post( __( 'Please note that these emails are sent using the sender options and template options set in WooCommerce. To configure these settings, go <a href="%s">here</a>.', 'nm-gift-registry' ) ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=email#email_template_options-description' ) ) );
			?></p>
		<table class="form-table <?php echo (!nmgr()->is_pro) ? 'is-pro' : ''; ?>" role="presentation">
			<tr>
				<td class="wc_emails_wrapper">
					<table class="wc_emails widefat" cellspacing="0">
						<colgroup>
							<col width="5%">
							<col width="25%">
							<col width="25%">
							<col width="25%">
							<col width="20%">
						</colgroup>
						<thead>
							<tr>
								<?php
								$columns = apply_filters(
									'nmgr_email_setting_columns', array(
									'status' => '',
									'name' => __( 'Email', 'nm-gift-registry' ),
									'email_type' => __( 'Content type', 'nm-gift-registry' ),
									'recipient' => __( 'Recipient(s)', 'nm-gift-registry' ),
									'actions' => '',
									)
								);
								foreach ( $columns as $key => $column ) {
									echo '<th class="wc-email-settings-table-' . esc_attr( $key ) . '">' . esc_html( $column ) . '</th>';
								}
								?>
							</tr>
						</thead>
						<tbody id='nmgr-emails'>
							<?php
							$email_sections = $this->get_tab_sections();
							foreach ( $email_sections as $section_id => $section_args ) {
								// Let's extract individual fields
								$recipient = $this->get_email_section_field( 'recipient', $section_id );
								$enabled = $this->get_email_section_field( 'enabled', $section_id );
								$email_type = $this->get_email_section_field( 'email_type', $section_id );

								echo '<tr class="nmgr-accordion-header" style="cursor:pointer;">';

								foreach ( $columns as $key => $column ) {

									switch ( $key ) {
										case 'name':
											echo '<td class="wc-email-settings-table-' . esc_attr( $key ) . '">'
											. '<a href="#' . esc_attr( $section_id ) . '">'
											. esc_html( isset( $section_args[ 'title' ] ) ? esc_html( $section_args[ 'title' ] ) : '' )
											. '</a>'
											. (isset( $section_args[ 'description' ] ) ? wc_help_tip( $section_args[ 'description' ] ) : null)
											. '</td>';
											break;
										case 'recipient':
											echo '<td class="wc-email-settings-table-' . esc_attr( $key ) . '">'
											. esc_html( $section_args[ 'is_customer_email' ] ? __( 'Customer', 'nm-gift-registry' ) : $this->get_option( "{$section_id}_recipient", !empty( $recipient ) && isset( $recipient[ 'default' ] ) ? $recipient[ 'default' ] : null ) )
											. '</td>';
											break;
										case 'status':
											echo '<td class="wc-email-settings-table-' . esc_attr( $key ) . '">';
											if ( $this->get_option( "{$section_id}_enabled", !empty( $enabled ) && isset( $enabled[ 'default' ] ) ? $enabled[ 'default' ] : null ) ) {
												echo '<span class="status-enabled tips" data-tip="' . esc_attr__( 'Enabled', 'nm-gift-registry' ) . '">' . esc_html__( 'Yes', 'nm-gift-registry' ) . '</span>';
											} else {
												echo '<span class="status-disabled tips" data-tip="' . esc_attr__( 'Disabled', 'nm-gift-registry' ) . '">-</span>';
											}
											echo '</td>';
											break;
										case 'email_type':
											echo '<td class="wc-email-settings-table-' . esc_attr( $key ) . '">';
											if ( class_exists( Email::class ) ) {
												$email_class = new Email( $section_id );
												$default_value = !empty( $email_type ) && isset( $email_type[ 'default' ] ) ?
													$email_type[ 'default' ] : null;
												$email_class->email_type = $this->get_option( "{$section_id}_email_type", $default_value );
												echo esc_html( $email_class->get_content_type() );
											}
											echo '</td>';
											break;
										case 'actions':
											echo '<td class="wc-email-settings-table-' . esc_attr( $key ) . '">'
											. '<button class="button alignright">'
											. esc_html__( 'Manage', 'nm-gift-registry' )
											. '</button>'
											. '</td>';
											break;
										default:
											do_action( 'nmgr_email_setting_column_' . $key, $section_args );
											break;
									}
								}
								echo '</tr>';

								// Show the settings form for this section
								echo '<tr>'
								. '<td></td>'
								. '<td colspan = "4">'
								. '<table class="form-table section-fields" role="presentation">';
								do_settings_fields( $this->current_tab, $section_id );
								echo '</table>'
								. '</td>'
								. '</tr>';
							}
							?>
						</tbody>
					</table>
				</td>
			</tr>
		</table>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				if ('function' === typeof jQuery) {
					jQuery("#nmgr-emails").accordion({
						header: '.nmgr-accordion-header',
						collapsible: true,
						active: false,
						icons: false,
						create: function () {
							jQuery('.nmgr-accordion-header').removeClass('ui-accordion-header ui-state-default');
						}
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Extract an individual field from the emails section's defined fields
	 *
	 * @param string $key The suffix of the field id
	 * @param string $section_id The id of the section
	 * @return array
	 */
	public function get_email_section_field( $key, $section_id ) {
		$email_sections = $this->get_tab_sections( 'emails' );
		if ( isset( $email_sections[ $section_id ] ) ) {
			$fields = $email_sections[ $section_id ][ 'fields' ];
			$field = array_filter( $fields, function ( $val ) use ( $key, $section_id ) {
				return isset( $val[ 'id' ] ) && $val[ 'id' ] == "{$section_id}_{$key}";
			} );
			return array_shift( $field );
		}
	}

	/**
	 * Email type options.
	 *
	 * This should be an exact copy of WooCommerce's email type options
	 * @see WC_Email->get_email_type_options()
	 *
	 * @return array
	 */
	public static function get_email_type_options() {
		$types = array( 'plain' => __( 'Plain text', 'nm-gift-registry' ) );

		if ( class_exists( 'DOMDocument' ) ) {
			$types[ 'html' ] = __( 'HTML', 'nm-gift-registry' );
			$types[ 'multipart' ] = __( 'Multipart', 'nm-gift-registry' );
		}

		return $types;
	}

}
