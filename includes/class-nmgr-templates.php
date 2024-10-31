<?php
/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

class NMGR_Templates {

	public static function run() {
		// Setup templates with woocommerce and wordpress
		add_filter( 'is_woocommerce', array( __CLASS__, 'is_woocommerce' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		// Single wishlist page content
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_title' ), 20 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_display_name' ), 30 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_event_date' ), 40 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_description' ), 50 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_notices' ), 60 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_items' ), 70 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_share_links' ), 80 );
		add_action( 'nmgr_wishlist', array( __CLASS__, 'single_show_copy_link' ), 90 );

		// Account page content
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'show_wishlist_dashboard_text' ), 10 );

		add_filter( 'nmgr_delete_item_notice', array( __CLASS__, 'notify_of_item_purchased_status' ), 10, 2 );

		// Archives
		add_action( 'nmgr_archive_header', array( __CLASS__, 'show_archive_header' ), 10, 2 );
		add_action( 'nmgr_after_archive_title', array( __CLASS__, 'show_archive_results_count_and_page_number' ), 10, 2 );
		add_action( 'nmgr_archive_no_results_found', array( __CLASS__, 'no_search_results_notification' ) );
	}

	public static function single_show_copy_link( $wishlist ) {
		if ( 'publish' !== $wishlist->get_status() ) {
			return;
		}
		?>
		<div class="nmgr-copy-wrapper nmgr-tip"
				 title="<?php
				 printf(
					 /* translators: %s: wishlist type title */
					 nmgr()->is_pro ? esc_attr__( 'Copy your %s url', 'nm-gift-registry' ) : esc_attr__( 'Copy your %s url', 'nm-gift-registry-lite' ),
					 esc_html( nmgr_get_type_title( '', 0, $wishlist->get_type() ) )
				 );
				 ?>">
			<svg width="1.2em" height="1.2em" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m6 18h-3c-.48 0-1-.379-1-1v-14c0-.481.38-1 1-1h14c.621 0 1 .522 1 1v3h3c.621 0 1 .522 1 1v14c0 .621-.522 1-1 1h-14c-.48 0-1-.379-1-1zm1.5-10.5v13h13v-13zm9-1.5v-2.5h-13v13h2.5v-9.5c0-.481.38-1 1-1z" fill-rule="nonzero"/></svg>
			<div class="nmgr-link nmgr-copy"><?php echo esc_html( $wishlist->get_permalink() ); ?> </div>
		</div>
		<?php
	}

	/**
	 * Declare certain NM Gift Registry pages as woocommerce pages
	 *
	 * @param bool $boolean
	 * @return boolean
	 */
	public static function is_woocommerce( $boolean ) {
		return is_nmgr_wishlist() || $boolean;
	}

	/**
	 * Add body classes to identify all nmgr pages
	 *
	 * Classes added here follow this format {page-type}nm_gift_registry
	 *
	 * @param type $classes
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( is_nmgr_search() ) {
			$classes[] = 'search-nm_gift_registry';
		}

		if ( is_nmgr_wishlist() ) {
			$classes[] = 'nm_gift_registry-wishlist';
		}

		if ( is_nmgr_wishlist_page( 'archive' ) ) {
			$classes[] = 'nm_gift_registry-archive';
		}

		if ( is_nmgr_account_section() ) {
			$classes[] = 'nm_gift_registry';
			$classes[] = 'account-nm_gift_registry';
		}

		return $classes;
	}

	/**
	 * Show the wishlist title on the single wishlist page
	 *
	 * @param NMGR_Wishlist $wishlist
	 */
	public static function single_show_title( $wishlist ) {
		printf( '<h2 class="nmgr-title nmgr-text-center entry-title">%s</h2>', esc_html( $wishlist->get_title() ) );
	}

	/**
	 * Show the wishlist display name on the single wishlist page
	 *
	 * @param NMGR_Wishlist $wishlist
	 */
	public static function single_show_display_name( $wishlist ) {
		if ( $wishlist->get_display_name() ) {
			printf( '<h3 class="nmgr-display-name nmgr-text-center">%s</h3>', esc_html( $wishlist->get_display_name() ) );
		}
	}

	/**
	 * Show the wishlist event date on the single wishlist page
	 *
	 * @param NMGR_Wishlist $wishlist
	 */
	public static function single_show_event_date( $wishlist ) {
		if ( $wishlist->is_type( 'wishlist' ) ) {
			return;
		}

		$date = nmgr_format_date( $wishlist->get_event_date() );
		if ( $date ) :

			if ( nmgr_user_has_wishlist( $wishlist ) ) {
				$expiry_days = $wishlist->get_expiry_days();
				$abs_days = absint( $expiry_days );
				$days_notice = '';

				if ( $expiry_days > 0 ) {
					$days_notice = sprintf(
						/* translators: %d: wishlist event days */
						nmgr()->is_pro ? _n( '%d day to your event', '%d days to your event', $abs_days, 'nm-gift-registry' ) : _n( '%d day to your event', '%d days to your event', $abs_days, 'nm-gift-registry-lite' ),
						$abs_days
					);
					$expiry_days = "+$expiry_days";
				} elseif ( $expiry_days < 0 ) {
					$days_notice = sprintf(
						/* translators: %d: wishlist event days */
						nmgr()->is_pro ? _n( '%d day after your event', '%d days after your event', $abs_days, 'nm-gift-registry' ) : _n( '%d day after your event', '%d days after your event', $abs_days, 'nm-gift-registry-lite' ),
						$abs_days
					);
				} else {
					$days_notice = nmgr()->is_pro ?
						__( 'Your event is today', 'nm-gift-registry' ) :
						__( 'Your event is today', 'nm-gift-registry-lite' );
					$expiry_days = $days_notice;
				}
			}
			?>

			<p class="nmgr-event-date nmgr-text-center">
				<span class="nmgr-date-text">
					<?php
					echo esc_html( nmgr()->is_pro ?
							__( 'Event date', 'nm-gift-registry' ) :
							__( 'Event date', 'nm-gift-registry-lite' )
					);

					echo ': ' . esc_html( $date );
					?>
				</span>
				<?php if ( nmgr_user_has_wishlist( $wishlist ) ) : ?>
					<span class="nmgr-badge nmgr-tip"
								style="vertical-align: text-top; margin-left: 5px;"
								title="<?php esc_attr_e( $days_notice ); ?>"><?php echo esc_html( $expiry_days ); ?></span>
							<?php endif; ?>
			</p>

			<?php
		endif;
	}

	/**
	 * Show the wishlist description on the single wishlist page
	 *
	 * @param NMGR_Wishlist $wishlist
	 */
	public static function single_show_description( $wishlist ) {
		if ( $wishlist->get_description() ) {
			printf( '<div class="nmgr-description nmgr-text-center">%s</div>', wp_kses_post( wpautop( $wishlist->get_description() ) ) );
		}
	}

	/**
	 * Show woocommerce notices if available
	 */
	public static function single_show_notices() {
		/**
		 * We first check if the functions exists to prevent fatal error for
		 * 'wc_print_notices()' not found when saving a page with the shortcode
		 * in the admin area
		 */
		if ( function_exists( 'woocommerce_output_all_notices' ) && function_exists( 'wc_print_notices' ) ) {
			woocommerce_output_all_notices();
		}
	}

	/**
	 * Show the wishlist items on the single wishlist page
	 *
	 * @param NMGR_Wishlist $wishlist
	 */
	public static function single_show_items( $wishlist ) {
		if ( $wishlist->is_type( 'gift-registry' ) && $wishlist->is_fulfilled() &&
			nmgr_get_type_option( $wishlist->get_type(), 'hide_fulfilled_items' ) ) {
			return;
		}

		echo nmgr_get_account_section( 'items', $wishlist );
	}

	/**
	 * Show share links on the single wishlist page
	 */
	public static function single_show_share_links( $wishlist ) {
		echo \NMGR\Lib\Single::get_share_template( $wishlist );
	}

	/**
	 * Show link to wishlist endpoint url on woocommerce account dashboard
	 */
	public static function show_wishlist_dashboard_text() {
		foreach ( [ 'gift-registry', 'wishlist' ] as $type ) {
			if ( is_nmgr_enabled( $type ) ) {
				printf(
					/* translators: 1: wishlist module account url, 2: wishlist type title */
					nmgr()->is_pro ? wp_kses_post( __( '<p>You can also manage your <a href="%1$s">%2$s</a>.</p>', 'nm-gift-registry' ) ) : wp_kses_post( __( '<p>You can also manage your <a href="%1$s">%2$s</a>.</p>', 'nm-gift-registry-lite' ) ),
					esc_url( nmgr_get_url( $type, 'home' ) ),
					esc_html( nmgr_get_type_title( '', '', $type ) )
				);
			}
		}
	}

	/**
	 * Show notice when there are no wishlist search results
	 * @since 2.2.0
	 */
	public static function no_search_results_notification() {
		if ( !is_nmgr_search() ) {
			return;
		}

		echo '<p class="woocommerce-info">' .
		sprintf(
			/* translators: %s: wishlist type title */
			esc_html( nmgr()->is_pro ? __( 'No %s were found matching your selection.', 'nm-gift-registry' ) : __( 'No %s were found matching your selection.', 'nm-gift-registry-lite' ) ),
			esc_html( nmgr_get_type_title( '', true ) )
		) .
		'</p>';
	}

	public static function notify_of_item_purchased_status( $notice, $item ) {
		if ( $item->get_purchased_quantity() ) {
			$notice .= ' ' . (nmgr()->is_pro ?
				__( 'This item has purchases that may be lost if deleted.', 'nm-gift-registry' ) :
				__( 'This item has purchases that may be lost if deleted.', 'nm-gift-registry-lite' ));
		}
		return $notice;
	}

	public static function show_archive_header( $query, $args ) {
		if ( $args[ 'show_header' ] ?? true ) :
			?>
			<header class="nmgr-archive-header nmgr-text-center">
			<?php
			if ( $args[ 'show_title' ] ?? true ) :
				if ( !empty( $args[ 'title' ] ) ) {
					$title = $args[ 'title' ];
				} elseif ( is_nmgr_search( $query ) ) {
					$title = sprintf(
						/* translators: %s: search query */
						nmgr()->is_pro ? __( 'Search results for: &ldquo;%s&rdquo;', 'nm-gift-registry' ) : __( 'Search results for: &ldquo;%s&rdquo;', 'nm-gift-registry-lite' ),
						\NMGR\Lib\Archive::get_search_query()
					);
				} else {
					$title = get_the_archive_title();
				}

				$title = apply_filters( 'nmgr_archive_title', $title, $query, $args );

				if ( $title ) :
					?>
						<h2 class="nmgr-archive-title">
						<?php echo wp_kses_post( $title ); ?>
						</h2>
							<?php
						endif;
					endif;

					do_action( 'nmgr_after_archive_title', $query, $args );
					?>
			</header>
				<?php
			endif;
		}

		public static function show_archive_results_count_and_page_number( $query, $args ) {
			$show_results_count = $args[ 'show_results_count' ] ?? is_nmgr_archive();
			$show_on_page = apply_filters( 'nmgr_show_archive_results_count', $show_results_count );

			if ( $show_on_page ) :
				?>
			<p class="nmgr-archive-results-metadata">
			<?php
			$results_found = '<span class="results-found">' . sprintf(
					/* translators: %d: total results */
					nmgr()->is_pro ? _n( '%d result found', '%d results found', $query->found_posts, 'nm-gift-registry' ) : _n( '%d result found', '%d results found', $query->found_posts, 'nm-gift-registry-lite' ),
					( int ) $query->found_posts
				) . '</span>';

			if ( get_query_var( 'paged' ) ) {
				$results_found .= '<span class="page-number">' . sprintf(
						/* translators: %s: page number */
						nmgr()->is_pro ? __( '&nbsp;&ndash; Page %s', 'nm-gift-registry' ) : __( '&nbsp;&ndash; Page %s', 'nm-gift-registry-lite' ),
						get_query_var( 'paged' )
					) . '</span>';
			}
			echo $results_found;
			?>
			</p>
				<?php
			endif;
		}

		public static function get_purchase_refund_template( $item_id ) {
			ob_start();
			$item = nmgr_get_wishlist_item( $item_id );
			$purchased_quantity = $item->get_purchased_quantity();
			?>

		<style>
			#nmgr-purchase-refund-form {
				display: flex;
				flex-flow: column;
				align-items: center;
			}

			#nmgr-purchase-refund-form .nmgr-title {
				font-size: 1.675em;
			}

			#nmgr-purchase-refund-form input.nmgr-quantity {
				width: 5em;
			}

			#nmgr-purchase-refund-form .nmgr-options {
				border: 1px grey dashed;
				padding: .7em;
				margin-top: 1.5em;
			}
		</style>

		<form id="nmgr-purchase-refund-form">
			<div class="nmgr-title-wrap">
				<span class="nmgr-title"><?php echo esc_html( $item->get_product()->get_name() ); ?></span>
		<?php
		$desired_qty_title = nmgr()->is_pro ?
			__( 'Desired quantity', 'nm-gift-registry' ) :
			__( 'Desired quantity', 'nm-gift-registry-lite' );
		?>
				<span class="nmgr-tip nmgr-badge" style="vertical-align:text-bottom;"
							title="<?php esc_attr_e( $desired_qty_title ); ?>">
		<?php echo ( int ) $item->get_quantity(); ?>
				</span>
			</div>
			<br>
				<label class="nmgr-new-qty">
					<span>
		<?php
		echo esc_html( nmgr()->is_pro ?
				__( 'Purchased quantity', 'nm-gift-registry' ) :
				__( 'Purchased quantity', 'nm-gift-registry-lite' )
		);
		?>
					</span>
					<input type="number"
								 step="1"
								 placeholder="0"
								 autocomplete="off"
								 size="4"
								 class="nmgr-quantity"
								 value="<?php echo esc_attr( $purchased_quantity ); ?>"
								 data-qty="<?php echo esc_attr( $purchased_quantity ); ?>"
								 name="quantity"
								 min="0"
								 max="<?php echo esc_attr( $item->get_quantity() ); ?>"
								 />
				</label>

				<div class="nmgr-options">
					<div>
		<?php
		$args = [
			'input_id' => 'nmgr_create_order_switch',
			'input_name' => 'create_order',
			'checked' => true,
			'label_text' => (nmgr()->is_pro ?
			__( 'Create order', 'nm-gift-registry' ) :
			__( 'Create order', 'nm-gift-registry-lite' )) .
			nmgr_get_help_tip( nmgr()->is_pro ?
				__( 'Create an order to reflect the purchase of the item.', 'nm-gift-registry' ) :
				__( 'Create an order to reflect the purchase of the item.', 'nm-gift-registry-lite' ) ),
		];
		echo nmgr_get_checkbox_switch( $args );
		?>
					</div>
					<div>
		<?php
		$args2 = [
			'input_id' => 'nmgr_apply_price_switch',
			'input_name' => 'apply_price',
			'checked' => true,
			'label_text' => (nmgr()->is_pro ?
			__( 'Apply price', 'nm-gift-registry' ) :
			__( 'Apply price', 'nm-gift-registry-lite' )) .
			nmgr_get_help_tip( nmgr()->is_pro ?
				__( 'Include the price of the item in the order total.', 'nm-gift-registry' ) :
				__( 'Include the price of the item in the order total.', 'nm-gift-registry-lite' ) ),
		];
		echo nmgr_get_checkbox_switch( $args2 );
		?>
					</div>
				</div>

				<input type="hidden" name="wishlist_item_id" value="<?php echo esc_attr( $item->get_id() ); ?>">
					</form>
		<?php
		return ob_get_clean();
	}

}
