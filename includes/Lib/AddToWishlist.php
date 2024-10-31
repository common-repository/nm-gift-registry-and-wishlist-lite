<?php

namespace NMGR\Lib;

use NMGR\Fields\Fields;
use NMGR\Tables\Table;
use NMGR\Lib\Single;

defined( 'ABSPATH' ) || exit;

class AddToWishlist {

	public function run() {
		add_action( 'nmgr_post_action', [ $this, 'ajax_post_actions' ] );
	}

	public function ajax_post_actions( $args ) {
		switch ( $args[ 'post_action' ] ) {
			case 'atw_button_clicked':
				$this->add_button_clicked();
				break;
			case 'atw_get_section':
				$this->get_section();
				break;
			case 'atw_submit_dialog':
				$this->dialog_submitted();
				break;
		}
	}

	public function add_button_clicked() {
		$args = [];
		$dataset = $_POST[ 'dataset' ];
		$type = sanitize_text_field( $dataset[ 'type' ] );
		$product_id = json_decode( wp_unslash( $dataset[ 'product_id' ] ), true );
		$args[ 'data' ][ 'btn_id' ] = $product_id;

		if ( !empty( $dataset[ 'extra_data' ] ) ) {
			$extra = [];
			parse_str( $dataset[ 'extra_data' ], $extra );

			$product_id = !empty( $extra[ 'variation_id' ] ) ? ( int ) $extra[ 'variation_id' ] : $product_id;
			$variations = [];
			foreach ( $extra as $key => $attr ) {
				if ( false !== strpos( $key, 'attribute_' ) ) {
					$variations[ sanitize_text_field( $key ) ] = sanitize_text_field( $attr );
				}
			}

			$args[ 'data' ][ 'variations' ][ $product_id ] = $variations;
		}

		$this->start( $product_id, $type, $args );
	}

	/**
	 * This begins the complete add to wishlist process which includes
	 * - Creating a wishlist if none exists
	 * - Displaying the dialog if necessary
	 * @param int|array $product_id Ids of products to add to the wishlist
	 * @param string $type Wishlist type
	 * @param string $btn_id data-id attribute of the button that was clicked to
	 * begin the add to wishlist process.
	 * This data would be kept persistent in the dialog if it is showing.
	 */
	public function start( $product_id, $type, $data = [] ) {
		$product_ids = array_map( 'absint', (is_array( $product_id ) ? $product_id : [ $product_id ] ) );
		$this->maybe_terminate_process( $product_ids, $type, $data );

		$args = $data;
		/**
		 * The data key is used to store values that should be persisted throughout the dialog sections.
		 * This is used to separate it from values that are only for specific dialog sections.
		 */
		$args[ 'data' ][ 'type' ] = $type;
		$args[ 'data' ][ 'product_ids' ] = $product_ids;
		$args[ 'data' ][ 'products' ] = $this->get_products_data_from_product_ids( $product_ids );
		$create_wishlist_mode = $this->get_create_wishlist_mode( $type );
		$default_wishlist_id = nmgr_get_user_default_wishlist_id( '', $type );

		// If we don't have a wishlist create one
		if ( !$default_wishlist_id ) {
			if ( 'auto' === $create_wishlist_mode ) {
				$default_wishlist_id = $this->create_wishlist( $type );
			} elseif ( 'modal' === $create_wishlist_mode ) {
				$this->show_dialog( $this->get_template( 'profile', $args ) );
			}
		}

		$args[ 'data' ][ 'wishlist_id' ] = $default_wishlist_id;

		// At this point we should have a wishlist to add to, so let's do the adding straightaway
		switch ( $this->get_mode( $type ) ) {
			case 'simple':
				$wishlist = nmgr_get_wishlist( $default_wishlist_id, true );

				/**
				 * The only time we should display a dialog in simple mode is if the
				 * wishlist needs a shipping address
				 */
				if ( $wishlist->needs_shipping_address() ) {
					$this->show_dialog( $this->get_template( 'shipping', $args ) );
				} else {
					$this->add( $wishlist, $args[ 'data' ][ 'products' ], $args );
				}
				break;

			default:
				$this->show_dialog( $this->get_template( 'select-wishlist', $args ) );
				break;
		}
	}

	private function maybe_terminate_process( $product_ids, $type, $data ) {
		$notices = [];
		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product && $product->is_type( 'variable' ) && empty( $data[ 'variation_id' ] ) ) {
				$msg = '<strong>' . $product->get_name() . '</strong> &ndash; ';
				$msg .= sprintf(
					/* translators: %s wishlist type title */
					nmgr()->is_pro ? esc_attr__( 'Please select some product options before adding this product to your %s.', 'nm-gift-registry' ) : esc_attr__( 'Please select some product options before adding this product to your %s.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $type )
				);
				$notices[] = nmgr_get_toast_notice( $msg, 'error' );
			}
		}

		if ( !empty( $notices ) ) {
			wp_send_json( [
				'toast_notice' => $notices,
			] );
		}
	}

	private function get_products_data_from_product_ids( $product_ids ) {
		$products = [];
		foreach ( $product_ids as $pid ) {
			$products[ $pid ] = [ 'quantity' => 1 ];
		}
		return $products;
	}

	private function show_dialog( $content ) {
		$modal = nmgr_get_modal();
		$modal->set_id( 'nmgr-atw-dialog' );
		$modal->set_content( $content );
		$modal->make_large();
		wp_send_json( [ 'show_template' => $modal->get() ] );
	}

	private function show_dialog_section( $section, $args = [] ) {
		wp_send_json( [
			'replace_templates' => [
				'.atw-fieldset' => $this->get_template_part( "$section", $args )
			],
		] );
	}

	private function create_wishlist( $type ) {
		$title = str_replace(
			array( '{wishlist_type_title}', '{site_title}' ),
			array( nmgr_get_type_title( 'c', false, $type ), get_bloginfo( 'name' ) ),
			nmgr_get_type_option( $type, 'default_wishlist_title' )
		);

		$wishlist = nmgr()->wishlist();
		$wishlist->set_title( $title );
		$wishlist->set_status( 'publish' );
		$wishlist->set_user_id( esc_html( nmgr_get_current_user_id() ) );
		$wishlist->set_type( $type );

		if ( 'gift-registry' === $type && is_user_logged_in() ) {
			$user = wp_get_current_user();
			$wishlist->set_email( $user->user_email );
			$wishlist->set_first_name( $user->first_name );
			$wishlist->set_last_name( $user->last_name );
		}

		$wishlist->save();

		$final_title = str_replace( '{wishlist_id}', $wishlist->get_id(), $title );
		$wishlist->set_title( $final_title );

		return $wishlist->save();
	}

	private function get_posted_formdata() {
		$formdata = [];
		parse_str( $_POST[ 'formdata' ], $formdata );

		foreach ( $formdata[ 'data' ] as $key => $val ) {
			$formdata[ 'data' ][ $key ] = is_string( $val ) ? json_decode( $val, true ) : $val;
		}

		return $formdata;
	}

	public function get_section() {
		$formdata = $this->get_posted_formdata();
		$args = [ 'data' => $formdata[ 'data' ] ];
		$this->show_dialog_section( sanitize_text_field( $_POST[ 'dataset' ][ 'section' ] ), $args );
	}

	public function dialog_submitted() {
		$formdata = $this->get_posted_formdata();
		$wishlist_id = ( int ) ($formdata[ 'data' ][ 'wishlist_id' ] ?? 0);
		$submit_section = $formdata[ 'partial_submit' ] ?? null;

		/**
		 * Always validate and save if we have form fields
		 */
		if ( $submit_section ) {
			$form = new \NMGR_Form( $wishlist_id );
			$form->set_data( $formdata )->validate();
			$error_messages = $form->get_fields_error_messages();

			if ( !empty( $error_messages ) ) {
				wp_send_json( [
					'error_data' => $error_messages,
					'toast_notice' => nmgr_get_error_toast_notice(),
				] );
			}

			// Prioritize the saved wishlist id over the selected wishlist id in the modal
			$formdata[ 'data' ][ 'wishlist_id' ] = $wishlist_id = $form->save();
		}

		$wishlist = nmgr_get_wishlist( $wishlist_id, true );

		if ( !$wishlist ) {
			return;
		}

		if ( $wishlist->needs_shipping_address() ) {
			$this->show_dialog_section( 'shipping', $formdata );
		}

		if ( !empty( $formdata[ 'data' ][ 'products' ] ) ) {
			$this->add( $wishlist, $formdata[ 'data' ][ 'products' ], $formdata );
		}
	}

	private function add( $wishlist, $products, $formdata = null ) {
		$results = [];

		if ( !empty( $products ) ) {
			foreach ( $products as $pid => $args ) {
				if ( !empty( $args[ 'quantity' ] ) ) {
					try {
						$result = $this->create(
							$wishlist,
							wc_get_product( $pid ),
							$args[ 'quantity' ],
							$args[ 'favourite' ] ?? 0,
							($formdata[ 'data' ][ 'variations' ][ $pid ] ?? [] )
						);
						if ( !empty( $result ) ) {
							$results = $result + $results;
						}
					} catch ( \Exception $exc ) {
						wc_add_notice( $exc->getMessage(), 'error' );
					}
				}
			}
		}

		if ( $results ) {
			$this->added_to_wishlist_notice( $wishlist, $results );
		}

		wp_send_json( [
			'toast_notice' => nmgr_get_wc_toast_notices(),
			'close_dialog' => true,
			'wishlist_type' => $wishlist->get_type(),
			'added_to_wishlist' => $results ? $results : false,
			'btn_id' => $formdata[ 'data' ][ 'btn_id' ],
		] );
	}

	public function get_add_to_new_wishlist_text( $type ) {
		$text = nmgr_get_type_option( $type, 'add_to_new_wishlist_button_text' );
		$text2 = $text ? $text : nmgr_get_type_option( $type, 'add_to_wishlist_button_text' );
		return str_replace(
			'{wishlist_type_title}',
			nmgr_get_type_title( '', false, $type ),
			$text2
		);
	}

	private function get_wishlists( $type ) {
		$wishlists = nmgr_get_user_wishlists( '', $type );

		foreach ( $wishlists as $key => $wish ) {
			if ( method_exists( $wish, 'is_archived' ) && $wish->is_archived() ) {
				unset( $wishlists[ $key ] );
			}
		}

		return $wishlists;
	}

	private function get_mode( $type ) {
		$umode = 'simple';
		if ( nmgr()->is_pro &&
			('gift-registry' === $type || nmgr_get_type_option( $type, 'allow_multiple_wishlists' ) ) ) {
			$umode = 'advanced';
		}
		return apply_filters( 'nmgr_add_to_wishlist_mode', $umode, [ 'type' => $type ] );
	}

	private function get_button_permalink( $type, $product = '' ) {
		$permalink = '';

		// if we don't have a valid user but we are showing the wishlist button, redirect to login page with notice
		if ( !is_nmgr_user( $type ) ) {
			$permalink = wc_get_page_permalink( 'myaccount' );
			$permalink_args = array(
				'nmgr-notice' => 'require-login',
				'nmgr-redirect' => $_SERVER[ 'REQUEST_URI' ],
				'nmgr-type' => $type,
			);
		} else {
			/**
			 * if we have a valid user who has no wishlists to add products to and the admin wants to
			 * redirect him to create a wishlist instead of using the modal, setup the redirect
			 */
			if ( is_nmgr_shop_loop() && $product && $product->is_type( array( 'variable' ) ) ) {
				/**
				 * The user is logged in and if he has wishlists to add products to
				 * For variable and grouped products on archive pages redirect to actual product page with notice
				 */
				$permalink = $product->get_permalink();
				$permalink_args = array(
					'nmgr-notice' => 'select-product',
					'nmgr-pt' => $product->get_type(),
					'nmgr-type' => $type,
				);
			}
		}

		if ( $permalink && !empty( $permalink_args ) ) {
			$permalink = add_query_arg( $permalink_args, $permalink );
		}

		return $permalink;
	}

	private function get_button_text( $type ) {
		return str_replace(
			'{wishlist_type_title}',
			nmgr_get_type_title( '', false, $type ),
			nmgr_get_type_option( $type, 'add_to_wishlist_button_text' )
		);
	}

	private function get_button_type( $type ) {
		return nmgr_get_type_option( $type, 'add_to_wishlist_button_type', 'button' );
	}

	private function get_button_attributes( $type, $product = '' ) {
		$permalink = $this->get_button_permalink( $type, $product );
		$product_in_wishlist = $product ? $this->user_has_product( $product, $type ) : false;

		$att = [
			'href' => $permalink ? $permalink : '#',
			'class' => array_filter( [
				$this->get_button_type( $type ),
				is_product() ? 'alt' : '',
				'nmgr-atw-btn',
				!$permalink ? 'nmgr-atw-add' : '',
				!$product_in_wishlist ? 'not-in-wishlist' : '',
				$product && $product->is_type( 'variable' ) && is_product() ? 'disabled' : '',
			] ),
			'data-product_id' => $product ? ( int ) $product->get_id() : '',
			'data-type' => $type,
			'data-post_action' => 'atw_button_clicked',
			'data-extra_data' => '',
		];

		$thumbnail_positions = array( 'thumbnail_top_left', 'thumbnail_top_right', 'thumbnail_bottom_left', 'thumbnail_bottom_right' );
		$archive_position = nmgr_get_type_option( $type, 'add_to_wishlist_button_position_archive' );
		$single_position = nmgr_get_type_option( $type, 'add_to_wishlist_button_position_single' );
		$button_location = is_product() ? $single_position : (is_nmgr_shop_loop() ? $archive_position : null);

		if ( in_array( $button_location, $thumbnail_positions ) ) {
			$att[ 'class' ][] = 'on-thumbnail';
			$parts = explode( '_', $button_location );
			$att[ 'class' ][] = "nmgr-{$parts[ 1 ]}";
			$att[ 'class' ][] = "nmgr-{$parts[ 2 ]}";
		}

		return $att;
	}

	public function get_button( $type, $product_id = '' ) {
		if ( !is_nmgr_enabled( $type ) ) {
			return;
		}

		$product = is_object( $product_id ) ? $product_id : ($product_id ? wc_get_product( $product_id ) : '');

		if ( !apply_filters( 'nmgr_show_add_to_wishlist_button', true, $product, $type ) ) {
			return;
		}

		$attr = nmgr_utils_format_attributes( $this->get_button_attributes( $type, $product ) );

		$btn = "<a $attr>" . $this->get_button_content( $type, $product ) . '</a>';
		return $btn;
	}

	private function get_button_content( $wishlist_type, $product ) {
		$content = '';
		$piwt_attr = $product ? $this->get_product_in_wishlist_title_attribute( $product, $wishlist_type ) : '';

		switch ( $this->get_button_type( $wishlist_type ) ) {
			case 'icon-heart':
				$in_wishlist = array(
					'icon' => 'heart',
					'size' => 2,
					'title' => $piwt_attr,
					'class' => 'nmgr-animate in-wishlist-icon',
					'fill' => 'currentColor',
				);

				$not_in_wishlist = array(
					'icon' => 'heart-empty',
					'size' => 2,
					'class' => 'not-in-wishlist-icon',
					'title' => $this->get_button_text( $wishlist_type ),
					'fill' => 'currentColor',
				);
				$content = nmgr_get_svg( $in_wishlist ) . nmgr_get_svg( $not_in_wishlist );
				break;

			case 'button':
				$content = $this->get_button_text( $wishlist_type );
				if ( $product ) {
					$content .= is_nmgr_shop_loop() && $product->is_type( 'variable' ) ? ' *' : '';
					$content .= nmgr_get_svg( array(
						'icon' => 'heart',
						'size' => .75,
						'fill' => 'currentColor',
						'class' => 'nmgr-animate in-wishlist-icon',
						'style' => 'margin-left:0.1875em;',
						'title' => $piwt_attr,
						) );
				}
				break;

			case 'custom':
				if ( nmgr()->is_pro && function_exists( 'nmgr_get_custom_add_to_wishlist_button' ) ) {
					$content = nmgr_get_custom_add_to_wishlist_button( $wishlist_type );
				}
				break;
		}

		return $content;
	}

	public function get_button_with_wrapper( $type, $product_id = '' ) {
		return '<div class="nmgr-atw-wrapper">' . $this->get_button( $type, $product_id ) . '</div>';
	}

	public static function get_button_template( $atts ) {
		global $product;

		if ( !is_array( $atts ) && !empty( $atts ) ) {
			$product = wc_get_product( $atts );
		} elseif ( is_array( $atts ) && isset( $atts[ 'id' ] ) ) {
			$product = wc_get_product( $atts[ 'id' ] );
		} else {
			$product = wc_get_product( $product );
		}

		$type = !empty( $atts[ 'type' ] ) ? $atts[ 'type' ] : 'gift-registry';

		return nmgr()->add_to_wishlist()->get_button_with_wrapper( $type, $product );
	}

	private function get_create_wishlist_mode( $type ) {
		return !nmgr_get_type_option( $type, 'default_wishlist_title' ) ? 'modal' : 'auto';
	}

	public function get_require_shipping_address_notice() {
		$temp = new Single();
		if ( method_exists( $temp, 'set_type' ) && method_exists( $temp, 'get_registered_notice' ) ) {
			$temp->set_type( 'gift-registry' );
			return $temp->get_registered_notice( 'require_shipping_address' );
		}
	}

	private function get_template( $name, $args = [] ) {
		$wrap = $this->get_styles();
		$wrap .= '<form id="nmgr-atw-content" data-post_action="atw_submit_dialog">';
		$wrap .= $this->get_template_part( $name, $args ) . '</form>';

		return $wrap;
	}

	private function get_template_part( $name, $args = [] ) {
		$dialog_args = $this->get_dialog_args( $name, $args );

		$template = '<div class="atw-fieldset">';
		if ( !empty( $dialog_args[ 'data' ] ) ) {
			foreach ( $dialog_args[ 'data' ] as $key => $value ) {
				$val = json_encode( $value );
				$template .= "<input type='hidden' name='data[{$key}]' value='$val'>";
			}
		}

		$fnc = str_replace( '-', '_', "template_$name" );
		$template .= $this->$fnc( $dialog_args );
		$template .= '</div>';

		return $template;
	}

	/**
	 * Get the title attribute to show when a wishlist has a product
	 *
	 * This depends on the kind of product - simple, variable, grouped
	 * as well as the number of wishlists the user has
	 */
	private function get_product_in_wishlist_title_attribute( $product, $type = 'gift-registry' ) {
		$count = nmgr_get_user_wishlists_count( '', $type );
		$product_type = $product->get_type();
		$wishlist_type_title = ($count > 1 ? nmgr_get_type_title( '', 1, $type ) : nmgr_get_type_title( '', false, $type ) );

		switch ( $product_type ) {
			case 'variable':
				return sprintf(
					nmgr()->is_pro ?
					/* translators: %s: wishlist type title */
					_n(
						'This product has variations in your %s',
						'This product has variations in one or more of your %s',
						$count,
						'nm-gift-registry'
					) :
					/* translators: %s: wishlist type title */
					_n(
						'This product has variations in your %s',
						'This product has variations in one or more of your %s',
						$count,
						'nm-gift-registry-lite'
					),
					$wishlist_type_title
				);
			case 'grouped':
				return sprintf(
					nmgr()->is_pro ?
					/* translators: %s: wishlist type title */
					_n(
						'This product has child products in your %s',
						'This product has child products in one or more of your %s',
						$count,
						'nm-gift-registry'
					) :
					/* translators: %s: wishlist type title */
					_n(
						'This product has child products in your %s',
						'This product has child products in one or more of your %s',
						$count,
						'nm-gift-registry-lite'
					),
					$wishlist_type_title
				);
			default:
				return sprintf(
					nmgr()->is_pro ?
					/* translators: %s: wishlist type title */
					_n(
						'This product is in your %s',
						'This product is in one or more of your %s',
						$count,
						'nm-gift-registry'
					) :
					/* translators: %s: wishlist type title */
					_n(
						'This product is in your %s',
						'This product is in one or more of your %s',
						$count,
						'nm-gift-registry-lite'
					),
					$wishlist_type_title
				);
		}
	}

	/**
	 * Add to wishlist messages.
	 *
	 * @param NMGR_Wishlist $wishlist Wishlist Object
	 * @param int|array $product_ids_to_qtys Array of product id to quantity wishlist or single product ID.
	 *
	 * @return mixed
	 */
	private function added_to_wishlist_notice( $wishlist, $product_ids_to_qtys ) {
		$titles = array();
		$count = 0;
		$type = $wishlist->get_type();

		foreach ( $product_ids_to_qtys as $product_id => $qty ) {
			$titles[] = apply_filters( 'nmgr_add_to_wishlist_qty_html', ( $qty > 1 ? absint( $qty ) . ' &times; ' : '' ), $product_id ) .
				apply_filters( 'nmgr_add_to_wishlist_item_name_in_quotes',
					sprintf(
						/* translators: %s: item name */
						nmgr()->is_pro ? _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'nm-gift-registry' ) : _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'nm-gift-registry-lite' ),
						strip_tags( get_the_title( $product_id ) )
					),
					$product_id
				);
			$count += $qty;
		}

		$added_text = sprintf(
			/* translators: 1: item names, 2: wishlist type title */
			nmgr()->is_pro ? _n( '%1$s has been added to your %2$s.', '%1$s have been added to your %2$s.', $count, 'nm-gift-registry' ) : _n( '%1$s has been added to your %2$s.', '%1$s have been added to your %2$s.', $count, 'nm-gift-registry-lite' ),
			wc_format_list_of_items( array_filter( $titles ) ),
			esc_html( nmgr_get_type_title( '', false, $type ) )
		);

		// Get success messages.
		$added_text .= sprintf( ' <a style="color:#fff;text-decoration:underline;" href="%s" tabindex="1" class="nmgr-view-btn">%s</a>',
			esc_url( $wishlist->get_permalink() ),
			nmgr()->is_pro ? __( 'View', 'nm-gift-registry' ) : __( 'View', 'nm-gift-registry-lite' )
		);

		$message = apply_filters( 'nmgr_add_to_wishlist_notice', $added_text, $wishlist, $product_ids_to_qtys );
		wc_add_notice( $message );
	}

	/**
	 * Add a product to a wishlist
	 *
	 * @param NMGR_Wishlist $wishlist The wishlist object
	 * @param WC_Product $product The product object
	 * @param int $qty Quantity to be added
	 * @param int $favourite Product favourite in wishlist
	 * @param array $variations Product variations
	 * @param array $item_data Extra data passed to the wishlist item
	 *
	 * @return array|false Array of product id to quantity added to wishlist.
	 * False if product is not added to the wishlist
	 * @throws Exception
	 */
	public function create( $wishlist, $product, $qty = 1, $favourite = null, $variations = array(), $item_data = array() ) {
		$add_to_wishlist_params = array(
			'wishlist' => $wishlist,
			'product' => $product,
			'quantity' => $qty,
			'favourite' => $favourite,
			'variations' => $variations,
			'item_data' => $item_data,
		);

		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$extra_item_data = apply_filters( 'nmgr_add_to_wishlist_item_data', $item_data, $add_to_wishlist_params );
		$add_to_wishlist_params[ 'item_data' ] = $extra_item_data;
		$unique_id = $wishlist->generate_unique_id( $product_id, $variation_id, $variations, $add_to_wishlist_params[ 'item_data' ] );
		$add_to_wishlist_params[ 'unique_id' ] = $unique_id;
		$type = $wishlist->get_type();

		if ( !apply_filters( 'nmgr_maybe_add_to_wishlist', true, $add_to_wishlist_params ) ) {
			return false;
		}

		$item_id = $wishlist->add_item( $product, $qty, $favourite, $variations, $add_to_wishlist_params[ 'item_data' ] );

		if ( $item_id ) {
			$add_to_wishlist_params[ 'item_id' ] = $item_id;
			do_action( 'nmgr_added_to_wishlist', $add_to_wishlist_params );
			return array( $product->get_id() => $qty );
		} else {
			throw new \Exception( sprintf(
						/* translators: 1: product name, 2: wishlist type title */
						nmgr()->is_pro ? __( '%1$s could not be added to your %2$s', 'nm-gift-registry' ) : __( '%1$s could not be added to your %2$s', 'nm-gift-registry-lite' ),
						$product->get_name(),
						nmgr_get_type_title( '', false, $type )
					) );
		}
	}

	public function get_dialog_submit_button( $type, $text = '' ) {
		$txt = !$text ? $this->get_button_text( $type ) : $text;
		return '<input type="submit" name="atw_submit" class="atw_dialog_submit_btn" value="' . $txt . '">';
	}

	public function get_dialog_args( $section, $args = [] ) {
		switch ( $section ) {
			case 'select-wishlist':
				$product_ids = $args[ 'data' ][ 'product_ids' ];
				$default_product_id = null;
				$products = [];
				$wishlists = $this->get_wishlists( $args[ 'data' ][ 'type' ] );

				if ( 1 === count( $product_ids ) ) {
					$default_product = wc_get_product( $product_ids[ 0 ] );
					if ( $default_product ) {
						$product_type = $default_product->get_type();
						switch ( $product_type ) {
							case 'grouped':
								$default_product_id = $default_product->get_children();
								$products = array_filter(
									array_map( 'wc_get_product', $default_product_id ),
									'wc_products_array_filter_visible_grouped' );
								break;

							case 'variation':
								$default_product_id = $default_product->get_parent_id();
								$products = [ $default_product ];
								break;

							default:
								$default_product_id = $default_product->get_id();
								$products = [ $default_product ];
								break;
						}
					}
				} else {
					$products = array_map( 'wc_get_product', $product_ids );
				}

				$args[ 'wishlists' ] = $wishlists;
				$args[ 'products' ] = $products;
				$args[ 'default_product_id' ] = $default_product_id;

				$this->remove_from_data( [ 'wishlist_id', 'products' ], $args );
				break;

			case 'profile':
				$this->remove_from_data( 'wishlist_id', $args );
				break;
		}

		return $args;
	}

	private function remove_from_data( $params, &$args ) {
		foreach ( array_filter( ( array ) $params ) as $un ) {
			if ( isset( $args[ 'data' ][ $un ] ) ) {
				unset( $args[ 'data' ][ $un ] );
			}
		}
	}

	/**
	 * @return Fields
	 */
	private function get_fields( $args ) {
		$data = $this->columns_data( $args );

		$fields = new Fields();
		$fields->set_id( 'add_to_wishlist' );
		$fields->set_args( $args );
		$fields->set_data( $data );
		$fields->filter_showing();
		$fields->filter_by_priority();
		$fields->set_values( [ $this, 'column_value' ] );
		return $fields;
	}

	public function get_table( $args ) {
		if ( !empty( $args[ 'products' ] ) ) {
			$fields = $this->get_fields( $args );

			$table = new Table();
			$table->set_id( $fields->get_id() );
			$table->set_head( false );
			$table->set_args( $args );
			$table->set_data( $fields->get_data() );
			$table->set_rows_object( $args[ 'products' ] );

			return $table->get_table();
		}
	}

	protected function columns_data( $args ) {
		return [
			'quantity' => [
				'priority' => 30,
			],
			'image' => [
				'priority' => 40,
			],
			'title' => [
				'priority' => 50,
			],
			'price' => [
				'priority' => 60,
			],
		];
	}

	/**
	 * @param string $key
	 * @param Table $table
	 * @return string
	 */
	public function column_value( $table ) {
		$key = $table->get_cell_key();
		$product = $table->get_row_object();
		$args = $table->get_args();
		$type = $args[ 'data' ][ 'type' ];

		ob_start();

		switch ( $key ) {
			case 'price':
				?>
				<div class="atw-product-price"><?php echo $product->get_price_html(); ?></div>
				<?php
				break;

			case 'title':
				?>
				<div class="atw-prodict-title"><?php echo esc_html( $product->get_name() ); ?></div>
				<?php
				if ( !empty( $args[ 'data' ][ 'variations' ][ $product->get_id() ] ) ) {
					$var = $args[ 'data' ][ 'variations' ][ $product->get_id() ];
					$variations = nmgr_get_variations_for_display( $var, $product->get_name() );
					if ( !empty( $variations ) ) :
						?>
						<ul class="variations">
							<?php foreach ( $variations as $variation ) :
								?>
								<li class="<?php echo esc_attr( $variation[ 'key' ] ); ?>">
									<strong><?php echo wp_kses_post( $variation[ 'key' ] ); ?>:</strong>
									<?php echo wp_kses_post( force_balance_tags( $variation[ 'value' ] ) ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php
					endif;
				}
				break;

			case 'image':
				$thumbnail = $product->get_image( 'nmgr_thumbnail', array(
					'title' => $product->get_name(),
					'alt' => $product->get_name() ) );
				?>
				<div class="atw-product-image">
					<?php echo wp_kses( $thumbnail, nmgr_allowed_post_tags() ); ?>
				</div>
				<?php
				break;

			case 'quantity':
				$min = 'wishlist' === $type;
				woocommerce_quantity_input(
					array(
						'input_name' => $this->get_input_name_for_product( 'quantity', $product->get_id() ),
						'input_value' => 1,
						'min_value' => $min ? 1 : 0,
						'max_value' => $min ? 1 : $product->get_stock_quantity(),
					),
					$product
				);
				break;
		}

		return ob_get_clean();
	}

	public function get_input_name_for_product( $key, $product_id ) {
		return "data[products][$product_id][$key]";
	}

	public function maybe_show_add_to_existing_wishlist_link( $type ) {
		if ( nmgr_get_user_wishlists_count( '', $type ) && 'advanced' === $this->get_mode( $type ) ) :
			?>
			<div class="atw-row atw-existing-wrapper">
				<a href="#" id="atw-existing"
					 class="atw-get-section" data-section="select-wishlist"
					 data-post_action="atw_get_section">
					&laquo;
					<?php
					echo esc_html( sprintf(
							/* translators: %s: wishlist type title */
							__( 'Add to existing %s', 'nm-gift-registry' ),
							nmgr_get_type_title( '', false, $type )
						) );
					?>
				</a>
			</div>
			<?php
		endif;
	}

	private function get_styles() {
		ob_start();
		?>
		<style>
			.atw-existing-wrapper {
				opacity: .7;
				text-align: center;
			}
			.atw-row {
				margin-bottom: 14px;
			}

			#nmgr-atw-dialog label,
			#nmgr-atw-content {
				margin: 0;
			}

			#nmgr-atw-dialog fieldset {
				border: none !important;
				margin: 0 !important;
				padding: 0 !important;
				background: none !important;
			}

			#nmgr-atw-dialog #select-wishlist-wrapper {
				margin-bottom: 8px;
			}

			#atw-add-to-new-wishlist-wrapper {
				margin-top: 1px;
			}

			#nmgr-atw-dialog .cls {
				text-align: right;
			}

			#atw-selwish {
				padding: 0.3125em;
				text-align: center;
			}

			.atw-dialog-actions {
				text-align: right;
				padding-top: 20px;
			}

			#nmgr-atw-dialog .nmgr-copy-shipping-address-wrapper {
				text-align: center;
			}

			.nmgr-shipping-notice {
				max-width: 500px;
				text-align: center;
				margin: 12px auto 0;
			}

			#nmgr-atw-dialog .nmgr-copy-shipping-address-wrapper {
				margin: 15px 0;
			}

			#nmgr-atw-dialog #seladd {
				text-align: center;
			}

			.atw-product-image {
				width: 53px;
			}

			#nmgr-atw-dialog .favourite .nmgr-icon {
				font-size: 1.875em;
				top: 2px;
				position: relative;
			}

			#nmgr-atw-dialog .favourite input:checked + label.icon .nmgr-icon,
			#nmgr-atw-dialog .favourite input:not(:checked) + label.icon:hover .nmgr-icon {
				color: #aaa;
			}

			#nmgr-atw-dialog .favourite input:not(:checked) + label.icon .nmgr-icon {
				color: #ddd;
			}

			#nmgr-atw-dialog .nmgr-cart-qty-wrapper .nmgr-qty[data-qty="0"] {
				background-color: #ccc;
			}

			.atw-cell.favourite,
			.atw-cell.quantity,
			.atw-cell.quantity_icon,
			.atw-cell.image {
				width: 0%;
			}

			#nmgr-atw-dialog .atw-cell ul {
				margin-left: 17px;
			}

			#nmgr-atw-dialog table {
				border: none;
				margin: 0 auto 13px;
				width: auto;
			}

			#nmgr-atw-dialog table td {
				border: 1px solid rgba(0, 0, 0, 0.1);
				border-width: 1px 0 0 0;
				padding: 10px;
				vertical-align: middle;
			}

			#nmgr-atw-dialog table td:first-child {
				border-left-width: 1px;
			}

			#nmgr-atw-dialog table td:last-child {
				border-right-width: 1px;
			}

			#nmgr-atw-dialog table tr:last-of-type td {
				border-bottom-width: 1px;
			}
		</style>
		<?php
		return ob_get_clean();
	}

	protected function template_profile( $args ) {
		ob_start();
		$type = $args[ 'data' ][ 'type' ];
		$form = new \NMGR_Form();
		$form->set_type( $type );
		?>
		<fieldset id="atw-profile">

			<?php $this->maybe_show_add_to_existing_wishlist_link( $type ); ?>

			<h4 class="nmgr-text-center">
				<?php echo esc_html( $this->get_add_to_new_wishlist_text( $type ) ); ?>
			</h4>

			<?php
			$fields = $form->get_fields( 'profile' );

			if ( isset( $fields[ 'event_date' ] ) ) {
				$fields[ 'event_date' ][ 'type' ] = 'date';
				$fields[ 'event_date' ][ 'custom_attributes' ][ 'pattern' ] = '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])';
				$fields[ 'event_date' ][ 'placeholder' ] = nmgr()->is_pro ?
					__( 'yyyy-mm-dd', 'nm-gift-registry' ) :
					__( 'yyyy-mm-dd', 'nm-gift-registry-lite' );

				if ( isset( $fields[ 'event_date_display' ] ) ) {
					unset( $fields[ 'event_date_display' ] );
				}
			}

			echo $form->get_fields_html( $fields );
			echo $form->get_hidden_fields();
			?>
			<input type="hidden" name="partial_submit" value="save_profile">

			<div class="atw-dialog-actions">
				<?php echo $this->get_dialog_submit_button( $type ); ?>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	protected function template_shipping( $args ) {
		$type = $args[ 'data' ][ 'type' ];
		$form = new \NMGR_Form();
		$form->set_type( $type );
		$wishlist = nmgr_get_wishlist( $args[ 'data' ][ 'wishlist_id' ] );

		ob_start();
		?>
		<fieldset id="atw-shipping">
			<?php $this->maybe_show_add_to_existing_wishlist_link( $type ); ?>

			<h4 class="nmgr-text-center">
				<?php echo esc_html( $wishlist->get_title() ); ?>
			</h4>

			<?php
			$shipping_notice = $this->get_require_shipping_address_notice();
			if ( $shipping_notice ) :
				?>
				<p class="nmgr-shipping-notice">
					<?php echo esc_html( $shipping_notice ); ?>
				</p>
				<?php
			endif;
			?>

			<?php
			if ( is_user_logged_in() ) {
				$customer = new \WC_Customer( get_current_user_id() );
				if ( $customer &&
					((method_exists( $customer, 'has_shipping_address' ) && $customer->has_shipping_address()) ||
					$customer->get_shipping_address())
				) {
					nmgr_show_copy_shipping_address_btn( $customer->get_shipping() );
				}
			}
			?>

			<?php
			echo $form->get_fields_html( 'shipping' );
			echo $form->get_hidden_fields();
			?>

			<input type="hidden" name="partial_submit" value="update_shipping">

			<div class="atw-dialog-actions">
				<?php echo $this->get_dialog_submit_button( $type ); ?>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	protected function template_select_wishlist( $args ) {
		$type = $args[ 'data' ][ 'type' ];
		$wishlists = $args[ 'wishlists' ];
		$default_product_id = $args[ 'default_product_id' ];

		ob_start();
		?>
		<div id="atw-select-wishlist">
			<?php
			do_action( 'nmgr_add_to_wishlist_dialog_content_before', $args );
			do_action( 'nmgr_add_to_wishlist_dialog_content_after_title', $args );

			echo $this->get_table( $args );

			if ( !empty( $wishlists ) ) :
				?>
				<fieldset id="atw-selwish-wrapper" class="atw-row">

					<div id="seladd" class="atw-row">
						<div id="select-wishlist-wrapper">
							<label class="select-wishlist">
								<select id="atw-selwish" name="data[wishlist_id]">
									<?php
									foreach ( $wishlists as $wishlist ) {
										$wishlist_id = absint( $wishlist->get_id() );
										$title = '';

										if ( $default_product_id && $wishlist->has_product( $default_product_id ) ) {
											$title = sprintf(
												/* translators: %s: wishlist type title */
												nmgr()->is_pro ? __( 'This product is in this %s', 'nm-gift-registry' ) : __( 'This product is in this %s', 'nm-gift-registry-lite' ),
												nmgr_get_type_title( '', false, $wishlist->get_type() )
											);
										}
										?>
										<option value="<?php echo $wishlist_id; ?>"
														title="<?php echo esc_attr( $title ); ?>">
															<?php echo esc_html( $wishlist->get_title() ) . ($title ? ' &hearts;' : ''); ?>
										</option>
									<?php } ?>
								</select>
							</label>
						</div>
						<div id="matw-atw-btn">
							<?php echo $this->get_dialog_submit_button( $type ); ?>
						</div>

						<?php
						if ( nmgr_get_option( 'allow_multiple_wishlists' ) ) :
							?>
							<div id="atw-add-to-new-wishlist-wrapper" class="atw-row">
								<a href="#" id="atw-new" class="atw-get-section" data-section="profile"
									 data-post_action="atw_get_section">
										 <?php echo esc_html( $this->get_add_to_new_wishlist_text( $type ) ); ?>
								</a>
							</div>
							<?php
						endif;
						?>
					</div>

					<?php do_action( 'nmgr_add_to_wishlist_dialog_content_after_wishlists', $args ); ?>

				</fieldset>
				<?php
			endif;

			do_action( 'nmgr_add_to_wishlist_dialog_content_after', $args );
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check whether the current user has a product in any of his wishlists
	 *
	 * @param WC_Product $product
	 * @param string $type Wishlist type
	 * @return boolean True or false
	 */
	public function user_has_product( $product, $type = 'gift-registry' ) {
		global $wpdb;

		$wishlist_ids = nmgr_get_user_wishlist_ids( '', $type );

		if ( $wishlist_ids ) {
			if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
				$product_item_ids = $product->get_children();
			} else {
				$product_item_ids = ( array ) $product->get_id();
			}

			$val = $wpdb->get_var( "
			SELECT COUNT(*)
			FROM {$wpdb->prefix}nmgr_wishlist_items AS items
			WHERE wishlist_id IN ('" . implode( "','", array_map( 'intval', $wishlist_ids ) ) . "')
			AND product_id IN ('" . implode( "','", array_map( 'intval', $product_item_ids ) ) . "')
			LIMIT 1
				" );

			return ( bool ) $val;
		}
		return false;
	}

}
