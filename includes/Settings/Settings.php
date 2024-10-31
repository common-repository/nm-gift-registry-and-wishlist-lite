<?php

namespace NMGR\Settings;

use NMGR\Sub\License;
use NMGR\Settings\PluginProps;

defined( 'ABSPATH' ) || exit;

abstract class Settings {

	/**
	 * The current tab we are on
	 * @var string
	 */
	protected $current_tab;

	/**
	 * The current section we are on
	 * @var string
	 */
	protected $current_section;

	/**
	 * Name of options in the database table
	 * (also used as the options_group value in the 'register_setting' function)
	 * @var string
	 */
	public $option_name;

	/**
	 * Whether this page is a woocommerce screen, so that we can enqueue woocommerce scripts
	 * @var boolean
	 */
	public $is_woocommerce_screen = false;

	/**
	 * Whether this page is an nmerimedia screen, so that we can enqueue nmerimedia scripts
	 * @var boolean
	 */
	public $is_nmerimedia_screen = true;

	/**
	 * License instance
	 * @var License
	 */
	public $license;
	public $plugin_props;

	/**
	 * Set up settings menu and page for this plugin
	 * @param PluginProps $plugin_props The plugin properties object
	 */
	public function __construct( PluginProps $plugin_props ) {
		$this->plugin_props = $plugin_props;

		if ( !$this->option_name && !empty( $this->plugin_props->base ) ) {
			$this->option_name = $this->plugin_props->base . '_settings';
		}

		if ( is_null( $this->license ) && $this->plugin_props->is_licensed && class_exists( License::class ) ) {
			$this->license = new License();
		}
	}

	public function run() {
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_woocommerce_screen_id' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'setup_license' ) );
		add_action( 'admin_head', array( $this, 'style' ) );
		add_action( 'admin_init', array( $this, 'add_settings_fields' ) );
		add_action( 'admin_menu', array( $this, 'show_saved_settings_errors' ) );

		if ( $this->option_name ) {
			add_filter( 'pre_update_option_' . $this->option_name, array( $this, 'pre_update_option' ), 10, 2 );
			add_action( 'update_option_' . $this->option_name, array( $this, 'update_option' ), 10, 2 );
		}

		if ( $this->page_slug() === filter_input( INPUT_GET, 'page' ) ) {
			$this->current_tab = $this->get_current_tab();
			$this->current_section = $this->get_current_section();
		}
	}

	/**
	 * Add the screen id of the current plugin settings page to the
	 * array of woocommerce screen ids
	 */
	public function add_woocommerce_screen_id( $screen_ids ) {
		if ( $this->is_woocommerce_screen && $this->is_current_screen() ) {
			$screen_ids[] = $this->get_screen_id();
		}
		return $screen_ids;
	}

	/**
	 * Get the screen id of the current plugin settings page
	 * @return string
	 */
	public function get_screen_id() {
		return !is_null( $this->plugin_props ) ? 'nmeri-media_page_' . $this->plugin_props->slug : '';
	}

	/**
	 * Check if the current admin screen being viewed is an nmerimedia screen
	 * @return boolean
	 */
	public function is_nmerimedia_screen() {
		return $this->is_nmerimedia_screen && $this->is_current_screen();
	}

	/**
	 * Check if the current screen being viewed is for this settings page
	 * @return boolean
	 */
	public function is_current_screen() {
		return self::get_current_screen_id() === $this->get_screen_id();
	}

	private function get_current_screen_id() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			return $screen ? $screen->id : null;
		}
	}

	public function add_license_package( $packages ) {
		if ( !empty( $this->plugin_props->basename ) ) {
			$packages[] = $this->plugin_props->basename;
		}
		return $packages;
	}

	public function style() {
		if ( !$this->is_current_screen() ) {
			return;
		}
		?>
		<style>
			.wrap.nmerimedia-settings table.form-table:last-of-type {
				margin-bottom: 3.125em;
			}

			.wrap.nmerimedia-settings ~ h2.heading:not(:first-of-type) {
				margin-top: 3.75em;
			}

			.wrap.nmerimedia-settings label .nm-desc {
				display: inline;
			}

			.wrap.nmerimedia-settings .nm-desc:not(label .nm-desc) {
				margin-top: 8px;
			}
		</style>
		<?php
	}

	/**
	 * Parameters to use with add_menu_page for the parent menu
	 * if the settings page would be a submenu
	 *
	 * @return array
	 */
	public function parent_menu_params() {
		$title = $this->plugin_props->is_pro ?
			__( 'Nmeri Media', 'nm-gift-registry' ) :
			__( 'Nmeri Media', 'nm-gift-registry-lite' );

		$iconfile = __DIR__ . "/assets/svg/nmerimedia-icon.svg";
		if ( file_exists( $iconfile ) ) {
			ob_start();
			include $iconfile;
			$icon = 'data:image/svg+xml;base64,' . base64_encode( ob_get_clean() );
		}

		return array(
			'page_title' => $title,
			'menu_title' => $title,
			'capability' => 'manage_options',
			'menu_slug' => 'nmerimedia',
			'function' => array( $this, 'menu_page_content' ),
			'icon_url' => $icon ?? '',
			'position' => 50
		);
	}

	/**
	 * Slug of settings page
	 * @return string
	 */
	public function page_slug() {
		return $this->plugin_props->slug ?? null;
	}

	/**
	 * Get the url of the plugin settings page
	 * @return string
	 */
	public function get_page_url() {
		$slug = $this->page_slug();
		return $slug ? menu_page_url( $slug, false ) : '';
	}

	/**
	 * The menu page configuration.
	 *
	 * This function must return an array contain the parameters for either the wordpress function
	 * 'add_menu_page' or 'add_submenu_page' for the menu page to show up in the admin menu.
	 *
	 * Parameters accepted (as array keys):
	 *
	 * - parent_slug {optional|required} Required only if the page would be a submenu page.
	 * - page_title {required}
	 * - menu_title {required}
	 * - capability {required}
	 * - icon_url - {optional} Only used for top level menu pages
	 * - position - {optional}
	 *
	 * The other parameters typically used in the "add_menu_page" or "add_submenu_page" functions
	 * such as the 'menu_slug' and the callback function for displaying the page content are taken
	 * from the class methods such as 'page_slug()' and 'menu_page_content()' respectively.
	 *
	 * @return array
	 *
	 */
	protected function menu_params() {
		$title = $this->plugin_props->name;
		return array(
			'parent_slug' => $this->parent_menu_params()[ 'menu_slug' ],
			'page_title' => $title,
			'menu_title' => 0 === strpos( $title, 'NM ' ) ? str_replace( 'NM ', '', $title ) : $title,
			'capability' => 'manage_options',
			'position' => 1
		);
	}

	private function tabs() {
		$tabs = $this->get_tabs();
		uasort( $tabs, function ( $a, $b ) {
			$a[ 'priority' ] = $a[ 'priority' ] ?? 0;
			$b[ 'priority' ] = $b[ 'priority' ] ?? 0;
			return ( $a[ 'priority' ] < $b[ 'priority' ] ) ? -1 : 1;
		} );
		return $tabs;
	}

	/**
	 * Get all the settings tabs registered for the plugin
	 *
	 * This returns an array of arrays where each array key represents the slug for the
	 * settings tab and the array value is an array containing keys:
	 * - tab_title {string}  The title of the tab
	 * - sections_title {string} The title to show for all the tab sections
	 * - show_sections {boolean} whether to show the sections all at once (using do_settings_sections)
	 * - sections {array} Sections to show on the tab where each array key represents the section slug and
	 * 		                the array value represents the section title.
	 *
	 * @return array
	 */
	protected function get_tabs() {
		return array();
	}

	public function license_section() {
		$this->license->page_content();
	}

	public function setup_license() {
		if ( $this->license ) {
			$this->license->set_package_id( $this->plugin_props->basename );
			$this->license->set_option_name( $this->plugin_props->base . '_license' );
			$this->license->set_page_url( add_query_arg( 'tab', 'license', $this->get_page_url() ) );

			$this->tabs()[ 'license' ] = array(
				'tab_title' => __( 'License', 'nm-gift-registry' ),
				'priority' => 100, // Licenses tab should come last
				'content' => [ $this, 'license_section' ],
				'submit_button' => false,
			);
		}
	}

	/**
	 * Actions to perform before an option is updated.
	 * Use this only if you have set 'option_name' and you are saving options.
	 */
	public function pre_update_option( $new_value, $old_value ) {
		return $new_value;
	}

	/**
	 * Actions to perform before an option is updated.
	 * Use this only if you have set 'option_name' and you are saving options.
	 */
	public function update_option( $old_value, $new_value ) {

	}

	/**
	 * Get the saved settings option from the database
	 *
	 * @param string {optional} The field key to get from the options array
	 * @param mixed  {optional} The value to set for the field key if it doesn't exist
	 * @return mixed The field key value or the entire option value if no field key is specified.
	 */
	public function get_option( $field_key = '', $default_value = null ) {
		$option = get_option( $this->option_name, array() );
		$options = is_array( $option ) ? $option : (!$option ? array() : array( $option ));
		if ( $field_key ) {
			return array_key_exists( $field_key, $options ) ? $options[ $field_key ] : $default_value;
		}
		return $options;
	}

	public function add_menu_page() {
		if ( !empty( $this->menu_params() ) && array_key_exists( 'parent_slug', $this->menu_params() ) ) {
			if ( $this->parent_menu_params()[ 'menu_slug' ] === $this->menu_params()[ 'parent_slug' ] &&
				!menu_page_url( $this->parent_menu_params()[ 'menu_slug' ], false ) ) {
				add_menu_page(
					$this->parent_menu_params()[ 'page_title' ],
					$this->parent_menu_params()[ 'menu_title' ],
					$this->parent_menu_params()[ 'capability' ],
					$this->parent_menu_params()[ 'menu_slug' ],
					$this->parent_menu_params()[ 'function' ],
					$this->parent_menu_params()[ 'icon_url' ],
					$this->parent_menu_params()[ 'position' ]
				);
			}

			$position = null;
			if ( isset( $this->menu_params()[ 'position' ] ) ) {
				$position = $this->menu_params()[ 'position' ];
			} elseif ( $this->parent_menu_params()[ 'menu_slug' ] === $this->menu_params()[ 'parent_slug' ] ) {
				$position = 0;
			}

			add_submenu_page(
				$this->menu_params()[ 'parent_slug' ],
				$this->menu_params()[ 'page_title' ],
				$this->menu_params()[ 'menu_title' ],
				$this->menu_params()[ 'capability' ],
				$this->page_slug(),
				array( $this, 'menu_page_content' ),
				$position
			);

			if ( $this->parent_menu_params()[ 'menu_slug' ] === $this->menu_params()[ 'parent_slug' ] &&
				menu_page_url( $this->parent_menu_params()[ 'menu_slug' ], false ) ) {
				remove_submenu_page( $this->parent_menu_params()[ 'menu_slug' ], $this->parent_menu_params()[ 'menu_slug' ] );
			}
		} elseif ( !empty( $this->menu_params() ) ) {
			add_menu_page(
				$this->menu_params()[ 'page_title' ],
				$this->menu_params()[ 'menu_title' ],
				$this->menu_params()[ 'capability' ],
				$this->page_slug(),
				array( $this, 'menu_page_content' ),
				isset( $this->menu_params()[ 'icon_url' ] ) ? $this->menu_params()[ 'icon_url' ] : null,
				isset( $this->menu_params()[ 'position' ] ) ? $this->menu_params()[ 'position' ] : null
			);
		}
	}

	/**
	 * The key used to save the settings errors in the database
	 * @return string.
	 */
	public function get_settings_errors_key() {
		if ( $this->option_name ) {
			return $this->option_name . '_errors';
		}
	}

	/**
	 * The main heading of the settings page.
	 * (This comes before the settings tabs)
	 * @return string
	 */
	public function get_heading() {
		return '';
	}

	/**
	 * Outputs the template (tabs, tab sections) for the menu page content
	 * Override this if you want to set a custom menu page content
	 */
	public function menu_page_content() {
		?>
		<div class="wrap nmerimedia-settings <?php echo esc_attr( $this->page_slug() ) . ' ' . esc_attr( $this->current_tab ); ?>">
			<?php if ( !empty( $this->get_heading() ) ) : ?>
				<h1><?php echo sanitize_text_field( $this->get_heading() ); ?></h1>
			<?php endif; ?>
			<form method="post" action="options.php" enctype="multipart/form-data">
				<div class="container">
					<div class="main">
						<?php if ( 1 < count( $this->tabs() ) ) : ?>
							<nav class="nav-tab-wrapper">
								<?php
								foreach ( $this->tabs() as $slug => $args ) {
									$tab_title = isset( $args[ 'tab_title' ] ) ? $args[ 'tab_title' ] : $slug;
									$tab_url = add_query_arg( array(
										'page' => $this->page_slug(),
										'tab' => esc_attr( $slug )
										),
										remove_query_arg( 'page', $this->get_page_url() )
									);

									echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab ' .
									( $this->current_tab === $slug ? 'nav-tab-active ' : '' ) . esc_attr( $slug ) . '">' .
									wp_kses_post( apply_filters( $this->page_slug() . '_tab_title', $tab_title, $slug, $this ) ) .
									'</a>';
								}
								?>
							</nav>
						<?php endif; ?>

						<?php
						$args = isset( $this->tabs()[ $this->current_tab ] ) ? $this->tabs()[ $this->current_tab ] : '';

						$sections_title = isset( $args[ 'sections_title' ] ) ? $args[ 'sections_title' ] : null;

						// hack to keep settings_errors() above section titles and section submenu
						printf( '<h1 style=%s>%s</h1>',
							empty( $sections_title ) ? 'display:none;' : '',
							esc_html( $sections_title )
						);

						$current_tab_sections = isset( $this->tabs()[ $this->current_tab ][ 'sections' ] ) ? $this->tabs()[ $this->current_tab ][ 'sections' ] : array();

						if ( 1 < count( $current_tab_sections ) ) :
							?>
							<ul class="subsubsub">

								<?php
								$section_keys = array_keys( $current_tab_sections );

								foreach ( $current_tab_sections as $key => $label ) {
									$section_url = add_query_arg( array(
										'page' => $this->page_slug(),
										'tab' => esc_attr( $this->current_tab ),
										'section' => sanitize_title( $key )
										),
										remove_query_arg( [ 'page', 'tab', 'section' ], $this->get_page_url() )
									);
									$label = apply_filters( $this->page_slug() . '_tab_section_title', $label, $key, $this );
									echo '<li><a href="' . esc_url( $section_url ) . '" class="' . ( $this->current_section == $key ? 'current' : '' ) . '">' . sanitize_text_field( $label ) . '</a> ' . ( end( $section_keys ) == $key ? '' : '|' ) . ' </li>';
								}
								?>

							</ul><br class="clear" />
							<?php
						endif;

						settings_errors();
						settings_fields( $this->option_name );

						do_action( $this->page_slug() . '_tabs_after_nav', $this );

						if ( isset( $args[ 'show_sections' ] ) && $args[ 'show_sections' ] ) {
							$key = !empty( $this->current_section ) ? $this->current_section : $this->current_tab;
							do_settings_sections( $key );
						} elseif ( !empty( $args[ 'content' ] ) && is_callable( $args[ 'content' ] ) ) {
							call_user_func( $args[ 'content' ], $this );
						}

						do_action( $this->page_slug() . '_tab_' . $this->current_tab );

						if ( ($this->get_current_tab() && !empty( $this->tabs() ) && !isset( $args[ 'submit_button' ] )) ||
							(isset( $args[ 'submit_button' ] ) && $args[ 'submit_button' ]) ) {
							submit_button();
						}
						?>
					</div><!--- .main -->
					<?php
					$sidebar = $this->get_sidebar();
					if ( !empty( $sidebar ) ) :
						?>
						<div class="sidebar">
							<?php echo wp_kses_post( $sidebar ); ?>
						</div>
					<?php endif; ?>
				</div><!-- .container --->
			</form>
		</div>
		<?php
	}

	public function get_sidebar_links() {
		$links = [
			'docs' => $this->plugin_props->docs_url,
			'review' => $this->plugin_props->review_url,
			'support' => $this->plugin_props->support_url,
			'product' => $this->plugin_props->is_pro ? '' : $this->plugin_props->product_url,
		];

		$links_html = '';

		foreach ( $links as $key => $value ) {
			if ( $value ) {
				switch ( $key ) {
					case 'docs':
						$text = $this->plugin_props->is_pro ?
							__( 'Docs', 'nm-gift-registry' ) :
							__( 'Docs', 'nm-gift-registry-lite' );
						break;
					case 'review':
						$text = $this->plugin_props->is_pro ?
							__( 'Review', 'nm-gift-registry' ) :
							__( 'Review', 'nm-gift-registry-lite' );
						break;
					case 'support':
						$text = $this->plugin_props->is_pro ?
							__( 'Support', 'nm-gift-registry' ) :
							__( 'Support', 'nm-gift-registry-lite' );
						break;
					case 'product':
						$text = __( 'Get PRO', 'nm-gift-registry-lite' );
						break;
				}
				$links_html .= '<li><a href="' . $value . '">' . $text . '</a></li>';
			}
		}

		return !empty( $links_html ) ? "<ul>$links_html</ul>" : '';
	}

	public function get_sidebar() {
		return $this->get_sidebar_links();
	}

	/**
	 * Get the current settings tab being viewed
	 *
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 * @return string
	 */
	public function get_current_tab( $request = array() ) {
		$page = $request[ 'page' ] ?? sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'page' ) ) );
		if ( $page && $this->page_slug() === $page ) {
			$tab = $request[ 'tab' ] ?? filter_input( INPUT_GET, 'tab' );
			if ( !empty( $tab ) ) {
				return sanitize_title( wp_unslash( $tab ) );
			} else {
				$tabs = array_keys( $this->tabs() );
				return reset( $tabs );
			}
		}
	}

	/**
	 * Get the current settings section being viewed
	 *
	 * @param array $request The associative array used to determine the section, typically
	 * $_GET or HTTP_REFERER
	 * @return string
	 */
	public function get_current_section( $request = array() ) {
		$page = $request[ 'page' ] ?? sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'page' ) ) );
		if ( $page && $this->page_slug() === $page ) {
			$raw_section = $request[ 'section' ] ?? $_GET[ 'section' ] ?? '';
			$section = $raw_section ? sanitize_title( wp_unslash( $raw_section ) ) : '';
			$tab = $this->get_current_tab( $request );

			if ( $tab && !$section ) {
				$tab_args = isset( $this->tabs()[ $tab ] ) ? $this->tabs()[ $tab ] : '';
				if ( !empty( $tab_args ) && isset( $tab_args[ 'sections' ] ) ) {
					$sec = array_flip( $tab_args[ 'sections' ] );
					$section = reset( $sec );
				}
			}
			return $section;
		}
	}

	public function get_fields() {
		$fields = array();

		foreach ( array_keys( $this->tabs() ) as $tab ) {
			$this_section = $this->get_tab_sections( $tab );
			if ( $this_section ) {
				foreach ( $this_section as $args ) {
					foreach ( $args as $args_key => $args_value ) {
						if ( 'fields' == $args_key ) {
							$fields = array_merge( $fields, $args_value );
						}
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Get the default values for all plugin options
	 *
	 * @return array
	 */
	public function get_default_field_values() {
		return $this->get_default_values_for_fields( $this->get_fields() );
	}

	public function get_default_values_for_fields( $fields ) {
		$fields_vals = array();

		foreach ( $fields as $value ) {
			// Key to use to save the value
			$option_key = $this->get_field_key( $value );
			if ( $option_key ) {
				if ( isset( $value[ 'option_group' ] ) && $value[ 'option_group' ] ) {
					$fields_vals[ $option_key ][] = isset( $value[ 'default' ] ) ? $value[ 'default' ] : '';
				} else {
					$fields_vals[ $option_key ] = isset( $value[ 'default' ] ) ? $value[ 'default' ] : '';
				}

				if ( is_array( $fields_vals[ $option_key ] ) ) {
					$fields_vals[ $option_key ] = array_filter( $fields_vals[ $option_key ] );
				}
			}
		}

		return $fields_vals;
	}

	/**
	 * Save the default values for all plugin options in the database
	 * (This function should typically only be called on plugin installation or activation).
	 */
	public function save_default_values() {
		$defaults = $this->get_default_field_values();
		$existing_settings = $this->get_option();

		if ( $existing_settings ) {
			$defaults = apply_filters(
				$this->option_name . '_save_default_values',
				array_merge( $defaults, $existing_settings ),
				$this
			);

			delete_option( $this->option_name );
		}

		add_option( $this->option_name, $defaults );
	}

	/**
	 * Get all the sections that are in a settings tab
	 *
	 * @param string $tab The tab (Default is current tab)
	 * @return array
	 */
	public function get_tab_sections( $tab = '' ) {
		$tab = $tab ? $tab : $this->current_tab;
		$tab_sections = $tab . '_tab_sections';

		if ( method_exists( $this, $tab_sections ) ) {
			return apply_filters( $this->page_slug() . '_tab_sections', call_user_func( array( $this, $tab_sections ) ), $tab );
		}
	}

	public function add_settings_fields() {
		if ( !$this->option_name ) {
			return;
		}

		register_setting(
			$this->option_name,
			$this->option_name,
			array( $this, 'validate' )
		);

		$sections = $this->get_tab_sections();
		if ( !$sections ) {
			return;
		}

		foreach ( $sections as $key => $section ) {
			$page = isset( $section[ 'section' ] ) ? $section[ 'section' ] : $this->current_tab;
			add_settings_section(
				$key,
				isset( $section[ 'title' ] ) ? $section[ 'title' ] : '',
				array( $this, 'settings_section_description' ),
				$page
			);

			if ( !isset( $section[ 'fields' ] ) ) {
				continue;
			}

			foreach ( $section[ 'fields' ] as $key2 => $args2 ) {
				if ( !($args2[ 'show' ] ?? true) ) {
					unset( $section[ 'fields' ] [ $key2 ] );
				}
			}

			foreach ( $section[ 'fields' ] as $field ) {
				if ( 'heading' === $field[ 'type' ] ) {
					$field[ 'id' ] = uniqid();
				}

				if ( !isset( $field[ 'id' ] ) || (isset( $field[ 'show_in_group' ] ) && $field[ 'show_in_group' ]) ) {
					continue;
				}

				$class = 'heading' === ($field[ 'type' ] ?? '') ? [ 'hidden' ] : [];

				if ( !empty( $field[ 'class' ] ) ) {
					$class = array_merge( $class, ( array ) $field[ 'class' ] );
				}

				if ( $this->is_pro_field( $field ) ) {
					$class[] = 'is-pro';
				}

				add_settings_field(
					$field[ 'id' ],
					$this->get_formatted_settings_field_label( $field ),
					array( $this, 'output_field' ),
					$page,
					$key,
					array(
						'class' => implode( ' ', $class ),
						'field' => $field,
						'fields' => $section[ 'fields' ]
					)
				);
			}
		}
	}

	/**
	 * Echo content at the top of the section, between the heading and field
	 */
	public function settings_section_description( $section ) {
		$tab_sections = $this->get_tab_sections();

		if ( isset( $tab_sections[ $section[ 'id' ] ] ) ) {
			if ( isset( $tab_sections[ $section[ 'id' ] ][ 'description' ] ) ) {
				echo "<div class='section-description'>" . wp_kses_post( $tab_sections[ $section[ 'id' ] ][ 'description' ] ) . '</div>';
			}
		}
	}

	public function is_pro_field( $field ) {
		return ($field[ 'pro' ] ?? false) && !$this->plugin_props->is_pro;
	}

	public function get_pro_version_text( $with_html = true ) {
		$text = __( 'PRO', 'nm-gift-registry-lite' );
		return $with_html ? '<span class="nmerimedia-pro-version-text">(' . $text . ')</span>' : $text;
	}

	/**
	 * Format the label of a settings field before display
	 * This function is used to add error notification colors to the field label
	 * in situations where the field involved has an error
	 *
	 * @since 2.0.0
	 * @param type $field
	 */
	public function get_formatted_settings_field_label( $field ) {
		if ( !isset( $field[ 'label' ] ) ) {
			return '';
		}

		$label = $field[ 'label' ];

		if ( $this->is_pro_field( $field ) ) {
			$label = $label . ' ' . $this->get_pro_version_text();
		}

		if ( isset( $field[ 'error_codes' ] ) ) {
			$title = '';
			foreach ( $field[ 'error_codes' ] as $code ) {
				if ( $this->has_settings_error_code( $code ) ) {
					$title .= $this->get_error_message_by_code( $code );
				}
			}

			if ( !empty( $title ) ) {
				$label = '<span class="nmerimedia-settings-error" title="' . $title . '">' . $label . '</span>';
			}
		}

		if ( isset( $field[ 'desc_tip' ] ) ) {
			$label .= ' ' . $this->help_tip( $field[ 'desc_tip' ] );
		}

		return $label;
	}

	protected function help_tip( $title ) {
		return '<span class="nmerimedia-help" title="' . $title . '"> &#9432;</span>';
	}

	/**
	 * Check if particular settings error codes exists if we have errors after saving settings
	 *
	 * @since 2.0.0
	 * @param string|array $code Error code or array of error codes
	 * @return boolean
	 */
	public function has_settings_error_code( $code ) {
		foreach ( get_settings_errors( $this->page_slug() ) as $error ) {
			if ( in_array( $error[ 'code' ], ( array ) $code, true ) ) {
				return true;
			}
		}
		return false;
	}

	public function get_error_message_by_code( $code ) {
		$message = '';
		$codes_to_messages = $this->get_error_codes_to_messages();
		foreach ( ( array ) $code as $c ) {
			$message .= ($codes_to_messages[ $c ] ?? '') . '&#10;';
		}
		return trim( $message );
	}

	public function get_field_key( $field ) {
		return isset( $field[ 'option_name' ] ) ? $field[ 'option_name' ] : (isset( $field[ 'id' ] ) ? $field[ 'id' ] : '');
	}

	/**
	 * Get the name attribute of a form field based on the arguments supplied to the field
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function get_field_name( $field ) {
		if ( isset( $field[ 'name' ] ) ) {
			$name = $field[ 'name' ];
		} else {
			$key = $this->get_field_key( $field );
			$name = $this->option_name . "[$key]";
		}

		$name = isset( $field[ 'option_group' ] ) && $field[ 'option_group' ] ? $name . '[]' : $name;
		return $name;
	}

	/**
	 * Get the value saved for a field in the database
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function get_field_value( $field ) {
		$field_default = isset( $field[ 'default' ] ) ? $field[ 'default' ] : '';
		$field_key = $this->get_field_key( $field );
		$value = '';

		$get_option_func = array( $this, 'get_option' );

		if ( $field_key && is_callable( $get_option_func ) ) {
			$value = call_user_func( $get_option_func, $field_key, $field_default );
		} elseif ( isset( $field[ 'name' ] ) ) {
			$value = get_option( $field[ 'name' ], $field_default );
		}
		return $value;
	}

	/**
	 * Adds html checked attribute to a field if it should be checked
	 * Should be used for checkboxes, returns empty string otherwise.
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function checked( $field, $echo = false ) {
		$stored_value = ( array ) $this->get_field_value( $field );
		$field_value = isset( $field[ 'value' ] ) ? $field[ 'value' ] : 1;

		if ( in_array( $field_value, $stored_value ) ) {
			$result = " checked='checked'";
		} else {
			$result = '';
		}

		if ( $echo ) {
			echo esc_attr( $result );
		}
		return $result;
	}

	/**
	 * Adds html selected attribute to a select option if it should be selected
	 * Should be used for select option inputs, returns empty string otherwise.
	 *
	 * @param array $option_value The registered value for the option element
	 * @param array $field Arguments supplied to the field
	 */
	public function selected( $option_value, $field, $echo = false ) {
		$stored_value = ( array ) $this->get_field_value( $field );

		if ( in_array( $option_value, $stored_value ) ) {
			$result = " selected='selected'";
		} else {
			$result = '';
		}

		if ( $echo ) {
			echo esc_html( $result );
		}
		return $result;
	}

	public function output_field( $setting ) {
		$field = $setting[ 'field' ];
		$fields = $setting[ 'fields' ];

		// Ensure necessary fields are set
		$field_id = isset( $field[ 'id' ] ) ? esc_attr( $field[ 'id' ] ) : '';
		$field_type = isset( $field[ 'type' ] ) ? esc_attr( $field[ 'type' ] ) : '';
		$field_desc = isset( $field[ 'desc' ] ) ? '<div class="nm-desc">' . $field[ 'desc' ] . '</div>' : '';
		$field_placeholder = isset( $field[ 'placeholder' ] ) ? esc_attr( $field[ 'placeholder' ] ) : '';
		$field_class = isset( $field[ 'class' ] ) ? esc_attr( $field[ 'class' ] ) : '';
		$field_css = isset( $field[ 'css' ] ) ? esc_attr( $field[ 'css' ] ) : '';
		$field_name = esc_attr( $this->get_field_name( $field ) );
		$raw_field_value = $this->get_field_value( $field );
		$field_value = !is_array( $raw_field_value ) ? esc_attr( $raw_field_value ) : $raw_field_value;
		$inline_class = isset( $field[ 'inline' ] ) && true === $field[ 'inline' ] ? 'nmerimedia-inline' : '';
		$field_options = isset( $field[ 'options' ] ) ? $field[ 'options' ] : array();
		$custom_attributes = array();

		if ( isset( $field[ 'custom_attributes' ] ) && is_array( $field[ 'custom_attributes' ] ) ) {
			foreach ( $field[ 'custom_attributes' ] as $attribute => $attribute_value ) {
				if ( false === $attribute_value ) {
					unset( $field[ 'custom_attributes' ][ $attribute ] );
					break;
				}
				$custom_attributes[] = $attribute . '="' . $attribute_value . '"';
			}
		}
		$field_custom_attributes = implode( ' ', $custom_attributes );

		if ( isset( $field[ 'show_in_group' ] ) && $field[ 'show_in_group' ] ) {
			return;
		}

		switch ( $field_type ) {
			case 'heading':
				echo '</td></tr></tbody></table>';
				echo isset( $field[ 'label' ] ) && !empty( $field[ 'label' ] ) ? "<h2 class='heading'>" .
					sanitize_text_field( $field[ 'label' ] ) . '</h2>' : '';
				echo (!empty( $field_desc )) ? wp_kses_post( $field_desc ) : '';
				echo '<table class="form-table" role="presentation"><tbody><tr class="hidden"><th></th><td>';
				break;

			case 'text':
			case 'password':
			case 'number':
				printf( "<input type='%s' id='%s' name='%s' size='40' value='%s' placeholder='%s' %s />",
					esc_attr( $field_type ),
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( $field_value ),
					esc_attr( $field_placeholder ),
					wp_kses( $field_custom_attributes, [] )
				);
				break;

			case 'textarea':
				printf( "<textarea name='%s' cols='45' rows='4' placeholder='%s'>%s</textarea>",
					esc_attr( $field_name ),
					esc_attr( $field_placeholder ),
					wp_kses_post( $field_value )
				);
				break;

			case 'checkbox':
				if ( isset( $field[ 'checkboxgroup' ] ) ) {
					$group_fields = array_filter( $fields, function ( $f ) use ( $field ) {
						return isset( $f[ 'checkboxgroup' ] ) && $f[ 'checkboxgroup' ] == $field[ 'checkboxgroup' ];
					} );

					if ( $group_fields ) {
						foreach ( $group_fields as $group_field ) {
							printf( "<label><input %s value='%s' name='%s' type='checkbox' /> %s</label><br />",
								esc_attr( $this->checked( $group_field ) ),
								isset( $group_field[ 'value' ] ) ? esc_attr( $group_field[ 'value' ] ) : 1,
								esc_attr( $this->get_field_name( $group_field ) ),
								!empty( $group_field[ 'desc' ] ) ? wp_kses_post( $group_field[ 'desc' ] ) : ''
							);
						}
					}
				} else {
					printf( "<label><input %s value='1' name='%s' type='checkbox' %s /> %s</label>",
						esc_attr( $this->checked( $field ) ),
						esc_attr( $field_name ),
						wp_kses( $field_custom_attributes, [] ),
						!empty( $field_desc ) ? wp_kses_post( $field_desc ) : ''
					);
				}
				break;

			case 'radio':
				?>
				<div class="nmerimedia-input-group <?php echo esc_attr( $inline_class ); ?>">
					<?php
					foreach ( $field[ 'options' ] as $key => $val ) :
						$checked = checked( $key, $field_value, false );
						?>
						<div><label><input <?php echo esc_attr( $checked ) . ' ' . wp_kses( $field_custom_attributes, [] ); ?>
									value="<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									type="radio"/><?php echo wp_kses_post( $val ); ?></label></div>
						<?php endforeach; ?>
				</div>
				<?php
				break;

			case 'radio_with_image':
				?>
				<div class="nmerimedia-btn-group nmerimedia-input-group <?php echo esc_attr( $inline_class ); ?>">
					<?php
					foreach ( $field[ 'options' ] as $key => $args ) :
						$checked = checked( $key, $field_value, false );
						$option_id = "{$field_id}-{$key}";
						$title = $args[ 'label_title' ] ?? '';
						?>
						<div class="nmerimedia-btn <?php echo $this->is_pro_field( $args ) ? 'is-pro' : ''; ?>">
							<input <?php echo esc_attr( $checked ); ?>
								id="<?php echo esc_attr( $option_id ); ?>"
								type="radio"
								value="<?php echo esc_attr( $key ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>">
							<label for="<?php echo esc_attr( $option_id ); ?>"
										 title="<?php echo esc_attr( $title ); ?>"
										 class="nmgr-tip">
											 <?php
											 echo isset( $args[ 'image' ] ) ? wp_kses_post( $args[ 'image' ] ) : '';
											 echo isset( $args[ 'label' ] ) ? wp_kses_post( $args[ 'label' ] ) : '';
											 ?>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
				<?php
				break;

			case 'select':
				printf( "<select class='%s' name='%s' id='%s' %s>",
					esc_attr( $field_class ),
					esc_attr( $field_name ),
					esc_attr( $field_id ),
					wp_kses( $field_custom_attributes, [] )
				);
				foreach ( $field_options as $key => $val ) {
					printf( "<option value='%s' %s>%s</option>",
						esc_attr( $key ),
						esc_attr( $this->selected( $key, $field ) ),
						esc_html( $val )
					);
				}
				echo '</select>';
				break;

			case 'select_page':
				$args = array(
					'name' => esc_attr( $field_name ),
					'id' => esc_attr( $field_id ),
					'sort_column' => 'menu_order',
					'sort_order' => 'ASC',
					'show_option_none' => ' ',
					'class' => esc_attr( $field_class ),
					'echo' => false,
					'selected' => absint( $field_value ),
				);

				if ( isset( $field[ 'args' ] ) ) {
					$args = wp_parse_args( $field[ 'args' ], $args );
				}

				$html = str_replace( ' id=', " data-placeholder='" . esc_attr( $field_placeholder ) . "' style='" . esc_attr( $field_css ) . "' class='" . esc_attr( $field_class ) . "' id=", wp_dropdown_pages( $args ) );

				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				break;

			default:
				break;
		}

		// These fields should not have description
		$exclude_fields = array( 'checkbox', 'heading' );
		if ( $field_desc && !in_array( $field_type, $exclude_fields ) ) {
			echo wp_kses_post( $field_desc );
		}
	}

	// Validate fields before save
	public function validate( $input ) {
		$referer = array();
		parse_str( wp_parse_url( wp_get_referer(), PHP_URL_QUERY ), $referer );

		// We're only dealing with fields posted from a particular tab or section
		$tab = $this->get_current_tab( $referer );
		$section = $this->get_current_section( $referer );

		$tab_sections = $this->get_tab_sections( $tab );

		if ( !$tab_sections ) {
			return $input;
		}

		$fields = array();

		foreach ( $tab_sections as $content ) {
			foreach ( $content as $prop => $value ) {
				if ( 'fields' == $prop ) {
					if ( $section ) {
						if ( isset( $content[ 'section' ] ) && $content[ 'section' ] === $section ) {
							$fields = array_merge( $fields, $value );
							break 2;
						}
					} else {
						$fields = array_merge( $fields, $value );
					}
				}
			}
		}

		foreach ( $fields as $field ) {
			// Key for the field
			$key = $this->get_field_key( $field );

			// Get posted value
			$posted_value = isset( $input[ $key ] ) ? $input[ $key ] : null;

			// If this field value should be an array but the posted value is empty, convert it to array
			if ( !$posted_value && ($field[ 'option_group' ] ?? false) ) {
				$posted_value = [];
			}

			$input[ $key ] = wp_kses_post_deep( $posted_value );
		}

		/**
		 * Set settings errors here
		 */
		$f_input = apply_filters( $this->page_slug() . '_validate_input', $input, $this );

		$options = array_merge( $this->get_option(), $f_input );

		do_action( $this->page_slug() . '_before_save_input', $input, $this );

		if ( get_settings_errors( $this->page_slug() ) ) {
			$settings_saved = $this->plugin_props->is_pro ?
				__( 'Settings saved.', 'nm-gift-registry' ) :
				__( 'Settings saved.', 'nm-gift-registry-lite' );
			add_settings_error( $this->page_slug(), 'settings-saved', $settings_saved, 'success' );
		}

		$current_section_error_codes = $this->get_current_section_error_codes( $referer );

		foreach ( $current_section_error_codes as $k => $code ) {
			if ( $this->has_settings_error_code( $code ) ) {
				unset( $current_section_error_codes[ $k ] );
			}
		}

		$this->delete_settings_errors( $current_section_error_codes );

		return $options;
	}

	/**
	 * Get the fields for the current settings section being viewed
	 *
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 * @return array
	 */
	public function get_current_section_fields( $request = array() ) {
		$tab = $this->get_current_tab( $request );
		$section = $this->get_current_section( $request );
		$tab_sections = $this->get_tab_sections( $tab );
		$fields = array();

		if ( $tab_sections ) {
			foreach ( $tab_sections as $content ) {
				foreach ( $content as $prop => $value ) {
					if ( 'fields' == $prop ) {
						if ( $section ) {
							if ( isset( $content[ 'section' ] ) && $content[ 'section' ] === $section ) {
								$fields = array_merge( $fields, $value );
								break 2;
							}
						} else {
							$fields = array_merge( $fields, $value );
						}
					}
				}
			}
		}
		return $fields;
	}

	/**
	 * Get the error codes available for all the fields in a settings section
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 */
	public function get_current_section_error_codes( $request = array() ) {
		$error_codes = array();

		foreach ( $this->get_current_section_fields( $request ) as $field ) {
			if ( isset( $field[ 'error_codes' ] ) ) {
				$error_codes = array_merge( $error_codes, $field[ 'error_codes' ] );
			}
		}
		return array_unique( $error_codes );
	}

	/**
	 * Save a settings error to the database and also register it to be
	 * shown to the user using 'add_settings_error()'.
	 *
	 * @param string $code Settings error code
	 * @param string $message Settings error message to display to user
	 * @param string $type Type of error. Default warning.
	 */
	public function save_settings_error( $code, $message, $type = 'warning' ) {
		add_settings_error( $this->page_slug(), $code, $message, $type );
		$saved = get_option( $this->get_settings_errors_key(), array() );
		$saved[ $code ] = array( 'type' => $type );
		update_option( $this->get_settings_errors_key(), $saved );
	}

	/**
	 * Delete settings errors saved to the database
	 *
	 * @param array $codes Codes for settings errors to delete
	 */
	public function delete_settings_errors( $codes ) {
		if ( !empty( $codes ) ) {
			$saved = get_option( $this->get_settings_errors_key(), array() );
			foreach ( $codes as $code ) {
				if ( isset( $saved[ $code ] ) ) {
					unset( $saved[ $code ] );
				}
			}
			update_option( $this->get_settings_errors_key(), $saved );
		}
	}

	/**
	 * Get the settings errors that have been saved to the database
	 * @return array
	 */
	public function get_saved_settings_errors() {
		return get_option( $this->get_settings_errors_key(), array() );
	}

	public function show_saved_settings_errors() {
		$error_codes = $this->get_current_section_error_codes();
		if ( !empty( $error_codes ) ) {
			$saved_settings_errors = $this->get_saved_settings_errors();

			foreach ( $error_codes as $code ) {
				if ( !$this->has_settings_error_code( $code ) && array_key_exists( $code, $saved_settings_errors ) ) {
					$error = $saved_settings_errors[ $code ];
					$error_msg = $this->get_error_message_by_code( $code );
					add_settings_error( $this->page_slug(), $code, $error_msg, $error[ 'type' ] );
				}
			}
		}
	}

	public function get_error_codes_to_messages() {
		return [];
	}

}
