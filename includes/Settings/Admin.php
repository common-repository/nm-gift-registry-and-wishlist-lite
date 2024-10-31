<?php

namespace NMGR\Settings;

use NMGR\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class Admin extends Settings {

	public $option_name = 'nmgr_settings';
	public $is_woocommerce_screen = true;
	public $license = false;

	public function get_type() {
		return 'gift-registry';
	}

	/**
	 * For backwards compatibility with plugins using this method
	 * e.g. crowdfunding extension.
	 * @since version 3.0.0
	 * Remove in a much later version
	 */
	public static function get_default_values() {
		return function_exists( 'nmgr' ) ? nmgr()->gift_registry_settings()->get_default_field_values() : [];
	}

	public function is_nmerimedia_screen() {
		global $typenow, $pagenow;

		return parent::is_nmerimedia_screen() ||
			(function_exists( 'is_nmgr_admin' ) && is_nmgr_admin()) ||
			('product' === $typenow && 'edit.php' === $pagenow);
	}

	public function run() {
		if ( !is_admin() ) {
			return;
		}

		parent::run();

		add_filter( $this->page_slug() . '_tab_title', [ $this, 'modify_tab_title' ], 10, 2 );
		add_filter( $this->page_slug() . '_validate_input', [ $this, 'extra_validate_fields_to_save' ] );
		add_action( $this->page_slug() . '_tab_full_version', [ $this, 'do_settings_sections_full_version' ] );
		add_action( $this->page_slug() . '_before_save_input', [ $this, 'save_settings_errors' ] );
	}

	public function modify_tab_title( $title, $slug ) {
		if ( 'emails' === $slug && !nmgr()->is_pro ) {
			$title .= ' ' . $this->get_pro_version_text();
		}
		return $title;
	}

	public function get_screen_id() {
		return 'nm_gift_registry_page_' . $this->page_slug();
	}

	public function add_menu_page() {
		$title = $this->get_heading();

		add_submenu_page(
			'edit.php?post_type=nm_gift_registry',
			$title . ' - ' . nmgr()->name,
			$title,
			'manage_nm_gift_registry_settings',
			$this->page_slug(),
			array( $this, 'menu_page_content' )
		);
	}

	public function get_custom_tabs() {
		return [];
	}

	public function get_tabs() {
		$tabs = $this->get_custom_tabs();

		if ( !nmgr()->is_pro ) {
			$tabs[ 'full_version' ] = array(
				'tab_title' => __( 'PRO Version', 'nm-gift-registry-lite' ),
				'sections_title' => '',
				'show_sections' => false,
				'priority' => 90,
				'submit_button' => false
			);
		}

		return apply_filters( 'nmgr_settings_tabs', $tabs, $this->get_type() );
	}

	public function do_settings_sections_full_version() {
		if ( nmgr()->is_pro ) {
			return;
		}

		$features = array(
			array(
				'title' => __( 'Multiple wishlists', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow users to have as many gift registries or wishlists as they want, each with it\'s own custom management page and settings on the frontend.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'database',
					'size' => 6,
					'fill' => '#da5027',
					'sprite' => false
				) ),
			),
			array(
				'title' => __( 'Wishlist lifecycle emails', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow the plugin to send emails to custom recipients and the list owner when the list is created, fulfilled or deleted, when items have been ordered, purchased or refunded from the list, and when messages are sent to the list owner at checkout.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'email',
					'size' => 6,
					'fill' => 'blue'
				) ),
			),
			array(
				'title' => __( 'Featured and background images', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow list owners to upload and display featured and background images on their list pages and on social media to add a personalised touch and improve the impressions made on guests.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'camera',
					'size' => 6,
					'fill' => 'lawngreen'
				) ),
			),
			array(
				'title' => __( 'Checkout messages', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow guests to send messages to the list owner from the checkout page as items are ordered for him. These message appear in the list\'s management page and can be configured to appear in the owner\'s inbox as well as in other emails sent to him.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'bubble',
					'size' => 6,
					'fill' => 'aqua'
				) ),
			),
			array(
				'title' => __( 'Wishlist visibility settings', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow list owners to set the visibility of their lists to private, password protected or public from the list management page on the frontend. These visibilities correspond to WordPress\' visibility settings.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'gear',
					'size' => 6,
					'fill' => 'purple'
				) ),
			),
			array(
				'title' => __( 'Exclude wishlists from search', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow list owners to exclude individual lists from search results regardless of their visibility. This gives them improved control over how their lists appear on the website.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'search',
					'size' => 6,
					'sprite' => false,
					'fill' => 'yellow'
				) ),
			),
			array(
				'title' => __( 'Mark wishlist items as favourite', 'nm-gift-registry-lite' ),
				'desc' => __( 'Allow list owners to mark an item as favourite when adding it to their list. The favourite status of items can be edited in the list management page and items can be sorted in the items table by their favourite status.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'star-full',
					'size' => 6,
					'fill' => 'red'
				) ),
			),
			array(
				'title' => __( 'Extra add to wishlist customizations', 'nm-gift-registry-lite' ),
				'desc' => __( 'Customize the add to wishlist button to your liking. Select new display types, toggle the use of ajax for the action, the animation and display of notifications, the ability to choose the quantity and favourite status when adding a product to the list, whether variable and grouped products can be added to the list. Include or exclude products and product categories from being added to the wishlist, and much more.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'wrench',
					'size' => 6,
					'sprite' => false,
					'fill' => 'orange'
				) ),
			),
			array(
				'title' => __( 'One-click wishlist templates customization', 'nm-gift-registry-lite' ),
				'desc' => __( 'Customize the list templates from the admin area with the click of a button without writing any code. Toggle the visibility of default account tabs, the visibility and required status of the settings fields, the visibility and display type of the featured and background images, the messages module, and much more.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'control-panel',
					'size' => 6,
					'sprite' => false,
					'fill' => '#1d78c3'
				) ),
			),
			array(
				'title' => __( 'Delete wishlist', 'nm-gift-registry-lite' ),
				'desc' => __( 'Give list owners the ability to delete their lists from the frontend without having to contact the admin or leave it dormant to clog up your database. This gives them more control over their lists and leads to a cleaner site for the administrator.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'trash-can',
					'size' => 6,
					'fill' => 'brown'
				) ),
			),
			array(
				'title' => __( 'Customize wishlist shipping', 'nm-gift-registry-lite' ),
				'desc' => __( 'Control the shipping methods available for wishlist items in the cart. Calculate shipping rates for the wishlist items separately from normal items. Ship wishlist items in cart to the wishlist\'s owner\'s address. And much more.', 'nm-gift-registry-lite' ),
				'image' => nmgr_get_svg( array(
					'icon' => 'box-open',
					'size' => 6,
					'fill' => 'orangered'
				) ),
			),
		);
		?>
		<div class="nmgr-full-version">
			<div class="nmgr-text-center">
				<a class="nmgr-buy-btn" href="<?php echo esc_url( nmgr()->product_url ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'Upgrade Now', 'nm-gift-registry-lite' ); ?></a>
			</div>

			<h1 class="nmgr-text-center"><?php esc_html_e( 'NM Gift Registry and Wishlist Features', 'nm-gift-registry-lite' ); ?></h1>
			<p class="nmgr-desc nmgr-text-center"><?php esc_html_e( 'Check out these fantastic extra features that can provide your store with the perfect gift registry and wishlist experience.', 'nm-gift-registry-lite' ); ?></p>
			<div class="nmgr-features">
				<?php foreach ( $features as $feature ) : ?>
					<div class="nmgr-feature">
						<div class="nmgr-image"><?php echo $feature[ 'image' ]; ?></div>
						<div class="nmgr-info">
							<h2><?php echo esc_html( $feature[ 'title' ] ); ?></h2>
							<p><?php echo wp_kses_post( $feature[ 'desc' ] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="nmgr-text-center">
				<a class="nmgr-buy-btn" href="<?php echo esc_url( nmgr()->product_url ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'Upgrade Now', 'nm-gift-registry-lite' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 *  Validate custom fields that have not been captured above in the standard tab section fields
	 * @todo make this process more dynamic in the future
	 */
	public function extra_validate_fields_to_save( $input ) {
		return $input;
	}

	public function get_error_codes_to_messages() {
		$no_page_id = sprintf(
			/* translators: %s: wishlist type title */
			nmgr()->is_pro ? __( 'You need to set a page for viewing %s.', 'nm-gift-registry' ) : __( 'You need to set a page for viewing %s.', 'nm-gift-registry-lite' ),
			nmgr_get_type_title( '', 1, $this->get_type() )
		);

		$msgs = [
			'no-page-id' => $no_page_id,
			'no-wishlist-page-id' => $no_page_id,
		];

		return $msgs;
	}

	public function save_settings_errors( $input ) {
		$options = array_merge( nmgr_get_option(), $input );

		$params = [
			[
				'enable' => 'enable',
				'page_id' => 'page_id',
				'code' => 'no-page-id',
			],
			[
				'enable' => 'wishlist_enable',
				'page_id' => 'wishlist_page_id',
				'code' => 'no-wishlist-page-id',
			],
		];

		foreach ( $params as $param ) {
			if ( array_key_exists( $param[ 'page_id' ], $input ) && empty( $input[ $param[ 'page_id' ] ] ) &&
				isset( $options[ $param[ 'enable' ] ] ) && $options[ $param[ 'enable' ] ] ) {
				$code = $param[ 'code' ];
				$this->save_settings_error( $code, $this->get_error_message_by_code( $code ) );
			}
		}
	}

	public function get_default_field_values() {
		$defaults = parent::get_default_field_values();

		if ( !nmgr()->is_pro ) {
			$pro_defaults = $this->get_default_pro_fields_values();
			$defaults = array_diff_key( $defaults, $pro_defaults );
		}

		return $defaults;
	}

	public function get_default_pro_fields_values() {
		$default_pro_fields = [];

		foreach ( $this->get_fields() as $key => $field ) {
			if ( !empty( $field[ 'pro' ] ) ) {
				$default_pro_fields[ $key ] = $field;
			}
		}

		return $this->get_default_values_for_fields( $default_pro_fields );
	}

	protected function help_tip( $title ) {
		return nmgr_get_help_tip( $title );
	}

	public static function is_settings_screen( $type = '' ) {
		global $pagenow;

		/**
		 * Set options.php as settings screen so that setting screen would be recognized
		 * when saving plugin setting
		 */
		if ( 'options.php' === $pagenow ) {
			return true;
		}

		$is_screen = is_admin() && 'nm_gift_registry' === ($_GET[ 'post_type' ] ?? false);
		$page = $_GET[ 'page' ] ?? false;
		$sections = [
			'wishlist' => 'nmgr-wishlist-settings',
			'gift-registry' => 'nmgr-settings'
		];

		return $type ?
			$is_screen && $page === ($sections[ $type ] ?? null) :
			$is_screen && in_array( $page, $sections );
	}

}
