<?php

namespace NMGR\Lib;

use NMGR\Lib\Single;

defined( 'ABSPATH' ) || exit;

class Archive {

	public static function get_args( $atts = [] ) {
		$args = wp_parse_args(
			$atts,
			array(
				'title' => '',
				'show_header' => true,
				'show_title' => true,
				'show_results_count' => true,
				'show_results_if_no_search_query' => true,
				'type' => 'gift-registry',
			)
		);

		$args[ 'show_header' ] = filter_var( $args[ 'show_header' ], FILTER_VALIDATE_BOOLEAN );
		$args[ 'show_title' ] = filter_var( $args[ 'show_title' ], FILTER_VALIDATE_BOOLEAN );
		$args[ 'show_results_count' ] = filter_var( $args[ 'show_results_count' ], FILTER_VALIDATE_BOOLEAN );
		$args[ 'show_results_if_no_search_query' ] = filter_var( $args[ 'show_results_if_no_search_query' ], FILTER_VALIDATE_BOOLEAN );

		return apply_filters( 'nmgr_archive_template_args', $args );
	}

	/**
	 * Get the template for outputting wishlist archive results
	 *
	 * Works with search results too.
	 *
	 * This function or the shortcode attached to it should be used after wp_loaded hook
	 * as that is when the wp_query global exists.
	 *
	 * @param array $atts Attributes needed to compose the template.
	 * @param bool $echo Whether to echo or return the template.
	 */
	public static function get_template( $atts = array() ) {
		global $wp_query;

		if ( is_nmgr_admin() || !is_a( $wp_query, 'WP_Query' ) ) {
			return;
		}

		if ( !is_nmgr_archive() && !is_nmgr_search() ) {
			return Single::get_template( $atts );
		}

		$template_args = static::get_args( $atts );

		$query_args = (is_nmgr_archive() && !is_page()) ? $wp_query->query_vars : array();

		if ( isset( $_GET[ 'nmgr_s' ] ) || (!isset( $_GET[ 'nmgr_s' ] ) && $template_args[ 'show_results_if_no_search_query' ]) ) {
			$query_args = array();
			$query_args[ 's' ] = static::get_search_query();
		}

		$query_args[ 'paged' ] = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : '';
		$query_args[ 'post_type' ] = 'nm_gift_registry';

		if ( !empty( $template_args[ 'type' ] ) ) {
			$query_args[ 'tax_query' ] = array( array(
					'taxonomy' => 'nm_gift_registry_type',
					'field' => 'slug',
					'terms' => $template_args[ 'type' ],
					'operator' => 'IN',
				) );
		}
		$query_args[ 'nmgr_query' ] = $template_args; // currently for internal use only

		ob_start();

		$the_query = new \WP_Query( $query_args );

		static::loop( $the_query, $template_args );

		return ob_get_clean();
	}

	/**
	 * Display posts found using the standard wishlist template.
	 *
	 * This function is meant to provide a consistent template for displaying wishlist posts
	 * on any nm_gift_registry archive page such as search, categories, tags, post_type_archive e.t.c.
	 * It also works with a custom $wp_query object.
	 *
	 * @param WP_Query $query Custom query object if provided. Uses global $wp_query by default.
	 * @param array $action_args The arguments supplied to the action hooks used in the function.
	 */
	public static function loop( $query = null, $action_args = array() ) {
		global $wp_query;

		$custom_query = is_a( $query, 'WP_Query' ) && !$query->is_main_query();
		$the_query = $query ? $query : $wp_query;
		$is_nmgr_archive = is_nmgr_archive();
		$type = $action_args[ 'type' ] ?? null;

		if ( 'nm_gift_registry' !== $the_query->get( 'post_type' ) ) {
			return;
		}

		if ( $type && !nmgr_get_type_option( $type, 'enable' ) ) {
			return;
		}

		add_filter( 'is_nmgr_archive', '__return_true' );

		do_action( 'nmgr_archive_header', $the_query, $action_args );

		if ( $the_query->have_posts() ) :

			do_action( 'nmgr_before_archive_loop', $the_query, $action_args );

			while ( $the_query->have_posts() ) :

				$the_query->the_post();

				static::content();

			endwhile;

			do_action( 'nmgr_after_archive_loop', $the_query, $action_args );

			/**
			 * Set the custom query to the global $wp_query object so that we can use
			 * wordpress default pagination
			 */
			if ( $custom_query ) {
				$GLOBALS[ 'wp_query' ] = $query;
			}

			nmgr_paging_nav();

			/**
			 * reset the global $wp_query object after pagination.
			 */
			if ( $custom_query ) :
				wp_reset_query();
			endif;

		else:
			do_action( 'nmgr_archive_no_results_found', $the_query, $action_args );
		endif;

		add_filter( 'is_nmgr_archive', ($is_nmgr_archive ? '__return_true' : '__return_false' ) );
	}

	public static function content() {
		$wishlist = nmgr_get_wishlist( get_the_ID() );
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'nmgr-archive-content nmgr-background-color' ); ?>>
			<?php if ( nmgr()->is_pro && ($wishlist->get_thumbnail() || $wishlist->get_background_image_id()) ) : ?>
				<div class="entry-thumbnail nmgr-col">
					<a href="<?php echo esc_url( $wishlist->get_permalink() ); ?>" rel="bookmark">
						<?php
						if ( $wishlist->get_thumbnail() ) {
							echo $wishlist->get_thumbnail();
						} else {
							echo $wishlist->get_background_thumbnail();
						}
						?>
					</a>
				</div>
			<?php endif; ?>

			<div class="entry-content nmgr-col">
				<h2 class="entry-title nmgr-title">
					<a href="<?php echo esc_url( $wishlist->get_permalink() ); ?>" rel="bookmark">
						<?php echo esc_html( $wishlist->get_title() ); ?>
					</a>
				</h2>
				<?php
				if ( $wishlist->get_event_date() || $wishlist->get_full_name() ) {
					echo "<p class='nmgr-details'>";
					$wishlist->get_full_name() ? printf( '<span class="nmgr-full-name">%s</span>', esc_html( $wishlist->get_full_name() ) ) : '';
					$wishlist->get_event_date() ? printf(
								'<span class="nmgr-event-date">%1$s <span class="nmgr-date">%2$s</span></span>',
								esc_html( nmgr()->is_pro ?
										__( 'Event date:', 'nm-gift-registry' ) :
										__( 'Event date:', 'nm-gift-registry-lite' ) ),
								esc_html( nmgr_format_date( $wishlist->get_event_date() ) )
							) : '';
					echo "</p>";
				}
				?>
			</div>

			<div class="entry-action nmgr-col">
				<a href="<?php echo esc_url( $wishlist->get_permalink() ); ?>" class="button" rel="bookmark">
					<?php
					echo esc_html( nmgr()->is_pro ?
							__( 'View', 'nm-gift-registry' ) :
							__( 'View', 'nm-gift-registry-lite' )
					);
					?>
				</a>
			</div>

		</article>
		<?php
	}

	public static function get_search_template_args( $atts = [] ) {
		$args_unfiltered = nmgr_merge_args(
			array(
				'show_form' => true,
				'show_results' => true,
				'form_action' => nmgr_get_url(),
				'show_results_if_no_search_query' => true,
				'input_required' => false,
				'input_placeholder' => sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Search %s&hellip;', 'nm-gift-registry' ) : __( 'Search %s&hellip;', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', true )
				),
				'submit_button_text' => apply_filters( 'nmgr_search_submit_button_text',
					nmgr()->is_pro ?
					_x( 'Search', 'submit button', 'nm-gift-registry' ) :
					_x( 'Search', 'submit button', 'nm-gift-registry-lite' )
				),
				'hidden_fields' => array() // for now hidden fields can only be added via code and not shortcode
			),
			( array ) $atts
		);

		$args = wp_parse_args( filter_var_array( $args_unfiltered, array(
			'show_form' => FILTER_VALIDATE_BOOLEAN,
			'show_results' => FILTER_VALIDATE_BOOLEAN,
			'form_action' => FILTER_SANITIZE_URL,
			'show_results_if_no_search_query' => FILTER_VALIDATE_BOOLEAN,
			'input_required' => FILTER_VALIDATE_BOOLEAN,
			'input_placeholder' => FILTER_DEFAULT,
			'submit_button_text' => FILTER_DEFAULT,
			) ), $args_unfiltered );

		return apply_filters( 'nmgr_search_template_args', $args );
	}

	/**
	 * Get the wishlist search template
	 * This includes the search form and search results, depending on the arguments provided
	 *
	 * @param type $atts Attributes needed to compose the template
	 * @param boolean $echo Whether to echo the template. Default false.
	 * @return string Template html
	 */
	public static function get_search_template( $atts = '' ) {
		$args = static::get_search_template_args( $atts );

		$template = '';

		if ( !$args[ 'show_results_if_no_search_query' ] && !static::get_search_query() ) {
			$args[ 'show_results' ] = false;
		}

		if ( $args[ 'show_form' ] ) {
			$vars = array(
				'form_action' => $args[ 'form_action' ],
				'input_name' => 'nmgr_s',
				'input_value' => is_nmgr_search() ? get_query_var( 'nmgr_s' ) : '',
				'input_placeholder' => $args[ 'input_placeholder' ],
				'input_required' => $args[ 'input_required' ],
				'hidden_fields' => array_filter( ( array ) $args[ 'hidden_fields' ] ),
				'submit_button_text' => $args[ 'submit_button_text' ],
			);

			$temp = static::search_form( $vars );

			$template .= apply_filters( 'nmgr_search_form', $temp, $vars );
		}

		if ( $args[ 'show_results' ] ) {
			$template .= static::get_template( $args );
		}

		return $template;
	}

	public static function get_search_results_template( $atts = [] ) {
		$defaults = [ 'show_form' => false ];
		return static::get_search_template( wp_parse_args( $atts, $defaults ) );
	}

	/**
	 * Get the search form for finding a wishlist on the frontend
	 */
	public static function get_search_form( $args = [] ) {
		$defaults = array(
			'show_results' => false,
			'form_action' => nmgr_get_url(),
		);

		return static::get_search_template( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Get the search term used for searching wishlists
	 *
	 * The plugin gives priority to wordpress' search term 's' in the wp_query global object.
	 * If 's' is not available, the plugin checks for 'nmgr_s' in the wp_query object.
	 * If 'nmgr_s' is not available, the plugin checks for 'nmgr_s' in the $_GET array.
	 *
	 * This function provides a convenient place where all these checks can be made.
	 * The search term can be filtered with 'nmgr_get_search_query'
	 *
	 * @return string Search query term
	 */
	public static function get_search_query() {
		$s = '';
		if ( get_search_query() ) {
			$s = get_search_query();
		} elseif ( get_query_var( 'nmgr_s' ) ) {
			$s = get_query_var( 'nmgr_s' );
		} elseif ( isset( $_GET[ 'nmgr_s' ] ) ) {
			$s = $_GET[ 'nmgr_s' ];
		}
		return sanitize_text_field( wp_unslash( apply_filters( 'nmgr_get_search_query', $s ) ) );
	}

	public static function search_form( $args ) {
		ob_start();
		$form_action = $args[ 'form_action' ];
		$input_name = $args[ 'input_name' ];
		$input_placeholder = $args[ 'input_placeholder' ];
		$input_required = $args[ 'input_required' ];
		$input_value = $args[ 'input_value' ];
		$submit_button_text = $args[ 'submit_button_text' ];
		$hidden_fields = $args[ 'hidden_fields' ];
		?>
		<form role="search" method="get" class="nmgr-search-form" action="<?php echo esc_url( $form_action ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_name ); ?>">
				<?php
				echo esc_html( nmgr()->is_pro ?
						__( 'Search for:', 'nm-gift-registry' ) :
						__( 'Search for:', 'nm-gift-registry-lite' )
				);
				?>
			</label>
			<input type="search"
						 class="search-field"
						 placeholder="<?php echo esc_attr( $input_placeholder ); ?>"
						 value="<?php echo esc_attr( stripslashes( $input_value ) ); ?>"
						 <?php echo (!empty( $input_required )) ? 'required' : ''; ?>
						 name="<?php echo esc_attr( $input_name ); ?>" />
						 <?php if ( $submit_button_text ) : ?>
				<button type="submit">
					<?php echo esc_html( $submit_button_text ); ?>
				</button>
			<?php endif; ?>
			<?php
			if ( isset( $hidden_fields ) && !empty( $hidden_fields ) ) :
				foreach ( $hidden_fields as $key => $value ) :
					?>
					<input type="hidden"
								 name="<?php echo esc_html( $key ); ?>"
								 value="<?php echo esc_html( $value ); ?>" />
								 <?php
							 endforeach;
						 endif;
						 ?>
		</form>
		<?php
		return ob_get_clean();
	}

}
