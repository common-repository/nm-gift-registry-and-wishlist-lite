<?php

/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

use \NMGR\Settings\PluginProps;
use \NMGR\Lib\AddToWishlist;

class NMGR_Props extends PluginProps {

	public $requires_wc = '4.3.0';
	public $prefix = 'nmgrlite';
	public $requires_nmgrcf = '4.12';

	public function __construct( $filepath ) {
		parent::__construct( $filepath );

		$this->is_pro = false === strpos( $this->slug, 'lite' );
		$this->prefix = $this->is_pro ? 'nmgr' : $this->prefix;
		$this->is_licensed = $this->is_pro;
	}

	public function post_thumbnail_size() {
		return apply_filters( 'nmgr_medium_size', 190 );
	}

	public function flush_rewrite_rules() {
		update_option( 'nmgr_flush_rewrite_rules', 1 );
	}

	/**
	 * @return \NMGR_Admin_Post
	 */
	public function admin_post() {
		return new NMGR_Admin_Post();
	}

	/**
	 * @return \NMGR_Ajax
	 */
	public function ajax() {
		return new NMGR_Ajax();
	}

	public function items_table( $wishlist ) {
		return new \NMGR\Tables\ItemsTable( $wishlist );
	}

	/**
	 * @return \NMGR_Templates
	 */
	public function templates() {
		return new NMGR_Templates();
	}

	/**
	 * @return \NMGR_Wordpress
	 */
	public function wordpress() {
		return new NMGR_Wordpress();
	}

	/**
	 * @return \NMGR_Order
	 */
	public function order() {
		return new NMGR_Order();
	}

	/**
	 * @param type $item
	 * @return \NMGR_Wishlist_Item
	 */
	public function wishlist_item( $item = 0 ) {
		$class = apply_filters( 'nmgr_wishlist_item_class', NMGR_Wishlist_Item::class );
		return new $class( $item );
	}

	/**
	 * @param type $wishlist
	 * @return \NMGR_Wishlist
	 */
	public function wishlist( $wishlist = 0 ) {
		$class = apply_filters( 'nmgr_wishlist_class', NMGR_Wishlist::class );
		return new $class( $wishlist );
	}

	/**
	 * @param type $wishlist
	 * @return \NMGR_Account
	 */
	public function account( $wishlist = false ) {
		return new NMGR_Account( $wishlist );
	}

	/**
	 * @return \NMGR\Settings\GiftRegistry
	 */
	public function gift_registry_settings() {
		return new \NMGR\Settings\GiftRegistry( $this );
	}

	/**
	 * @return \NMGR\Settings\Wishlist
	 */
	public function wishlist_settings() {
		return new \NMGR\Settings\Wishlist( $this );
	}

	public function add_to_wishlist() {
		return new AddToWishlist();
	}

}
