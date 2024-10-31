<?php
/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Ajax {

	public function run() {
		$ajax_events = array(
			'json_search_products',
			'json_search_users',
			'add_to_cart',
			'post_action',
		);

		foreach ( $ajax_events as $event ) {
			if ( method_exists( $this, $event ) ) {
				add_action( 'wp_ajax_nmgr_' . $event, array( $this, $event ) );
				add_action( 'wp_ajax_nopriv_nmgr_' . $event, array( $this, $event ) );
			}
		}

		/**
		 * Remove woocommerce's add to cart action when running our own so that the add to cart action
		 * runs only once for us
		 */
		if ( wp_doing_ajax() && isset( $_REQUEST[ 'action' ] ) && ('nmgr_add_to_cart' === $_REQUEST[ 'action' ]) ) { // phpcs:ignore WordPress.Security.NonceVerification
			remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );
		}
	}

	public function add_to_cart() {
		if ( !isset( $_POST[ 'nmgr_add_to_cart_item_data' ] ) || empty( $_POST[ 'nmgr_add_to_cart_item_data' ] ) ) {
			wp_die( -1 );
		}

		$data = $_POST[ 'nmgr_add_to_cart_item_data' ];

		$items_data = nmgr()->order()->add_to_cart( $data );

		$success = wc_notice_count( 'success' ) ? true : false;
		$redirect_url = $success ? apply_filters( 'woocommerce_add_to_cart_redirect', false, false ) : false;
		if ( $success && !$redirect_url && 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			$redirect_url = wc_get_cart_url();
		}

		// We are expecting notices from the add to cart action, so get them
		$custom_data = array(
			'success' => $success,
			'notices' => $success && $redirect_url ? '' : nmgr_get_wc_toast_notices(),
			'items_data' => $items_data,
			'redirect_url' => esc_url( apply_filters( 'nmgr_ajax_add_to_cart_redirect_url', $redirect_url, $success, $items_data ) ),
		);

		/**
		 * Due to the way woocommerce adds products to cart via ajax, the refreshed cart fragments must
		 * be returned to the script directly as a result of the add to cart action in order to properly
		 * refresh the cart fragments.
		 * This poses a problem if we want to return additional results from the add-to-cart action as
		 * it is in this case.
		 * The solution is to add the additional results to the cart fragments array using the filter
		 * 'woocommerce_add_to_cart_fragments' so that our results can be returned to the script with
		 * the cart fragments array.
		 *
		 * This hack is necessary because Calling 'WC_AJAX::get_refreshed_fragments()' at the end of the
		 * add to cart action kills the script (using wp_die()) and so we have no other way of returning
		 * our custom results to the script except by hooking into the fragments array before the script is
		 * killed.
		 */
		if ( class_exists( 'wc_ajax' ) && method_exists( 'wc_ajax', 'get_refreshed_fragments' ) ) {
			add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) use ( $custom_data ) {
				return array_merge( $fragments, $custom_data );
			} );

			\WC_AJAX::get_refreshed_fragments();
		}
	}

	/**
	 * Save a wishlist profile
	 *
	 * This function is used for both admin and frontend (ajax requests)
	 */
	protected function save_form() {
		$form_data = [];
		parse_str( $_POST[ 'data' ] ?? '', $form_data );
		$wishlist_id = ( int ) ($form_data[ 'nmgr_wishlist_id' ] ?? 0);

		if ( $wishlist_id ) {
			$this->check_wishlist_permission( $wishlist_id );
		}

		$form = new \NMGR_Form( $wishlist_id );
		$form->set_data( $form_data )->validate();

		if ( $form->has_errors() ) {
			wp_send_json( [
				'error_data' => $form->get_fields_error_messages(),
				'toast_notice' => nmgr_get_error_toast_notice(),
			] );
		}

		$id = $form->save();
		$wishlist = nmgr_get_wishlist( $id );

		$response_data = array(
			'action' => $form_data[ 'nmgr_save' ] ?? null,
			'success' => true,
			'wishlist_type' => $wishlist->get_type(),
			'wishlist' => $wishlist->get_data(),
			'created' => ( bool ) !$wishlist_id
		);

		if ( $response_data[ 'created' ] ) {
			if ( $wishlist->needs_shipping_address() ) {
				$response_data[ 'redirect' ] = add_query_arg(
					'nmgr_redirect',
					1,
					trailingslashit( $wishlist->get_permalink() ) . 'shipping'
				);
			} else {
				$response_data[ 'redirect' ] = $wishlist->get_permalink();
			}
		} elseif ( !empty( $form_data[ '_wp_http_referer' ] ) &&
			false !== strpos( $form_data[ '_wp_http_referer' ], 'nmgr_redirect' ) ) {
			$response_data[ 'redirect' ] = $wishlist->get_permalink();
		}

		$redirect = $response_data[ 'redirect' ] ?? null;
		$response_data[ 'redirect' ] = apply_filters( 'nmgr_redirect_after_save', $redirect, $response_data );

		if ( $response_data[ 'success' ] && empty( $response_data[ 'redirect' ] ) ) {
			$response_data[ 'toast_notice' ] = nmgr_get_success_toast_notice();
		}

		wp_send_json( $response_data );
	}

	protected function add_item() {
		$wishlist_id = absint( wp_unslash( $_POST[ 'wishlist_id' ] ) );
		$this->check_wishlist_permission( $wishlist_id );

		$wishlist = nmgr_get_wishlist( $wishlist_id, true );
		$items_to_add = isset( $_POST[ 'nmgr_add_items_data' ] ) ?
			array_filter( wc_clean( wp_unslash( ( array ) $_POST[ 'nmgr_add_items_data' ] ) ) ) :
			array();

		// Add items to wishlist.
		foreach ( $items_to_add as $item ) {
			if ( !isset( $item[ 'product_id' ] ) || empty( $item[ 'product_id' ] ) ) {
				continue;
			}
			$product_id = absint( $item[ 'product_id' ] );
			$qty = wc_stock_amount( isset( $item[ 'product_qty' ] ) && $item[ 'product_qty' ] ? $item[ 'product_qty' ] : 1 );
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$favourite = isset( $item[ 'product_fav' ] ) ? absint( $item[ 'product_fav' ] ) : 0;
				$wishlist->add_item( $product, $qty, $favourite );
			}
		}

		$acc = nmgr()->account( $wishlist );
		wp_send_json( array(
			'success' => true,
			'toast_notice' => nmgr_get_success_toast_notice(),
			'wishlist' => $wishlist->get_data(),
			'replace_templates' => $acc->get_sections_by_ids( 'items' ),
			'close_dialog' => true,
		) );
	}

	protected function save_item_quantity() {
		$posted_ids = $this->get_posted_wishlist_and_item_ids();
		$this->check_wishlist_permission( $posted_ids[ 'wishlist_id' ] ?? false );

		$item = nmgr_get_wishlist_item( $posted_ids[ 'wishlist_item_id' ] ?? 0 );

		if ( $item && !empty( $_POST[ 'quantity' ] ) ) {
			$item->set_quantity( ( int ) $_POST[ 'quantity' ] );
			$item->save();

			$table = nmgr()->items_table( $item->get_wishlist() );

			wp_send_json( array(
				'replace_templates' => array_merge(
					$table->get_item_template_data( $item ),
					$table->get_totals_template_data()
				),
			) );
		}
	}

	/**
	 * Search for products and echo json.
	 */
	public function json_search_products() {
		check_ajax_referer( 'nmgr-search-products', 'security' );

		$term = sanitize_text_field( wp_unslash( $_GET[ 'term' ] ?? '' ) );

		if ( empty( $term ) ) {
			wp_die();
		}

		$data_store = \WC_Data_Store::load( 'product' );
		$ids = $data_store->search_products( $term, '', true, false, 30 );

		$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_readable' );
		$products = array();

		foreach ( $product_objects as $product_object ) {
			$formatted_name = $product_object->get_formatted_name();

			if ( !empty( $_GET[ 'display_stock' ] ) && $product_object->managing_stock() ) {
				$stock_amount = $product_object->get_stock_quantity();
				$formatted_name .= ' &ndash; ' . sprintf(
						/* translators: %d: stock quantity */
						nmgr()->is_pro ? __( 'Stock: %d', 'nm-gift-registry' ) : __( 'Stock: %d', 'nm-gift-registry-lite' ),
						wc_format_stock_quantity_for_display( $stock_amount, $product_object )
				);
			}

			$products[ $product_object->get_id() ] = rawurldecode( $formatted_name );
		}

		wp_send_json( $products );
	}

	/**
	 * Search for users
	 */
	public function json_search_users() {
		ob_start();

		check_ajax_referer( 'nmgr-search-users', 'security' );

		if ( !current_user_can( 'edit_nm_gift_registries' ) ) {
			wp_die( -1 );
		}

		$term = isset( $_GET[ 'term' ] ) ? ( string ) wc_clean( wp_unslash( $_GET[ 'term' ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$limit = 0;

		if ( empty( $term ) ) {
			wp_die();
		}

		$ids = array();
		// Search by ID.
		if ( is_numeric( $term ) ) {
			$customer = new \WC_Customer( ( int ) $term );

			// Customer exists.
			if ( 0 !== $customer->get_id() ) {
				$ids = array( $customer->get_id() );
			}
		}

		/**
		 * If the customer doesn't exist by searching by id, search for numeric username,
		 * this prevents performance issues with ID lookups.
		 */
		if ( empty( $ids ) ) {
			$data_store = \WC_Data_Store::load( 'customer' );

			/**
			 * If search is smaller than 3 characters, limit result set to avoid
			 * too many rows being returned.
			 */
			if ( 3 > strlen( $term ) ) {
				$limit = 20;
			}
			$ids = $data_store->search_customers( $term, $limit );
		}

		$found_customers = array();

		if ( !empty( $_GET[ 'exclude' ] ) ) {
			$ids = array_diff( $ids, array_map( 'absint', ( array ) wp_unslash( $_GET[ 'exclude' ] ) ) );
		}

		foreach ( $ids as $id ) {
			$customer = new \WC_Customer( $id );
			$name = $customer->get_first_name() . ' ' . $customer->get_last_name();

			$found_customers[ $id ] = [
				'text' => sprintf(
					/* translators: $1: customer name, $2: customer email */
					nmgr()->is_pro ? esc_html__( '%1$s (%2$s)', 'nm-gift-registry' ) : esc_html__( '%1$s (%2$s)', 'nm-gift-registry-lite' ),
					trim( $name ) ? $name : $customer->get_display_name(),
					$customer->get_email()
				),
				'shipping_address' => json_encode( $customer->get_shipping() ),
			];
		}

		wp_send_json( $found_customers );
	}

	protected function delete_item() {
		$wid = $this->get_posted_wishlist_and_item_ids();
		$wishlist_id = $wid[ 'wishlist_id' ];
		$wishlist_item_ids = $wid[ 'wishlist_item_ids' ];

		$this->check_wishlist_permission( $wishlist_id );

		$wishlist = nmgr_get_wishlist( $wishlist_id );
		if ( $wishlist && !empty( $wishlist_item_ids ) ) {
			$table = nmgr()->items_table( $wishlist );
			$sections = [];

			foreach ( $wishlist_item_ids as $item_id ) {
				$item = nmgr_get_wishlist_item( $item_id );

				if ( !$item ||
					($item->get_wishlist_id() !== $wishlist->get_id()) ||
					(!is_nmgr_admin() && $wishlist->is_type( 'gift-registry' ) &&
					((method_exists( $item, 'is_archived' ) && $item->is_archived()) ||
					$item->is_purchased() ||
					$item->is_fulfilled())
					)
				) {
					continue;
				}

				$table->set_row_object( $item );
				$sections[ '#' . $table->get_item_id() ] = '';
				$item->delete();
			}

			wp_send_json( array(
				'replace_templates' => array_merge(
					$sections,
					$table->get_totals_template_data(),
					$table->get_items_count_progressbar_data(),
				),
				'wishlist' => $wishlist->get_data(),
			) );
		}
	}

	protected function show_purchase_refund_item_dialog() {
		$wid = $this->get_posted_wishlist_and_item_ids();
		$wishlist_id = $wid[ 'wishlist_id' ];
		$wishlist_item_id = $wid[ 'wishlist_item_id' ];

		$this->check_wishlist_permission( $wishlist_id );

		$modal = nmgr_get_modal();
		$modal->set_title( nmgr()->is_pro ?
				__( 'Update purchased quantity', 'nm-gift-registry' ) :
				__( 'Update purchased quantity', 'nm-gift-registry-lite' )
		);
		$modal->set_id( 'nmgr-purchase-refund-dialog' );
		$modal->set_content( nmgr()->templates()->get_purchase_refund_template( $wishlist_item_id ) );
		$modal->set_footer(
			$modal->get_save_button( [
				'attributes' => [
					'type' => 'submit',
					'class' => [ 'button-primary', 'nmgr_save_purchase_refund_form' ],
					'form' => 'nmgr-purchase-refund-form'
				]
			] )
		);

		wp_send_json( [ 'show_template' => $modal->get() ] );
	}

	protected function save_purchase_refund_form() {
		$wid = $this->get_posted_wishlist_and_item_ids();
		$wishlist_id = $wid[ 'wishlist_id' ];
		$wishlist_item_id = $wid[ 'wishlist_item_id' ];

		$this->check_wishlist_permission( $wishlist_id );

		$item = nmgr_get_wishlist_item( $wishlist_item_id );

		if ( ( int ) $_POST[ 'quantity' ] !== $item->get_purchased_quantity() ) {
			$notices = [ nmgr_get_success_toast_notice() ];

			$args = [
				'quantity' => ( int ) $_POST[ 'quantity' ],
				'create_order' => !empty( $_POST[ 'create_order' ] ),
				'apply_price' => !empty( $_POST[ 'apply_price' ] ),
			];

			$order = $item->update_purchased_quantity( $args );

			if ( is_a( $order, \WC_Order::class ) ) {
				$notices[] = $item->get_order_created_toast_notice( $order );
			}

			$table = nmgr()->items_table( $item->get_wishlist() );

			wp_send_json( [
				'toast_notice' => $notices,
				'replace_templates' => array_merge(
					$table->get_item_template_data( $item ),
					$table->get_totals_template_data()
				),
				'close_dialog' => true,
			] );
		}
	}

	protected function load_wishlist_cart() {
		if ( !empty( $_POST[ 'cart_id' ] ) ) {
			$args = [ 'type' => sanitize_text_field( $_POST[ 'type' ] ?? '' ) ];
			wp_send_json( [
				'replace_templates' => [
					'#' . sanitize_text_field( $_POST[ 'cart_id' ] ) => \NMGR_Widget_Cart::template( $args )
				]
			] );
		}
	}

	protected function orderby() {
		$section_dataset = $_POST[ 'section_dataset' ];
		$table_dataset = $_POST[ 'table_dataset' ];
		$class = !empty( $table_dataset[ 'class' ] ) ? wp_unslash( $table_dataset[ 'class' ] ) : '';

		if ( class_exists( $class ) && !empty( $section_dataset[ 'wishlist_id' ] ) ) {
			/**
			 * This is used to get tables that are part of account sections.
			 * We expect them to take a wishlist object as their constructor argument
			 */
			$table = new $class( nmgr_get_wishlist( ( int ) $section_dataset[ 'wishlist_id' ] ) );
			$table->set_order( sanitize_text_field( $_POST[ 'order' ] ) );
			$table->set_orderby( sanitize_text_field( $_POST[ 'orderby' ] ) );
			$table->setup();

			/**
			 * We're replacing the navigation with the table because ordering the table resets the page
			 * to 1 and we want to reflect that in the navigation in case the ordering was done on another
			 * page number.
			 */
			wp_send_json( array(
				'success' => true,
				'replace_templates' => [
					"#{$table->get_html_id()}" => $table->get_table(),
					".nmgr-navs.{$table->get_id()}" => $table->get_nav()
				],
			) );
		}
	}

	protected function show_add_items_dialog() {
		$prod_text = esc_html( nmgr()->is_pro ?
			__( 'Product', 'nm-gift-registry' ) :
			__( 'Product', 'nm-gift-registry-lite' ) );

		$qty_text = esc_html( nmgr()->is_pro ?
			__( 'Quantity', 'nm-gift-registry' ) :
			__( 'Quantity', 'nm-gift-registry-lite' ) );

		$placeholder_text = nmgr()->is_pro ?
			esc_attr__( 'Search for a product&hellip;', 'nm-gift-registry' ) :
			esc_attr__( 'Search for a product&hellip;', 'nm-gift-registry-lite' );

		$language = sprintf(
			/* translators: %d character length */
			nmgr()->is_pro ? __( 'Please enter %d or more characters', 'nm-gift-registry' ) : __( 'Please enter %d or more characters', 'nm-gift-registry-lite' ),
			3
		);

		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( 'nmgr-search-products' );

		$columns = apply_filters( 'nmgr_add_items_table_columns', array(
			$prod_text => '<td data-title="' . $prod_text . '"><select class="nmgr-product-search" name="product_id" data-ajax_url="' . $ajax_url . '" data-nonce="' . $nonce . '" data-display_stock="true" data-text-input-too-short="' . $language . '" data-placeholder="' . $placeholder_text . '"></select></td>',
			$qty_text => '<td data-title="' . $qty_text . '"><input type="number" step="1" min="0" max="9999" autocomplete="off" name="product_qty" placeholder="1" size="4" class="quantity" /></td>',
			) );

		ob_start()
		?>
		<style>
			.nmgr-add-items-dialog table.widefat {
				border: none;
			}

			.nmgr-add-items-dialog .select2-container,
			.nmgr-add-items-dialog select {
				width: 100% !important;
			}

			@media screen and (min-width: 786px) {
				.nmgr-add-items-dialog table thead th:last-child,
				.nmgr-add-items-dialog table tbody td:last-child {
					padding-right: 0;
				}

				.nmgr-add-items-dialog table thead th:first-child,
				.nmgr-add-items-dialog table tbody td:first-child {
					padding-left: 0;
				}
			}

			@media screen and (max-width: 785px) {
				.nmgr-add-items-dialog table {
					display: block;
				}

				.nmgr-add-items-dialog table tbody {
					display: block;
				}

				.nmgr-add-items-dialog table thead {
					display: none;
				}

				.nmgr-add-items-dialog table tr,
				.nmgr-add-items-dialog table td {
					display: block;
				}

				.nmgr-add-items-dialog table tr {
					border: 1px solid #ccc;
					margin-bottom: 1.5em;
				}

				.nmgr-add-items-dialog table td {
					text-align: right !important;
				}

				.nmgr-add-items-dialog table tbody td[data-title]:before,
				.nmgr-add-items-dialog table tbody th[data-title]:before {
					content: attr(data-title) ": ";
					font-weight: 600;
					float: left;
				}

				.nmgr-add-items-dialog table tbody td:after,
				.nmgr-add-items-dialog table tbody th:after {
					content: '';
					clear: both;
					display: block;
				}

				.nmgr-add-items-dialog table .select2-container,
				.nmgr-add-items-dialog table select {
					width: inherit !important;
					max-width: 70% !important;
				}
			}
		</style>
		<form>
			<table class="widefat">
				<thead>
					<tr>
						<?php foreach ( array_keys( $columns ) as $label ) : ?>
							<th><?php echo $label; ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<?php $row = implode( '', $columns ); ?>
				<tbody data-row="<?php echo esc_attr( $row ); ?>">
					<tr>
						<?php echo $row; ?>
					</tr>
				</tbody>
			</table>
		</form>
		<?php
		$content = ob_get_clean();

		$modal_title = nmgr()->is_pro ?
			__( 'Add item(s)', 'nm-gift-registry' ) :
			__( 'Add item(s)', 'nm-gift-registry-lite' );

		$modal = nmgr_get_modal();
		$modal->set_title( $modal_title );
		$modal->set_id( 'nmgr-add-items-dialog' );
		$modal->set_content( $content );
		$modal->make_large();

		$modal->set_footer(
			$modal->get_save_button(
				[
					'text' => nmgr()->is_pro ?
						__( 'Add', 'nm-gift-registry' ) :
						__( 'Add', 'nm-gift-registry-lite' ),
					'attributes' => [
						'class' => [ 'button-primary', 'nmgr-add' ],
					]
				]
			)
		);

		wp_send_json( [ 'show_template' => $modal->get() ] );
	}

	/**
	 * Make sure a user is allowed to perform the action on a wishlist.
	 *
	 * For ajax usage.
	 * This function is similar to check_ajax_referrer in that it kills the script
	 * if the wishlist doesn't exist or if the user performing the action is not the
	 * wishlist owner or an admin user who can manage the wishlist.
	 *
	 * @param int|NMGR_Wishlist $wishlist_id The wishlist id or object
	 */
	public function check_wishlist_permission( $wishlist_id ) {
		if ( !nmgr_get_wishlist( $wishlist_id ) || !nmgr_user_can_manage_wishlist( $wishlist_id ) ) {
			wp_die();
		}
	}

	/**
	 * Get the wishlist id and wishlist item ids in the $_POST array
	 * @return array An array with wishlist_id (int|false) and wishlist_item_ids (array) keys representing
	 * the wishlist_id and wishlist_item_ids in $_POST
	 */
	public function get_posted_wishlist_and_item_ids() {
		$wishlist_id = ( int ) wp_unslash( $_POST[ 'wishlist_id' ] ?? false );
		$wishlist_item_ids = array_map( 'absint', wp_unslash( ( array ) ($_POST[ 'wishlist_item_ids' ] ?? []) ) );
		$item_id = ( int ) wp_unslash( $_POST[ 'wishlist_item_id' ] ?? 0 );

		if ( $item_id ) {
			$wishlist_item_ids = [ $item_id ];
		}

		if ( !$wishlist_id && !empty( $wishlist_item_ids ) ) {
			$first_item = nmgr_get_wishlist_item( $wishlist_item_ids[ 0 ] );
			$wishlist_id = $first_item ? $first_item->get_wishlist_id() : false;
		}

		return [
			'wishlist_id' => $wishlist_id ? ( int ) $wishlist_id : false,
			'wishlist_item_id' => !empty( $wishlist_item_ids ) ? $wishlist_item_ids[ 0 ] : false,
			'wishlist_item_ids' => $wishlist_item_ids,
		];
	}

	public function post_action() {
		check_ajax_referer( 'nmgr' );

		$action = sanitize_text_field( $_POST[ 'nmgr_post_action' ] ?? '' );

		if ( $action ) {
			if ( is_callable( [ $this, $action ] ) ) {
				$this->$action();
			} else {
				$args = array_merge( [ 'post_action' => $action, ], $this->get_posted_wishlist_and_item_ids() );
				do_action( 'nmgr_post_action', $args );
			}
		}

		wp_die();
	}

}
