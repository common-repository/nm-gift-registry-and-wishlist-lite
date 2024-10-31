<?php

/**
 * Plugin Name: NM Gift Registry and Wishlist Lite
 * Plugin URI: https://nmerimedia.com
 * Description: Advanced and highly customizable gift registry and wishlist plugin for your woocommerce store. <a href="https://nmerimedia.com/product/nm-gift-registry" target="_blank"><strong>Get PRO</strong></a> | <a href="https://nmerimedia.com/product-category/plugins/" target="__blank">See more plugins&hellip;</a>
 * Author: Nmeri Media
 * Author URI: https://nmerimedia.com
 * License: GPL V3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html\v1
 * Version: 5.4.2
 * Text Domain: nm-gift-registry-lite
 * Domain Path: /languages/
 * Review URI: https://wordpress.org/support/plugin/nm-gift-registry-and-wishlist-lite/reviews?rate=5#new-post
 * Docs URI: https://github.com/nmerii/nm-gift-registry-and-wishlist/wiki
 * Product URI: https://nmerimedia.com/product/nm-gift-registry
 * Support URI: https://nmerimedia.com/contact/
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 4.4.0
 * WC tested up to: 9.3.3
 */
defined( 'ABSPATH' ) || exit;

define( 'NMGRLITE_FILE', __FILE__ );

function nm_gift_registry_lite() {
	return NMGR_Lite_Install::get_plugin_props();
}

class NMGR_Lite_Install {

	/**
	 * @var NMGR_Setup
	 */
	private static $installer;

	public static function run() {
		if ( !class_exists( NMGR_Setup::class ) ) {
			include_once 'includes/class-nmgr-setup.php';
		}

		self::$installer = new NMGR_Setup( __FILE__ );
		self::$installer->load();
	}

	public static function get_plugin_props() {
		return self::$installer->get_plugin_props();
	}

}

NMGR_Lite_Install::run();

