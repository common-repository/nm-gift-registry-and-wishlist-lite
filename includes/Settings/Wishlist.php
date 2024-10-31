<?php

namespace NMGR\Settings;

use NMGR\Events\DeleteExtraUserWishlists;
use NMGR\Settings\Admin;

defined( 'ABSPATH' ) || exit;

class Wishlist extends Admin {

	public function get_heading() {
		return nmgr()->is_pro ?
			__( 'Wishlist Settings', 'nm-gift-registry' ) :
			__( 'Wishlist Settings', 'nm-gift-registry-lite' );
	}

	public function get_type() {
		return 'wishlist';
	}

	public function page_slug() {
		return 'nmgr-wishlist-settings';
	}

	public function update_option( $old_value, $new_value ) {
		if ( array_key_exists( 'wishlist_allow_multiple_wishlists', $old_value ) &&
			$old_value[ 'wishlist_allow_multiple_wishlists' ] != $new_value[ 'wishlist_allow_multiple_wishlists' ] &&
			!$new_value[ 'wishlist_allow_multiple_wishlists' ] ) {
			(new DeleteExtraUserWishlists( $this->get_type() ) )->run();
		}

		if ( array_key_exists( 'wishlist_page_id', $new_value ) ) {
			if ( nmgr_update_pagename( $new_value[ 'wishlist_page_id' ] ?? 0, $this->get_type() ) ) {
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
		];

		return $tabs;
	}

	private function get_wishlist_page_desc() {
		$desc = nmgr()->is_pro ?
			__( 'must contain the shortcode <code>[nmgr_wishlist]</code>', 'nm-gift-registry' ) :
			__( 'must contain the shortcode <code>[nmgr_wishlist]</code>', 'nm-gift-registry-lite' );

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
					'id' => 'wishlist_enable',
					'default' => 1,
					'type' => 'checkbox',
				),
				array(
					'id' => 'wishlist_page_id',
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
					'desc' => $this->get_wishlist_page_desc(),
					'error_codes' => [
						'no-wishlist-page-id'
					],
				),
				array(
					'label' => nmgr()->is_pro ?
					__( 'Sharing on social networks', 'nm-gift-registry' ) :
					__( 'Sharing on social networks', 'nm-gift-registry-lite' ),
					'id' => 'wishlist_share_on_facebook',
					'default' => 1,
					'type' => 'checkbox',
					'desc' => nmgr()->is_pro ?
					__( 'Allow sharing on Facebook', 'nm-gift-registry' ) :
					__( 'Allow sharing on Facebook', 'nm-gift-registry-lite' ),
					'checkboxgroup' => 'social_share',
				),
				array(
					'label' => '',
					'id' => 'wishlist_share_on_twitter',
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
					'id' => 'wishlist_share_on_pinterest',
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
					'id' => 'wishlist_share_on_whatsapp',
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
					'id' => 'wishlist_share_on_email',
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
					'id' => 'wishlist_allow_guest_wishlists',
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
					'id' => 'wishlist_allow_multiple_wishlists',
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
					'id' => 'wishlist_add_to_wishlist_button_type',
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
								'wishlist_add_to_wishlist_button_custom_html',
								$this->get_option( 'wishlist_add_to_wishlist_button_custom_html' )
							),
						),
					),
				),
				array(
					'id' => 'wishlist_add_to_wishlist_button_position_archive',
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
					'id' => 'wishlist_add_to_wishlist_button_position_single',
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
					'id' => 'wishlist_default_wishlist_title',
					'label' => nmgr()->is_pro ?
					__( 'Default wishlist title', 'nm-gift-registry' ) :
					__( 'Default wishlist title', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'default' => nmgr()->is_pro ?
					__( 'My Wishlist', 'nm-gift-registry' ) :
					__( 'My Wishlist', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'My Wishlist', 'nm-gift-registry' ) :
					__( 'My Wishlist', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'wishlist_add_to_wishlist_button_text',
					'label' => nmgr()->is_pro ?
					__( 'Add to wishlist button text', 'nm-gift-registry' ) :
					__( 'Add to wishlist button text', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'default' => nmgr()->is_pro ?
					__( 'Add to wishlist', 'nm-gift-registry' ) :
					__( 'Add to wishlist', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'Add to wishlist', 'nm-gift-registry' ) :
					__( 'Add to wishlist', 'nm-gift-registry-lite' ),
				),
				array(
					'id' => 'wishlist_add_to_new_wishlist_button_text',
					'label' => nmgr()->is_pro ?
					__( 'Add to new wishlist button text', 'nm-gift-registry' ) :
					__( 'Add to new wishlist button text', 'nm-gift-registry-lite' ),
					'type' => 'text',
					'pro' => 1,
					'default' => nmgr()->is_pro ?
					__( 'Add to new wishlist', 'nm-gift-registry' ) :
					__( 'Add to new wishlist', 'nm-gift-registry-lite' ),
					'placeholder' => nmgr()->is_pro ?
					__( 'Add to new wishlist', 'nm-gift-registry' ) :
					__( 'Add to new wishlist', 'nm-gift-registry-lite' ),
					'desc_tip' => nmgr()->is_pro ?
					__( 'When adding an item to the wishlist the user can create a new wishlist to add the item to instead of using an existing wishlist. This only applies if users are allowed to have multiple wishlists. Available placeholders: {wishlist_type_title}.', 'nm-gift-registry' ) :
					__( 'When adding an item to the wishlist the user can create a new wishlist to add the item to instead of using an existing wishlist. This only applies if users are allowed to have multiple wishlists. Available placeholders: {wishlist_type_title}.', 'nm-gift-registry-lite' ),
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
					'id' => 'wishlist_ajax_add_to_cart',
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
			),
		);

		return $sections;
	}

	/**
	 *  Validate custom fields that have not been captured above in the standard tab section fields
	 * @todo make this process more dynamic in the future
	 */
	public function extra_validate_fields_to_save( $input ) {
		$field_1 = 'wishlist_add_to_wishlist_button_custom_html';
		if ( array_key_exists( $field_1, $input ) ) {
			$input[ $field_1 ] = wp_kses( trim( $input[ $field_1 ] ), nmgr_allowed_post_tags() );
		}

		return $input;
	}

}
