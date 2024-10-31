<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;
use NMGR\Fields\Fields;
use NMGR\Tables\Table;

/**
 * @sync
 */
defined( 'ABSPATH' ) || exit;

if ( file_exists( __DIR__ . '/Sub/nmgr-functions-pro.php' ) ) {
	require_once 'Sub/nmgr-functions-pro.php';
}

/**
 * Returns Plugin main properties
 *
 * @return object
 */
function nmgr() {
	if ( function_exists( 'nm_gift_registry' ) ) {
		return nm_gift_registry();
	} elseif ( function_exists( 'nm_gift_registry_lite' ) ) {
		return nm_gift_registry_lite();
	}
}

/**
 * Check if we are viewing a single wishlist page on the frontend
 *
 * @return boolean
 */
function is_nmgr_wishlist() {
	return is_nmgr_wishlist_page( 'single' );
}

/**
 * Check if we are on a single wishlist edit page or the all wishlists page in the admin area
 *
 * @return boolean
 */
function is_nmgr_admin() {
	global $current_screen;

	$bool = false;

	if ( wp_doing_ajax() ) {
		$referer = parse_url( wp_get_referer() );

		if ( !empty( $referer[ 'path' ] ) && !empty( $referer[ 'query' ] ) ) {
			$path = $referer[ 'path' ];
			$query = [];
			parse_str( $referer[ 'query' ], $query );

			$other_page = false !== strpos( $path, 'edit.php' ) &&
				'nm_gift_registry' === ($query[ 'post_type' ] ?? '');

			$edit_page = false !== strpos( $path, 'post.php' ) &&
				'edit' === ($query[ 'action' ] ?? '') &&
				!empty( $query[ 'post' ] );

			$bool = ($other_page || $edit_page);
		}
	} elseif ( is_admin() && !wp_doing_ajax() ) {
		if ( !isset( $current_screen ) ) {
			$bool = false;
		} else {
			$bool = isset( $current_screen->post_type ) && 'nm_gift_registry' === $current_screen->post_type;
		}
	}

	return apply_filters( 'is_nmgr_admin', $bool );
}

/**
 * Check if we are on the wishlists search page
 *
 * @param WP_Query $query The query object. Uses global $wp_query by default.
 * @return boolean
 */
function is_nmgr_search( $query = '' ) {
	global $wp_query, $post;

	$the_query = is_a( $query, 'WP_Query' ) ? $query : $wp_query;

	$bool = (is_search() &&
		isset( $the_query->query_vars[ 'post_type' ] ) &&
		'nm_gift_registry' === $the_query->query_vars[ 'post_type' ]) ||
		isset( $the_query->query_vars[ 'nmgr_s' ] ) || isset( $_GET[ 'nmgr_s' ] ) ||
		(is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'nmgr_search' )) ||
		(is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'nmgr_search_results' ));

	return apply_filters( 'is_nmgr_search', $bool );
}

/**
 * Check if we are currently in an NM Gift Registry account section
 *
 * Registered account sections are:
 * - profile
 * - items
 * - images
 * - shipping
 * - messages
 * - settings
 *
 * @return boolean
 */
function is_nmgr_account_section( $section = '' ) {
	$bool = is_nmgr_wishlist_page( 'account_section' );
	if ( $section ) {
		$bool = $bool && $section === get_query_var( 'nmgr_action' );
	}

	return apply_filters( 'is_nmgr_account_section', $bool );
}

/**
 * Check whether the gift-registry or wishlist module is enabled
 *
 * @param int $user_id User id. Optional. Defaults to current logged in user.
 * @return boolean
 */
function is_nmgr_enabled( $type = 'gift-registry' ) {
	if ( !in_array( $type, [ 'gift-registry', 'wishlist' ] ) ) {
		$type = 'gift-registry';
	}
	return apply_filters( 'is_nmgr_enabled', (( bool ) nmgr_get_type_option( $type, 'enable' ) ), $type );
}

/**
 * Determine whether we are in the shop loop
 *
 * This is based on whether any of the registered action hooks for displaying product
 * content within loops in content-product.php is being fired.
 *
 * This function is typically preferred to checking for woocommerce archive
 * pages with is_shop() or is_product_taxonomy() because it covers the shop loop
 * in every location including places which may not be typical archive locations
 * such as the 'Related Products' section on single product pages.
 *
 * @return boolean
 */
function is_nmgr_shop_loop() {
	$actions = apply_filters( 'nmgr_shop_loop_actions', array(
		'woocommerce_before_shop_loop_item',
		'woocommerce_before_shop_loop_item_title',
		'woocommerce_shop_loop_item_title',
		'woocommerce_after_shop_loop_item_title',
		'woocommerce_after_shop_loop_item'
		) );

	foreach ( $actions as $action ) {
		if ( doing_action( $action ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Paged navigation for search results
 */
function nmgr_paging_nav() {
	$args = apply_filters( 'nmgr_paging_nav_args', array(
		'next_text' => nmgr()->is_pro ?
		__( 'Next', 'nm-gift-registry' ) :
		__( 'Next', 'nm-gift-registry-lite' ),
		'prev_text' => nmgr()->is_pro ?
		__( 'Previous', 'nm-gift-registry' ) :
		__( 'Previous', 'nm-gift-registry-lite' ),
		) );
	$pagination = apply_filters( 'nmgr_paging_nav', get_the_posts_pagination( $args ) );
	echo $pagination;
}

/**
 * Get an option value for the plugin
 *
 * If an option key is not provided, values for all plugin keys are returned
 * @return mixed
 */
function nmgr_get_option( $option_key = '', $default_value = null, $type = '' ) {
	$settings = nmgr()->is_pro ? [] : get_option( 'nmgr_default_pro_fields_values', [] );
	$options = array_diff_key( get_option( 'nmgr_settings', array() ), $settings );

	if ( $option_key ) {
		$option_key = 'wishlist' === $type ? 'wishlist_' . $option_key : $option_key;
		$value = $default_value;

		if ( array_key_exists( $option_key, $options ) ) {
			$value = $options[ $option_key ];
		}

		return apply_filters( 'nmgr_get_option', $value, [
			'option_key' => $option_key,
			'default_value' => $default_value,
			'type' => $type,
			'options' => $options
			] );
	} elseif ( $type ) {
		if ( 'wishlist' === $type ) {
			foreach ( $options as $key => $value ) {
				if ( 0 !== strpos( $key, 'wishlist_' ) ) {
					unset( $options[ $key ] );
				}
			}
		} elseif ( 'gift-registry' === $type ) {
			foreach ( $options as $key => $value ) {
				if ( 0 === strpos( $key, 'wishlist_' ) ) {
					unset( $options[ $key ] );
				}
			}
		}
	}

	return $options;
}

function nmgr_get_type_option( $type, $option_key = '', $default_value = null ) {
	return nmgr_get_option( $option_key, $default_value, $type );
}

/**
 * Get the full anchor tag for a user's wishlist page on the frontend or the edit page in the admin area if in admin
 *
 * @param obj $wishlist Wishlist object
 * @param array $args Parameters to be used in the anchor tag.
 * Accepted parameters
 *  title - title attriubte of the link element.
 * content - content to display as the link text.
 * @return string anchor html tag
 */
function nmgr_get_wishlist_link( $wishlist, $args = array() ) {
	if ( $wishlist ) {
		$url = (!is_nmgr_admin() && !is_admin()) ?
			$wishlist->get_permalink() :
			get_edit_post_link( $wishlist->get_id() );
		$title = isset( $args[ 'title' ] ) ? esc_attr( $args[ 'title' ] ) : '';
		$content = isset( $args[ 'content' ] ) ? $args[ 'content' ] : $wishlist->get_title();
		return sprintf( '<a href="%s" title="%s">%s</a>', $url, $title, $content );
	}
}

/**
 * Get the current wishlist id based on the global context, current page, or query
 *
 * @return int wishlist id
 */
function nmgr_get_current_wishlist_id() {
	global $wp_query;

	$the_post = !empty( $wp_query ) ? get_queried_object_id() : 0;

	if ( is_nmgr_wishlist() || is_nmgr_wishlist_page( 'account_section' ) ) {
		$nmgr_w = sanitize_text_field( wp_unslash( get_query_var( 'nmgr_w' ) ) );
		$type = nmgr_get_current_type();

		if ( $nmgr_w ) {
			$the_post = get_page_by_path( $nmgr_w, '', [ 'nm_gift_registry' ] );
		} elseif ( !nmgr_get_type_option( $type, 'allow_multiple_wishlists' ) ) {
			$the_post = nmgr_get_user_default_wishlist_id( '', $type );
		}
	}

	if ( $the_post && is_numeric( $the_post ) ) {
		$the_post = get_post( $the_post );
	}

	return ( isset( $the_post->ID ) && 'nm_gift_registry' === get_post_type( $the_post->ID ) ) ?
		( int ) $the_post->ID : 0;
}

/**
 * Get all the wishlist ids for a user
 *
 * @param int|string $user_id The user id (optional).
 * Defaults to current logged in user id or guest user id cookie value.
 * @param string $type The wishlist type taxonomy term slug (gift-registry or wishlist)
 * @return array
 */
function nmgr_get_user_wishlist_ids( $user_id = '', $type = 'gift-registry' ) {
	$userid = $user_id ? $user_id : nmgr_get_current_user_id();

	if ( !$userid ) {
		return array();
	}

	$args = [
		'posts_per_page' => -1,
		'post_type' => 'nm_gift_registry',
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'no_found_rows' => true,
		'post_status' => nmgr_get_post_statuses(),
		'fields' => 'ids',
		'meta_query' => [
			[
				'key' => '_nmgr_user_id',
				'value' => $userid,
				'compare' => '=',
			]
		],
		'tax_query' => [
			[
				'taxonomy' => 'nm_gift_registry_type',
				'field' => 'slug',
				'terms' => $type,
				'operator' => 'IN',
			]
		],
	];

	return get_posts( $args );
}

/**
 * Get all the wishlists for a user
 *
 * This function only retrieves active wishlists (wishlists with valid plugin statuses - @see nmgr_get_post_statuses())
 * as these are the statuses used for wishlists on the frontend.
 *
 * @param int|string $user_id The user id (optional).
 * Defaults to current logged in user id or guest user id cookie value.
 * @param string $type The wishlist type taxonomy term slug (gift-registry or wishlist)
 * @return array
 */
function nmgr_get_user_wishlists( $user_id = '', $type = 'gift-registry' ) {
	$posts = nmgr_get_user_wishlist_ids( $user_id, $type );

	foreach ( $posts as $key => $id ) {
		$posts[ $id ] = $posts[ $key ];
		unset( $posts[ $key ] );
	}

	return !empty( $posts ) ? array_filter( array_map( 'nmgr_get_wishlist', $posts ) ) : array();
}

/**
 * Get the count of all a user's wishlists or the current user if no user id is supplied
 *
 * This function only retrieves wishlists with valid plugin statuses (@see nmgr_get_statuses())
 * as these are the statuses used for wishlists on the frontend
 *
 * @param int $user_id The user id (optional)
 * @param string $type The wishlist type taxonomy term slug (gift-registry or wishlist)
 * @return mixed int | NULL
 */
function nmgr_get_user_wishlists_count( $user_id = '', $type = 'gift-registry' ) {
	return count( nmgr_get_user_wishlist_ids( $user_id, $type ) );
}

/**
 * Compose an svg icon using dynamic arguments provided
 *
 * @param string|array $args The name of the svg icon to get or array of icon parameters.
 *
 * If $args is an array, registered array keys needed to compose the svg are:
 * - icon - [required] string The svg icon name. This should correspond to the name of an svg file in the assets/svg directory or the last part of the id of a symbol element in the sprite file - assets/svg/sprite.svg.
 * - size - [optional] integer|string The svg icon width and height. (uses em unit if no unit is specified). Default 1em (16px).
 * - class - [optional] array Classes to add to the svg icon.
 * - sprite - [optional] boolean Whether to use the loaded svg sprite file (Default),
 *            or to load the single svg icon from another path.
 * - role - [optional] string Svg role attribute. Default img.
 * - id - [optional|required] string Svg id attribute. Expected if "sprite" is true.
 *        If not set, the 'icon' parameter is set as the id by default
 * - style - [optional] string Svg Inline style.
 * - title - [optional] string Svg title attribute.
 * - fill - [optional] string Svg fill attribute.
 * - path - [optional] Full path to the single svg icon file.
 *   (Usually used if not using the loaded svg sprite file).
 *
 * @return string Svg HTML element
 */
function nmgr_get_svg( $args ) {
	// Make sure icon name is given
	if ( !$args || (is_array( $args ) && (false === array_key_exists( 'icon', $args ))) ) {
		// If no arguments are set or the icon key is not in the array, require icon key
		return;
	} elseif ( is_string( $args ) ) {
		//if a string argument is given, assume it is the icon name and set it in the array
		$args = array( 'icon' => $args );
	}

	// Set defaults.
	$defaults = array(
		'id' => 'nmgr-icon-' . $args[ 'icon' ],
		'icon' => $args[ 'icon' ],
		'size' => 1,
		'class' => [
			'nmgr-icon',
			$args[ 'icon' ],
		],
		'sprite' => true,
		'role' => 'img',
		'path' => nmgr()->path . 'assets/svg/',
	);

	// Parse args.
	$args_1 = wp_parse_args( $args, $defaults );

	if ( true ) {
		$args = $args_1;
	}

	// Make sure default classes are present
	$args[ 'class' ] = ( array ) $args[ 'class' ];
	foreach ( $defaults[ 'class' ] as $class ) {
		if ( !in_array( $class, $args[ 'class' ] ) ) {
			$args[ 'class' ][] = $class;
		}
	}

	// Make sure the size has units (default em)
	$size = is_numeric( $args[ 'size' ] ) ? "{$args[ 'size' ]}em" : $args[ 'size' ];

	// Get extra svg parameters set by user that are not in the default expected arguments
	// e.g 'style', 'title' and 'fill'
	$extra_params = array_diff_key( $args, $defaults );
	$extra_params_string = '';

	// extract the title attribute if it exists so that we can add it separately to the svg
	$title = '';
	if ( isset( $extra_params[ 'title' ] ) && !empty( $extra_params[ 'title' ] ) ) {
		$title = htmlspecialchars( wp_kses_post( $extra_params[ 'title' ] ) );
		unset( $extra_params[ 'title' ] );
	}

	// Create new indexed array from extra svg parameters
	if ( !empty( $extra_params ) ) {
		$arr = array();
		foreach ( $extra_params as $key => $value ) {
			$arr[] = $key . '="' . $value . '"';
		}
		// Compose string from array of extra svg parameters
		$extra_params_string = implode( ' ', $arr );
	}

	// Compose svg with the given attributes
	$classes = implode( ' ', $args[ 'class' ] );
	$composed_svg = sprintf( '<svg role="%s" width="%s" height="%s" class="%s" %s ',
		$args[ 'role' ],
		$size,
		$size,
		$classes,
		$extra_params_string );

	if ( $args[ 'sprite' ] ) {
		/**
		 * we are using a sprite file
		 */
		$svg = sprintf( '%s><use xlink:href="#%s"></use></svg>',
			$composed_svg,
			$args[ 'id' ]
		);
	} else {
		/**
		 *  we are using a single svg file
		 */
		// Get the svg file
		$svg = nmgr_get_svg_file( $args[ 'icon' ], $args[ 'path' ] );

		// Remove width and heigh attributes from svg if exists as we are adding our own
		$svg = preg_replace( '/(width|height)="\d*"\s/', '', $svg );

		// Merge composed svg with original svg
		$svg = preg_replace( '/^<svg /', $composed_svg, trim( $svg ) );

		// Remove newlines & tabs.
		$svg = preg_replace( "/([\n\t]+)/", ' ', $svg );

		// Remove white space between SVG tags.
		$svg = preg_replace( '/>\s*</', '><', $svg );
	}

	/**
	 * Add title attribute if it exists
	 *
	 * We're adding the title to a container element for the svg to make it easier to
	 * show bootstrap tooltip for the svg as bootstrap tooltips don't show on svgs.
	 *
	 * Class 'nm' is necessary to initialize bootstrap tooltip only on nmerimedia elements
	 */
	if ( $title ) {
		$svg = "<span title='$title' class='nmgr-tip'>" . $svg . '</span>';
	}

	return $svg;
}

/**
 * Get a single svg icon file unmodified from the icon directory
 *
 * @param string $icon_name The name of the icon e.g. user.
 * @param string $path The full path to the icon file.
 *
 * @return string Icon html
 */
function nmgr_get_svg_file( $icon_name, $path = '' ) {
	$iconfile = trailingslashit( $path ) . "{$icon_name}.svg";
	if ( file_exists( $iconfile ) ) {
		ob_start();
		include $iconfile;
		return ob_get_clean();
	}
	return false;
}

/**
 * Get a wishlist by a specified id,
 * or by the current wishlist id in the global object if no id is specified.
 *
 * @param mixed $wishlist_id The wishlist id used to retrieve the wishlist. If zero is supplied, the
 * wishlist id is taken from the current wishlist id in the global context. If the value supplied
 * equates to false, the function assumes no wishlist id is specified and returns false.
 * @param bool $active Whether the wishlist must be active. Default false. (An active wishlist has its
 * post status in the registered post statuses for wishlists. Active wishlists appear on the frontend).
 * @return \NMGR\Sub\Wishlist|NMGR_Wishlist|false
 */
function nmgr_get_wishlist( $wishlist_id, $active = false ) {
	/**
	 * try catch statement is used because the wishlist db class throws an exception
	 * if the wishlist cannot be read from database
	 */
	try {
		$the_wishlist = apply_filters( 'nmgr_get_wishlist', null, $wishlist_id, $active );
		$wishlist = !is_null( $the_wishlist ) ? $the_wishlist : nmgr()->wishlist( $wishlist_id );
		if ( $wishlist && $active ) {
			$wishlist = $wishlist->is_active() ? $wishlist : false;
		}
		return $wishlist;
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Get all wishlists which are in the cart
 * @return array Array of unique wishlist ids
 */
function nmgr_get_wishlists_in_cart( $type = 'gift-registry' ) {
	$wishlists = array();
	if ( is_a( wc()->cart, 'WC_Cart' ) && !WC()->cart->is_empty() ) {
		$cart = WC()->cart->get_cart();

		foreach ( $cart as $cart_item ) {
			$nmgr_data = nmgr_get_cart_item_data( $cart_item, 'wishlist_item' );
			if ( $nmgr_data ) {
				$wishlist_id = $nmgr_data[ 'wishlist_id' ];
				$wishlist = nmgr_get_wishlist( $wishlist_id, true );
				if ( $wishlist && $wishlist->is_type( $type ) ) {
					$wishlists[] = ( int ) $wishlist_id;
				}
			}
		}

		$wishlists = apply_filters( 'nmgr_get_wishlists_in_cart', $wishlists, $cart, $type );
	}
	return array_unique( $wishlists );
}

/**
 * Get the post statuses used by the plugin
 *
 * These are the statuses the plugin uses for active wishlists on the frontend.
 * All other wordpress post statuses are currently ignored.
 *
 * Default post statuses:
 * - publish
 * - private
 *
 * @return array
 */
function nmgr_get_post_statuses() {
	return apply_filters( 'nmgr_post_statuses', array( 'publish', 'private' ) );
}

/**
 * Get a localized date based on date format
 *
 * @param string $date Date to format
 * @param string $format Date format to use. Default is default wordpress date format
 * @return string
 */
function nmgr_format_date( $date, $format = '' ) {
	$datetime = nmgr_get_datetime( $date );
	if ( $date && $datetime ) {
		$date_format = $format ? $format : get_option( 'date_format' );
		$function = function_exists( 'wp_date' ) ? 'wp_date' : 'date_i18n';
		return call_user_func( $function, $date_format, $datetime->getTimestamp() );
	}
	return $date;
}

function _nmgr_default_svg() {
	return sprintf( '<div class="nmgr-no-wishlist-placeholder-svg nmgr-text-center">%s</div>',
		nmgr_get_svg( array(
		'icon' => 'heart',
		'size' => nmgr()->post_thumbnail_size() / 16, // convert px to em
		'fill' => '#f8f8f8',
		) )
	);
}

function nmgr_default_content( $section ) {
	ob_start();
	?>
	<div class="nmgr-call-to-action-no-wishlist nmgr-text-center">
		<?php
		switch ( $section ) {
			case 'create_wishlist':
				$add_items_text = nmgr_get_add_items_text( 'wishlist' );
				echo _nmgr_default_svg();
				echo '<h4>' . $add_items_text . '</h4>';
				echo sprintf( '<a class="button nmgr-add-items-link nmgr-tip" title="%1$s" href="%2$s">%3$s</a>',
					$add_items_text,
					nmgr_get_add_items_url(),
					( nmgr()->is_pro ?
						__( 'Add item(s)', 'nm-gift-registry' ) :
						__( 'Add item(s)', 'nm-gift-registry-lite' )
					)
				);
				break;

			case 'create_gift-registry':
				$text = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Create %s.', 'nm-gift-registry' ) : __( 'Create %s.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( 'c' )
				);
				echo _nmgr_default_svg();
				echo '<h4>' . esc_html( $text ) . '</h4>';
				echo '<a class="button nmgr-call-to-action-btn" href="' . nmgr_get_url( 'gift-registry', 'new' ) . '">' .
				esc_html( nmgr()->is_pro ?
						__( 'Add new', 'nm-gift-registry' ) :
						__( 'Add new', 'nm-gift-registry-lite' ) ) .
				'</a>';
				break;

			case 'items':
				echo '<h4>' . esc_html( nmgr()->is_pro ?
						__( 'No items yet.', 'nm-gift-registry' ) :
						__( 'No items yet.', 'nm-gift-registry-lite' )
				) . '<br>' .
				sprintf(
					/* translators: %s: wishlist type title */
					esc_html( nmgr()->is_pro ? __( 'Save this %s before you can start adding items to it.', 'nm-gift-registry' ) : __( 'Save this %s before you can start adding items to it.', 'nm-gift-registry-lite' ) ),
					esc_html( nmgr_get_type_title( '', false, 'wishlist' ) )
				);
				break;
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * @param string $section
 * @param int|NMGR_Wishlist $id Id or object of the wishlist
 */
function nmgr_get_account_section( $section, $id ) {
	$acc = nmgr()->account( $id )->set_section( $section );
	return $acc->get_section_template();
}

/**
 * Get the button for adding a product to the wishlist
 *
 * @deprecated since version 4.10.
 * @todo Remove in versino 6.0.0
 *
 * This function is still left here as it is used in the add to wishlist customization
 * for a client (nmgr-matw). We should remove this in a much later version.
 *
 * @param int|WC_Product $atts Attributes needed to compose the button
 * Currently accepted $atts attributes if array:
 * - id [int|WC_Product] Product id or instance of WC_Product.
 *   Default none - id is taken from the global product variable if present.
 *
 * @return string Button html
 */
function nmgr_get_add_to_wishlist_button( $atts = false ) {
	_deprecated_function( __FUNCTION__, '4.10', 'nmgr()->add_to_wishlist()->get_button_template()' );
	return nmgr()->add_to_wishlist()->get_button_template( $atts );
}

/**
 * Get the current title used for the wishlist type
 *
 * @param string $formatting How to format the title (default is lowercase).
 * Possible values are
 * - 'c': Capitalize
 * - 'u': Uppercase
 * - 'cf': Capitalize first word
 *
 * @param boolean $pluralize Whether to get the plural form of the title. Default false
 * @param string $type The wishlist type (gift-registry or wishlist). Default is gift-registry
 *
 * @return string
 */
function nmgr_get_type_title( $formatting = '', $pluralize = false, $type = 'gift-registry' ) {
	$type_titles = [
		'gift-registry' => array(
			'singular' => nmgr()->is_pro ?
			__( 'gift registry', 'nm-gift-registry' ) :
			__( 'gift registry', 'nm-gift-registry-lite' ),
			'plural' => nmgr()->is_pro ?
			__( 'gift registries', 'nm-gift-registry' ) :
			__( 'gift registries', 'nm-gift-registry-lite' )
		),
		'wishlist' => array(
			'singular' => nmgr()->is_pro ?
			__( 'wishlist', 'nm-gift-registry' ) :
			__( 'wishlist', 'nm-gift-registry-lite' ),
			'plural' => nmgr()->is_pro ?
			__( 'wishlists', 'nm-gift-registry' ) :
			__( 'wishlists', 'nm-gift-registry-lite' )
		)
	];

	$type_title = $pluralize ? $type_titles[ $type ][ 'plural' ] : $type_titles[ $type ][ 'singular' ];

	switch ( $formatting ) {
		case 'cf': // capitalize first word
			$type_title = ucfirst( $type_title );
			break;

		case 'c': // capitalize
			$type_title = ucwords( $type_title );
			break;

		case 'u': // uppercase
			$type_title = strtoupper( $type_title );
			break;

		default: //lowercase
			$type_title = strtolower( $type_title );
			break;
	}

	return apply_filters( 'nmgr_type_title', $type_title, $formatting, $pluralize, $type_titles, $type );
}

/**
 * Return a standard plugin tooltip notification
 *
 * @param string $title The notification message to be in the tooltip
 * @return string
 */
function nmgr_get_help_tip( $title ) {
	return '<span class="nmgr-tip nmgr-help" style="cursor:help;" title="' . $title . '"> &#9432;</span>';
}

/**
 * Order statuses used by the plugin to determine if a payment is cancelled
 *
 * These are the same statuses used by woocommerce to
 * determine whether to increase stock levels
 *
 * Default:
 * - cancelled
 * - pending
 *
 * @return array
 */
function nmgr_get_payment_cancelled_order_statuses() {
	return apply_filters( 'nmgr_payment_cancelled_order_statuses', array( 'cancelled', 'pending' ) );
}

/**
 * Get the url where items can be added to the wishlist
 *
 * This should typically be the shop page url
 *
 * @return string
 */
function nmgr_get_add_items_url() {
	return apply_filters( 'nmgr_add_items_url', wc_get_page_permalink( 'shop' ) );
}

function nmgr_allowed_post_tags() {
	return array_merge( wp_kses_allowed_html( 'post' ), nmgr_allowed_svg_tags() );
}

/**
 * Svg tags allowed by the plugin
 *
 * @return array
 */
function nmgr_allowed_svg_tags() {
	return array(
		'svg' => array(
			'id' => true,
			'role' => true,
			'width' => true,
			'height' => true,
			'class' => true,
			'style' => true,
			'fill' => true,
			'xmlns' => true,
			'viewbox' => true,
			'aria-hidden' => true,
			'focusable' => true,
		),
		'use' => array(
			'xlink:href' => true
		),
		'title' => array(
			'data-title' => true
		),
		'path' => array(
			'fill' => true,
			'fill-rule' => true,
			'd' => true,
			'transform' => true,
		),
		'polygon' => array(
			'fill' => true,
			'fill-rule' => true,
			'points' => true,
			'transform' => true,
			'focusable' => true,
		),
	);
}

/**
 * Check if a wishlist belongs to a particular user
 *
 * @param int|NMGR_Wishlist $wishlist_id The wishlist id or object.
 * @return boolean True if user has the wishlist. False if not.
 */
function nmgr_user_has_wishlist( $wishlist_id ) {
	$id = 0;
	if ( is_numeric( $wishlist_id ) && 0 < $wishlist_id ) {
		$id = $wishlist_id;
	} elseif ( is_a( $wishlist_id, 'NMGR_Wishlist' ) ) {
		$id = $wishlist_id->get_id();
	}

	if ( $id ) {
		$userid = nmgr_get_current_user_id();
		if ( $userid ) {
			return ( int ) $userid === ( int ) get_post_meta( $id, '_nmgr_user_id', true );
		}
	}
	return false;
}

/**
 * Check whether the cart has items belonging to a particular wishlist
 *
 * If no wishlist id is supplied, the function checks for the first wishlist that may have an item in the cart.
 *
 * @param int $wishlist_id The wishlist id to check for.
 * @return int The wishlist id if the cart has the wishlist or 0 if the cart doesn't.
 */
function nmgr_get_wishlist_in_cart( $wishlist_id = '' ) {
	if ( is_a( wc()->cart, 'WC_Cart' ) && !WC()->cart->is_empty() ) {
		$id = 0;
		$cart = WC()->cart->get_cart();

		foreach ( $cart as $cart_item ) {
			$nmgr_data = nmgr_get_cart_item_data( $cart_item, 'wishlist_item' );
			if ( $nmgr_data ) {
				if ( $wishlist_id &&
					(absint( $wishlist_id ) === absint( $nmgr_data[ 'wishlist_id' ] )) &&
					nmgr_get_wishlist( $wishlist_id, true ) ) {
					$id = $wishlist_id;
					break;
				} elseif ( !$wishlist_id && nmgr_get_wishlist( $nmgr_data[ 'wishlist_id' ], true ) ) {
					$id = $nmgr_data[ 'wishlist_id' ];
					break;
				}
			}
		}
		return ( int ) apply_filters( 'nmgr_get_wishlist_in_cart', $id, $cart );
	}
	return 0;
}

/**
 * Check if the current user is a logged in user or a guest.
 *
 * Guests exist when non-logged-in users have been permitted to create and manage wishlists.
 *
 * @return boolean
 */
function is_nmgr_user( $type = 'gift-registry' ) {
	return apply_filters( 'is_nmgr_user', (is_user_logged_in() || is_nmgr_guest( $type ) ) );
}

/**
 * Check if the current user is a guest
 *
 * Guests exist when non-logged-in users have been permitted to create and manage wishlists.
 *
 * @return boolean
 */
function is_nmgr_guest( $type = 'gift-registry' ) {
	$val = ( bool ) !is_user_logged_in() && nmgr_get_type_option( $type, 'allow_guest_wishlists' );
	return apply_filters( 'is_nmgr_guest', $val, $type );
}

/**
 * Get the user id for the current logged in user or guest
 * (Note that this function returns the string value of the user id)
 *
 * @return string
 */
function nmgr_get_current_user_id() {
	if ( get_current_user_id() ) {
		/**
		 * Always return logged in user id as a string so that it can be compatible with the guest
		 * user id cookie value type and be tested with ===
		 */
		return ( string ) get_current_user_id();
	}

	if ( is_nmgr_guest( 'gift-registry' ) || is_nmgr_guest( 'wishlist' ) ) {
		return nmgr_get_user_id_cookie();
	}
}

/**
 * Get the user id stored in a cookie.
 * (This is typically used for guests)
 *
 * @return string
 */
function nmgr_get_user_id_cookie() {
	return isset( $_COOKIE[ 'nmgr_user_id' ] ) ? sanitize_key( wp_unslash( $_COOKIE[ 'nmgr_user_id' ] ) ) : 0;
}

/**
 * Check if the current user has permissions to manage a wishlist
 *
 * By default the administrator and shop_manager roles have permision to manage all wishlists.
 *
 * @param int|NMGR_Wishlist $wishlist_id Wishlist id or object
 * @return boolean
 */
function nmgr_user_can_manage_wishlist( $wishlist_id = 0 ) {
	return $wishlist_id ?
		(current_user_can( 'manage_nm_gift_registry_settings' ) || nmgr_user_has_wishlist( $wishlist_id )) :
		false;
}

/**
 * Get the DateTime object representation of a date
 *
 * @param string $date
 * @return DateTime
 */
function nmgr_get_datetime( $date ) {
	// Y-m-d is 2021-10-04 This is the default date format used for the jquery ui datepicker.
	$date_format = apply_filters( 'nmgr_validate_date_format', 'Y-m-d' );

	$datetime = DateTime::createFromFormat( $date_format, $date, wp_timezone() );

	if ( !$datetime ) {
		try {
			$datetime = new DateTime( $date, wp_timezone() );
		} catch ( Exception $ex ) {
			$datetime = false;
		}
	}
	return $datetime;
}

/**
 * Get a wishlist item by a specified id,
 *
 * @param int $item_id The wishlist item id used to retrieve the wishlist item
 * @return mixed NMGR_Wishlist_Item | false
 */
function nmgr_get_wishlist_item( $item_id ) {
	if ( !$item_id ) {
		return false;
	}

	$item = apply_filters( 'nmgr_get_wishlist_item', null, $item_id );
	if ( $item ) {
		return $item;
	}

	/**
	 * try catch statement is used because the wishlist item db class throws an exception
	 * if the wishlist item cannot be read from database
	 */
	try {
		$item = nmgr()->wishlist_item( $item_id );
		return $item;
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Get the default wishlist id for a user
 *
 * This is the wishlist id that is set for the user when he's allowed to have only one wishlist.
 * It should typically be the users latest wishlist which is in an active state.
 *
 * This function is different from 'nmgr_get_current_wishlist_id' because that function gets
 * the current wishlist id being viewed or operated on depending on the context. This function
 * does not depend on any context.
 *
 * @param int $user_id The user id. Defaults to the current wishlist user id if no user id is supplied.
 * @return int
 */
function nmgr_get_user_default_wishlist_id( $user_id = 0, $type = 'gift-registry' ) {
	$wishlist_ids = nmgr_get_user_wishlist_ids( $user_id, $type );
	return reset( $wishlist_ids );
}

/**
 * Check if we are on an nm_gift_registry archive page
 * e.g. search results, categories, tags and post type archives.
 *
 * This function doesn't return true for function nmgr_archive_content when used in
 * pages via shortcodes because it is not strictly a wordpress archive page.
 *
 * @return boolean
 */
function is_nmgr_archive() {
	return apply_filters( 'is_nmgr_archive',
		/**
		 * 'is_search' checks to see if we are using wordpress' default search key 's'
		 * 'is_nmgr_search' check to see that we are searching for wishlists
		 * Using these two functions is necessary to make sure we are searching for wishlists
		 * using wordpress' default search and not the custom wishlist search key 'nmgr_s' used
		 * in the search shortcode which makes it not to be a search archive
		 */
		(is_search() && is_nmgr_search()) ||
		is_nmgr_wishlist_page( 'archive' )
	);
}

/**
 * Check if we are on the custom page used for displaying wishlists
 * @return boolean
 */
function is_nmgr_wishlist_page( $query = '' ) {
	global $wp_query;

	if ( !isset( $wp_query ) ) {
		return;
	}

	$gr_page_id = ( int ) nmgr_get_option( 'page_id' );
	$wh_page_id = ( int ) nmgr_get_option( 'wishlist_page_id' );
	$queried_id = ( int ) get_queried_object_id();
	$is_custom_page = $queried_id && ($queried_id === $gr_page_id || $queried_id === $wh_page_id );
	$doing_ajax = wp_doing_ajax();
	$base_actions = nmgr_get_base_actions();

	$is_page = $is_custom_page || ($doing_ajax && !empty( $GLOBALS[ 'nmgr' ]->is_wishlist_page ));

	if ( $is_page && $query ) {
		$has_wishlist_shortcode = $has_archive_shortcode = null;

		if ( $is_custom_page ) {
			$page = get_post( $queried_id );
			$has_wishlist_shortcode = is_a( $page, 'WP_Post' ) && has_shortcode( $page->post_content, 'nmgr_wishlist' );
			$has_archive_shortcode = is_a( $page, 'WP_Post' ) && has_shortcode( $page->post_content, 'nmgr_archive' );
		}

		$type = nmgr_get_current_type();
		$nmgr_w = get_query_var( 'nmgr_w' );
		$nmgr_action = get_query_var( 'nmgr_action' );
		$ajax_query = $doing_ajax && !empty( $GLOBALS[ 'nmgr' ]->{'is_wishlist_page_' . $query} );
		$allow_multi = nmgr()->is_pro && nmgr_get_type_option( $type, 'allow_multiple_wishlists' );
		$base = !$nmgr_w && !$nmgr_action && $has_wishlist_shortcode;

		switch ( $query ) {
			case 'base':
				$is_page = $base;
				break;
			case 'new':
				$is_page = ($query === $nmgr_w) || $ajax_query;
				break;
			case 'home':
				$is_page = ($query === $nmgr_w) || ($base && $allow_multi ) || $ajax_query;
				break;
			case 'archive':
				$is_page = (!$nmgr_w && !$nmgr_action && $has_archive_shortcode) || $ajax_query;
				break;
			case 'single':
				$single = ($nmgr_w && !in_array( $nmgr_w, $base_actions ) && !$nmgr_action) ||
					($base && !$allow_multi ) ||
					$ajax_query;
				$is_page = apply_filters( 'is_nmgr_wishlist', $single );
				break;
			case 'account_section':
				$is_page = (($has_archive_shortcode || $has_wishlist_shortcode) &&
					$nmgr_w && $nmgr_action && !in_array( $nmgr_w, $base_actions )) ||
					$ajax_query;
				break;
			default:
				$is_page = false;
				break;
		}
	}

	return apply_filters( 'is_nmgr_wishlist_page', $is_page, [ 'query' => $query ] );
}

/**
 * Returns a html progress bar
 *
 * @param int|float $needed Quantity needed to complete the progress
 * @param int|float $received Quantity of progress made already
 * @param string $title_attribute Title attribute to display over the progress bar. Default none.
 * @param boolean $show_progress Whether to show the progress bar graphic. Default true.
 * @param boolean $show_percent Whether to show the percentage complete. Default true.
 */
function nmgr_progressbar( $needed, $received, $title_attribute = '', $show_progress = true, $show_percent = true ) {
	ob_start();
	$percent = ( float ) 0 === ( float ) $received ? '0%' :
		min( ceil( (( float ) $received / ( float ) $needed) * 100 ), ( float ) 100 ) . '%';
	?>

	<div class="nmgr-progressbar">
		<?php if ( $show_progress ) : ?>
			<div class="progress-wrapper nmgr-tip" title="<?php echo strip_tags( $title_attribute ); ?>">
				<div class="progress" style="width: <?php echo $percent; ?>"></div>
			</div>
		<?php endif; ?>
		<?php if ( $show_percent ) : ?>
			<span class="percent"><?php echo $percent; ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get the checkbox switch used by the plugin
 * @param array $args Arguments supplied to create the checkbox switch:
 * - input_type = {string} The input type, 'checkbox' or 'radio'. Default 'checkbox'
 * - input_id - {string, required} The input id
 * - input_name - {string} The input name
 * - input_value - {mixed} The input value
 * - input_class - {array} The input class
 * - input_attributes = {array} Attributes to go into the input element
 * - label_class - {array} The label class
 * - label_attributes - {array} Attributes to go into the label element
 * - label_text - {string} The label text
 * - label_before - {boolean} Whether the label text should be before the switch. Default false.
 * - checked - {boolean} Whether the checkbox should be checked or not.
 * - show_hidden_input - {mixed} Whether to show a hidden input for the checkbox. Default false.
 * 												If true, show hidden input value is 0 else, value of 'show_hidden_input' is used.
 * @return string
 */
function nmgr_get_checkbox_switch( $args ) {
	$defaults = array(
		'input_type' => 'checkbox',
		'input_id' => '',
		'input_name' => '',
		'input_value' => 1,
		'input_class' => array(),
		'input_attributes' => array(),
		'label_class' => array(),
		'label_attributes' => array(),
		'label_text' => '',
		'label_before' => false,
		'checked' => '',
		'show_hidden_input' => false,
	);

	$params = wp_parse_args( $args, $defaults );

	if ( isset( $params[ 'input_attributes' ][ 'disabled' ] ) && $params[ 'input_attributes' ][ 'disabled' ] ) {
		$params[ 'label_class' ][] = 'disabled';
	}

	if ( isset( $params[ 'input_attributes' ][ 'readonly' ] ) && $params[ 'input_attributes' ][ 'readonly' ] ) {
		$params[ 'label_class' ][] = 'readonly';
	}

	if ( $params[ 'label_before' ] ) {
		$params[ 'label_class' ][] = 'label-before';
	}

	$label_class = implode( ' ', array_map( 'sanitize_html_class', ( array ) $params[ 'label_class' ] ) );
	$input_class = implode( ' ', array_map( 'sanitize_html_class', ( array ) $params[ 'input_class' ] ) );
	$input_attributes = nmgr_format_attributes( $params[ 'input_attributes' ] );
	$label_attributes = nmgr_format_attributes( $params[ 'label_attributes' ] );
	$checked = $params[ 'checked' ] ? " checked='checked'" : '';
	$hidden = '';

	if ( $params[ 'show_hidden_input' ] ) {
		$hidden_input_val = (true === $params[ 'show_hidden_input' ]) ? 0 : $params[ 'show_hidden_input' ];
		$hidden = '<input type="hidden" value="' . $hidden_input_val . '" name="' . $params[ 'input_name' ] . '">';
	}

	return '<label class="nmgr-checkbox-switch ' . $label_class . '"' . $label_attributes . '>' .
		$hidden .
		'<span>' . $params[ 'label_text' ] . '</span>' .
		'<input type="' . $params[ 'input_type' ] . '" id="' . $params[ 'input_id' ] . '"
                value="' . $params[ 'input_value' ] . '"
								class="' . $input_class . '"
                name="' . $params[ 'input_name' ] . '"' .
		$checked . $input_attributes . '/>
                <label for="' . $params[ 'input_id' ] . '"></label>
            </label>';
}

function nmgr_format_attributes( $attributes ) {
	$sanitized_attributes = array();

	if ( !empty( $attributes ) ) {
		foreach ( ( array ) $attributes as $key => $value ) {
			$val = is_array( $value ) ? implode( ' ', array_map( 'esc_attr', $value ) ) : $value;
			$sanitized_attributes[] = esc_attr( $key ) . '="' . $val . '"';
		}
	}
	return implode( ' ', $sanitized_attributes );
}

/**
 * This function is similar to nmgr_format_attributes only that it does not
 * sanitize the attributes. It is an emergency function that has been created temporarily.
 *
 * @todo Merge with nmgr_format_attributes()
 */
function nmgr_utils_format_attributes( $attributes ) {
	$attr = array();

	if ( !empty( $attributes ) ) {
		foreach ( ( array ) $attributes as $key => $value ) {
			$val = is_array( $value ) ? implode( ' ', $value ) : $value;
			$attr[] = $key . '="' . $val . '"';
		}
	}
	return implode( ' ', $attr );
}

/**
 * Template for showing log of all purchases of a wishlist item
 *
 * @param int|NMGR_Wishlist_Item|\NMGR\Sub\Wishlist_Item|array $atts Attributes needed to compose the template.
 * Currently accepted $atts attributes if array:
 * - id [int|NMGR_Wishlist_Item] Wishlist item id or instance of NMGR_Wishlist_Item.
 *
 * @param boolean $echo Whether to echo the template. Default false.
 *
 * @return string Template html
 */
function nmgr_get_item_purchase_log_template( $atts = '', $echo = false ) {
	if ( !is_nmgr_enabled() || !is_nmgr_user() ) {
		return;
	}

	$args = shortcode_atts(
		array(
			'id' => is_array( $atts ) ? $atts[ 'id' ] : $atts,
		),
		$atts,
		'nmgr_item_purchase_log'
	);

	$item = nmgr_get_wishlist_item( $args[ 'id' ] );

	$props = $item->get_purchase_log_props();
	$fields = new Fields();
	$fields->set_id( 'item_purchase_log' );
	$fields->set_data( $props );
	$function = function ( $table ) {
		$key = $table->get_cell_key();
		$log = $table->get_row_object();
		return ($log[ $key ] ?? '');
	};
	$fields->set_values( $function );

	$table = new Table();
	$table->set_id( $fields->get_id() );
	$table->set_data( $fields->get_data() );
	$table->set_rows_object( $item->get_purchase_log() );
	$template = $table->get_table();

	if ( $echo ) {
		echo $template;
	} else {
		return $template;
	}
}

/**
 * Get the wishlist data in the cart item.
 *
 * @param array|string $cart_item The cart item array value or key
 * @param string $data_type The type of wishlist data for the cart item.
 * Default is 'wishlist_item'. Others are 'crowdfund', 'free_contribution'
 * @return false|array Array of wishlist data if true. False if the cart item doesn't have wishlist data.
 */
function nmgr_get_cart_item_data( $cart_item, $data_type = null ) {
	if ( is_a( wc()->cart, 'WC_Cart' ) ) {
		$cart_item = is_array( $cart_item ) ? $cart_item : wc()->cart->get_cart_item( $cart_item );
		if ( $data_type ) {
			$val = $data_type === ($cart_item[ 'nm_gift_registry' ][ 'type' ] ?? null) ?
				$cart_item[ 'nm_gift_registry' ] : false;
		} else {
			$val = $cart_item[ 'nm_gift_registry' ] ?? false;
		}
		return apply_filters( 'nmgr_get_cart_item_data', $val, $cart_item, $data_type );
	}
}

function nmgr_get_dropdown() {
	return new NMGR\Dialog\Dropdown();
}

function nmgr_get_modal() {
	return new \NMGR\Dialog\Modal();
}

function nmgr_get_toast() {
	return new NMGR\Dialog\Toast();
}

function nmgr_get_toast_notice( $content, $notice_type = 'success' ) {
	$comp = nmgr_get_toast();
	$comp->set_notice_type( $notice_type );
	$comp->set_content( $content );
	return $comp->get();
}

function nmgr_get_wc_toast_notices() {
	$arr = [];
	foreach ( wc_get_notices() as $type => $notice ) {
		foreach ( $notice as $not ) {
			$arr[] = nmgr_get_toast_notice( $not[ 'notice' ], $type );
		}
	}
	wc_clear_notices();
	return $arr;
}

function nmgr_get_error_toast_notice() {
	return nmgr_get_toast_notice( nmgr_get_error_text(), 'error' );
}

function nmgr_get_success_toast_notice() {
	return nmgr_get_toast_notice( nmgr_get_success_text(), 'success' );
}

function nmgr_show_copy_shipping_address_btn( $address ) {
	?>
	<div class="nmgr-copy-shipping-address-wrapper" style="margin-bottom:15px;">
		<a class="nmgr-copy-shipping-address" href="#"
			 data-nmgr-address="<?php echo esc_attr( htmlspecialchars( json_encode( $address ) ) ); ?>">
				 <?php
				 echo esc_html( nmgr()->is_pro ?
						 __( 'Copy account shipping address', 'nm-gift-registry' ) :
						 __( 'Copy account shipping address', 'nm-gift-registry-lite' )
				 );
				 ?>
		</a>
	</div>
	<?php
}

function nmgr_get_error_text() {
	return nmgr()->is_pro ?
		__( 'Error', 'nm-gift-registry' ) :
		__( 'Error', 'nm-gift-registry-lite' );
}

function nmgr_get_success_text() {
	return nmgr()->is_pro ?
		__( 'Success', 'nm-gift-registry' ) :
		__( 'Success', 'nm-gift-registry-lite' );
}

function nmgr_get_custom_order_notice() {
	return sprintf(
		/* translators: %s wishlist type title */
		nmgr()->is_pro ? __( 'This order was created manually from the purchase of a %s item.', 'nm-gift-registry' ) : __( 'This order was created manually from the purchase of a %s item.', 'nm-gift-registry-lite' ),
		nmgr_get_type_title()
	);
}

function nmgr_get_current_type() {
	global $wp_query;

	$type = 'gift-registry';

	if ( !is_nmgr_admin() && !wp_doing_ajax() & !empty( $wp_query ) ) {
		$current_id = get_queried_object_id();
		if ( $current_id && ($current_id === ( int ) nmgr_get_option( 'page_id' )) ) {
			$type = 'gift-registry';
		} elseif ( $current_id && ($current_id === ( int ) nmgr_get_option( 'wishlist_page_id' )) ) {
			$type = 'wishlist';
		}
	}
	return $type;
}

function nmgr_get_base_actions() {
	return [
		'new',
		'home',
	];
}

function nmgr_get_url( $type = 'gift-registry', $action = '' ) {
	$page_id = nmgr_get_type_option( $type, 'page_id' );
	$pre_url = $page_id ? get_permalink( $page_id ) : '';
	$url = $pre_url ? $pre_url : get_post_type_archive_link( 'nm_gift_registry' );

	switch ( $action ) {
		case 'home':
			$page = get_post( $page_id );
			if ( is_a( $page, 'WP_Post' ) && has_shortcode( $page->post_content, 'nmgr_wishlist' ) ) {
				$url = $url;
			} else {
				$url = trailingslashit( $url ) . $action;
			}
			break;

		default:
			$url = trailingslashit( $url ) . $action;
			break;
	}

	return $url;
}

function nmgr_get_term_id_by_slug( $slug ) {
	$term = get_term_by( 'slug', $slug, 'nm_gift_registry_type' );
	return $term ? $term->term_id : 0;
}

function nmgr_get_add_items_text( $type = 'gift-registry' ) {
	return sprintf(
		/* translators: %s: wishlist type title */
		nmgr()->is_pro ? __( 'Go shopping for items to add to your %s.', 'nm-gift-registry' ) : __( 'Go shopping for items to add to your %s.', 'nm-gift-registry-lite' ),
		nmgr_get_type_title( '', false, $type )
	);
}

function nmgr_get_click_here_link( $url ) {
	$click_here_text = nmgr()->is_pro ?
		__( 'Click here', 'nm-gift-registry' ) :
		__( 'Click here', 'nm-gift-registry-lite' );

	return '<a href="' . $url . '">' . $click_here_text . '</a>';
}

function nmgr_update_pagename( $page_id, $type ) {
	if ( $page_id ) {
		$option = 'wishlist' === $type ? 'nmgr_wishlist_pagename' : 'nmgr_pagename';
		return update_option( $option, get_page_uri( $page_id ) );
	}
}

function nmgr_get_variations_for_display( $variations, $variation_product_name ) {
	$display = array();

	foreach ( $variations as $name => $value ) {
		$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );

		if ( taxonomy_exists( $taxonomy ) ) {
			// If this is a term slug, get the term's nice name.
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( !is_wp_error( $term ) && $term && $term->name ) {
				$value = $term->name;
			}
			$label = wc_attribute_label( $taxonomy );
		} else {
			// If this is a custom option slug, get the options name.
			$label = wc_attribute_label( str_replace( 'attribute_', '', $name ) );
		}

		// Check the nicename against the title.
		if ( '' === $value || wc_is_attribute_in_product_name( $value, $variation_product_name ) ) {
			continue;
		}

		$display[] = array(
			'key' => $label,
			'value' => $value,
		);
	}

	return $display;
}

function nmgr_orders_table() {
	global $wpdb;
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_table_ds = wc_get_container()->get( OrdersTableDataStore::class );
		return $order_table_ds::get_orders_table_name();
	} else {
		return $wpdb->posts;
	}
}

function nmgr_is_paid_statuses() {
	return array_merge( wc_get_is_paid_statuses(), [ 'refunded' ] );
}

function nmgr_round( $amt ) {
	return round( ( float ) $amt, wc_get_price_decimals() );
}

/**
 * Merge supplied values with default values
 * Should be used with only associative arrays.
 * This function doesn't use wp_parse_args because it doesn't merge recursively.
 *
 * @param array $defaults Default values
 * @param array $args Supplied values
 * @return array
 */
function nmgr_merge_args( $defaults, $args ) {
	$merged = $defaults;
	foreach ( ( array ) $args as $key => $val ) {
		if ( isset( $defaults[ $key ] ) ) {
			$merged[ $key ] = is_array( $defaults[ $key ] ) ? array_merge_recursive( $defaults[ $key ], ( array ) $val ) : $val;
		} else {
			$merged[ $key ] = $val;
		}
	}
	return $merged;
}
