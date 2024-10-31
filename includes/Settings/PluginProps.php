<?php

namespace NMGR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The properties of the plugin
 * @class PluginProps
 */
class PluginProps {

	public $name;
	public $version;
	public $url;
	public $path;
	public $docs_url;
	public $product_url;
	public $review_url;
	public $support_url;

	/**
	 * Plugin root filepath e.g /www/html/wordpress/wp-content/plugins/nm-plugin/nm-plugin.php
	 */
	public $file;

	/**
	 * Slug of plugin root file slug e.g nm-plugin
	 */
	public $slug;

	/**
	 * Basename of plugin root file e.g. nm-plugin/nm-plugin.php
	 */
	public $basename;

	/**
	 * Plugin base e.g nm_plugin
	 * Usually taken from plugin root file
	 */
	public $base;

	/**
	 * Whether the plugin is licensed
	 * @var boolean
	 */
	public $is_licensed = false;

	/**
	 * Plugin notices
	 */
	public $notices = [];

	/**
	 * Whether the plugin is disabled.
	 * Disabled plugins are active but not running
	 */
	public $is_disabled;

	/**
	 * Pro version
	 * @var boolean
	 */
	public $is_pro = false;

	public function __construct( $filepath ) {
		$this->file = $filepath;
		$this->url = plugin_dir_url( $filepath );
		$this->path = plugin_dir_path( $filepath );
		$this->slug = pathinfo( $filepath, PATHINFO_FILENAME );
		$this->basename = plugin_basename( $filepath );
		$this->base = str_replace( '-', '_', $this->slug );

		$plugin_headers = array(
			'name' => 'Plugin Name',
			'version' => 'Version',
			'docs_url' => 'Docs URI',
			'support_url' => 'Support URI',
			'review_url' => 'Review URI',
			'product_url' => 'Product URI',
		);

		$filedata = get_file_data( $filepath, $plugin_headers );

		foreach ( $filedata as $key => $val ) {
			if ( property_exists( $this, $key ) ) {
				$this->{$key} = $val;
			}
		}
	}

	public function is_disabled() {
		return $this->is_disabled;
	}

}
