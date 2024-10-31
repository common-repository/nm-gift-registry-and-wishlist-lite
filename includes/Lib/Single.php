<?php

namespace NMGR\Lib;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class Single {

	private $type;

	/**
	 * @var NMGR_Wishlist|\NMGR\Sub\Wishlist
	 */
	private $wishlist;
	private $notices = [];
	private $is_disabled;
	private $is_password_protected;
	private $page;

	public function __construct( $id = false ) {
		$this->wishlist = nmgr_get_wishlist( $id, true );
		$this->type = $this->wishlist ? $this->wishlist->get_type() : nmgr_get_current_type();
		$this->is_password_protected = $this->wishlist ? post_password_required( $this->wishlist->get_id() ) : false;
	}

	public function get_id() {
		return $this->wishlist ? $this->wishlist->get_id() : 0;
	}

	public function set_type( $type ) {
		$this->type = $type;
	}

	public function set_page( $page ) {
		$this->page = $page;
	}

	public function is_page( $page ) {
		return $this->page === $page || is_nmgr_wishlist_page( $page );
	}

	private function set_notice( $key, $message, $type = 'notice' ) {
		$this->notices[ $key ] = [
			'message' => $message,
			'type' => $type,
		];
	}

	public function process_notices() {
		$is_single = is_nmgr_wishlist();

		if ( !$this->wishlist || ($is_single && ($this->is_disabled || $this->is_password_protected)) ) {
			return;
		}

		$is_gift_registry = $this->wishlist->is_type( 'gift-registry' );
		$user_has_wishlist = nmgr_user_has_wishlist( $this->wishlist );
		$is_archived = is_callable( [ $this->wishlist, 'is_archived' ] ) ? $this->wishlist->is_archived() : false;
		$is_admin = is_nmgr_admin();
		$single_or_admin = $is_single || $is_admin;

		if ( $is_gift_registry && $is_archived && ($single_or_admin || is_nmgr_account_section()) ) {
			$this->set_notice( 'wishlist_archived', $this->get_registered_notice( 'wishlist_archived' ) );
		}

		if ( $is_gift_registry && ($single_or_admin || is_nmgr_account_section( 'shipping' )) &&
			!$is_archived && $this->wishlist->needs_shipping_address() ) {
			$notice = $this->get_registered_notice( 'require_shipping_address' );

			if ( $is_single && $user_has_wishlist ) {
				$link = trailingslashit( $this->wishlist->get_permalink() ) . 'shipping';
				$filtered_link = apply_filters( 'nmgr_set_shipping_address_url', $link, $this->wishlist );

				if ( $filtered_link ) {
					$notice .= sprintf( '<a class="button" href="%s">%s</a>',
						$filtered_link,
						nmgr()->is_pro ?
						__( 'Set now', 'nm-gift-registry' ) :
						__( 'Set now', 'nm-gift-registry-lite' )
					);
				}
			}
			$this->set_notice( 'require_shipping_address', $notice );
		}

		if ( $is_gift_registry && $single_or_admin && $this->wishlist->is_fulfilled() ) {
			$this->set_notice( 'wishlist_fulfilled', $this->get_registered_notice( 'wishlist_fulfilled' ) );
		}

		if ( $is_single && !$is_archived && !$this->wishlist->has_items() ) {
			$link = '';
			if ( $user_has_wishlist ) {
				$link = sprintf( '<a href="%s" tabindex="1" class="button">%s</a>',
					nmgr_get_add_items_url(),
					nmgr()->is_pro ?
					__( 'Add item(s)', 'nm-gift-registry' ) :
					__( 'Add item(s)', 'nm-gift-registry-lite' )
				);
			}

			$this->set_notice( 'wishlist_empty', $link . $this->get_registered_notice( 'wishlist_empty' ) );
		}
	}

	private function get_body_html() {
		ob_start();
		if ( $this->is_page( 'new' ) ) {
			if ( is_nmgr_user( $this->type ) ) {
				$text = nmgr()->is_pro ?
					__( 'Add new', 'nm-gift-registry' ) :
					__( 'Add new', 'nm-gift-registry-lite' );
				echo '<h3 class="nmgr-add-new-header-text">' . esc_html( $text ) . '</h3>';

				nmgr()->account()->show_new_wishlist_template( $this->type );
			} else {
				$this->set_login_notice();
			}
		} elseif ( $this->is_page( 'account_section' ) ) {
			if ( nmgr_user_has_wishlist( $this->wishlist ) ) {
				$section = get_query_var( 'nmgr_action' );
				$title = nmgr()->account( $this->wishlist )->set_section( $section )->get_section_title();

				if ( $title ) :
					?>
					<div class="nmgr-account-section-header">
						<a href="<?php echo esc_url( $this->wishlist->get_permalink() ); ?>"
							 class="nmgr-wishlist-title">
								 <?php echo esc_html( $this->wishlist->get_title() ); ?>
						</a>
						<h3 class="nmgr-template-title <?php echo esc_attr( $section ); ?>">
							<?php echo wp_kses_post( $title ); ?>
						</h3>
					</div>
					<?php
				endif;
				echo nmgr_get_account_section( $section, $this->wishlist->get_id() );
			}
		} elseif ( $this->is_page( 'home' ) ) {
			if ( is_nmgr_user( $this->type ) ) {
				if ( !nmgr_get_user_wishlists_count( '', $this->type ) ) {
					echo nmgr_default_content( "create_{$this->type}" );
				} else {
					nmgr()->account()->show_all_wishlists_template( $this->type );
				}
			} else {
				$this->set_login_notice();
			}
		} elseif ( $this->is_page( 'single' ) ) {
			if ( $this->is_page( 'base' ) ) {
				if ( is_nmgr_user( $this->type ) ) {
					if ( !nmgr_get_user_wishlists_count( '', $this->type ) ) {
						echo nmgr_default_content( "create_{$this->type}" );
					} elseif ( nmgr_user_has_wishlist( $this->wishlist ) ) {
						echo $this->get_post();
					}
				} else {
					$this->set_login_notice();
				}
			} else {
				if ( !$this->wishlist ) {
					$this->is_disabled = true;
					$this->set_notice( 'invalid_wishlist', $this->get_registered_notice( 'invalid_wishlist' ) );
				} elseif ( 'private' === $this->wishlist->get_status() && !nmgr_user_has_wishlist( $this->wishlist ) ) {
					$this->is_disabled = true;
					$this->set_notice( 'private_wishlist', $this->get_registered_notice( 'private_wishlist' ) );
				} else {
					echo $this->get_post();
				}
			}
		}
		return ob_get_clean();
	}

	private function get_post() {
		if ( $this->is_disabled ) {
			return;
		}

		ob_start();

		if ( $this->get_id() ) {
			global $post;

			$post = get_post( $this->get_id() );

			setup_postdata( $post );

			$this->content( $this->wishlist );

			wp_reset_postdata();
		}

		return ob_get_clean();
	}

	private function set_login_notice() {
		$url = add_query_arg( array(
			'nmgr-notice' => 'login-to-access',
			'nmgr-redirect' => $_SERVER[ 'REQUEST_URI' ],
			'nmgr-type' => $this->type,
			), wc_get_page_permalink( 'myaccount' ) );

		$notice = $this->get_registered_notice( 'login-to-access' ) . ' ' . nmgr_get_click_here_link( $url );

		$this->set_notice( 'login-to-access', $notice );
	}

	public function get_registered_notice( $key ) {
		$notice = '';

		switch ( $key ) {
			case 'login-to-access':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Login to access your %s.', 'nm-gift-registry' ) : __( 'Login to access your %s.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'invalid_wishlist':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'This %s does not exist or it cannot be viewed.', 'nm-gift-registry' ) : __( 'This %s does not exist or it cannot be viewed.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'private_wishlist':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					__( 'This %s is private.', 'nm-gift-registry' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'wishlist_archived':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					__( 'This %s is archived.', 'nm-gift-registry' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'wishlist_fulfilled':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'This %s is fulfilled.', 'nm-gift-registry' ) : __( 'This %s is fulfilled.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'wishlist_empty':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'This %s is empty.', 'nm-gift-registry' ) : __( 'This %s is empty.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;

			case 'require_shipping_address':
				$notice = sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'The shipping address for this %s must be properly filled before items can be purchased from it.', 'nm-gift-registry' ) : __( 'The shipping address for this %s must be properly filled before items can be purchased from it.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->type )
				);
				break;
		}

		return $notice;
	}

	public function get_notices() {
		return $this->notices;
	}

	private function get_notices_html() {
		ob_start();

		$this->process_notices();

		foreach ( $this->notices as $notice ) {
			if ( function_exists( 'wc_print_notice' ) ) {
				wc_print_notice( $notice[ 'message' ], 'notice' );
			}
		}
		return ob_get_clean();
	}

	private function process_menu() {
		$wishlists_count = nmgr_get_user_wishlists_count( nmgr_get_current_user_id(), $this->type );
		$user_has_wishlist = nmgr_user_has_wishlist( $this->wishlist );

		if ( !is_nmgr_user( $this->type ) || $this->is_disabled || ($this->wishlist && !$user_has_wishlist) ) {
			return [];
		}

		$is_home = $this->is_page( 'home' );
		$is_new = $this->is_page( 'new' );
		$is_single = is_nmgr_wishlist();
		$is_archived = is_callable( [ $this->wishlist, 'is_archived' ] ) ? $this->wishlist->is_archived() : false;
		$allow_multi = nmgr()->is_pro ? nmgr_get_type_option( $this->type, 'allow_multiple_wishlists' ) : false;

		$actions = [
			'manage' => [
				'text' => nmgr()->is_pro ?
				__( 'Manage', 'nm-gift-registry' ) :
				__( 'Manage', 'nm-gift-registry-lite' ),
				'priority' => 10,
				'show' => $user_has_wishlist,
			],
			'add_new' => [
				'text' => nmgr()->is_pro ?
				__( 'Add new', 'nm-gift-registry' ) :
				__( 'Add new', 'nm-gift-registry-lite' ),
				'priority' => 20,
				'attributes' => [
					'href' => nmgr_get_url( $this->type, 'new' ),
				],
				'show' => ($user_has_wishlist || $is_home || $is_new || $is_single) && (!$wishlists_count || $allow_multi),
			],
			'see_all' => [
				'text' => __( 'See all', 'nm-gift-registry' ),
				'priority' => 30,
				'attributes' => [
					'href' => nmgr_get_url( $this->type, 'home' ),
				],
				'show' => ($is_new && $wishlists_count) ||
				(($user_has_wishlist || $is_home) && $wishlists_count && $allow_multi),
			],
			'add_items' => [
				'text' => nmgr()->is_pro ?
				__( 'Add item(s)', 'nm-gift-registry' ) :
				__( 'Add item(s)', 'nm-gift-registry-lite' ),
				'priority' => 40,
				'attributes' => [
					'class' => [
						'nmgr-tip',
					],
					'title' => nmgr_get_add_items_text( $this->type ),
					'href' => nmgr_get_add_items_url(),
				],
				'show' => ($user_has_wishlist && !$this->wishlist->needs_shipping_address() && !$is_archived) ||
				$is_single || $is_home,
			],
			'view' => [
				'text' => nmgr()->is_pro ?
				__( 'View', 'nm-gift-registry' ) :
				__( 'View', 'nm-gift-registry-lite' ),
				'priority' => 50,
				'attributes' => [
					'href' => $this->wishlist ? $this->wishlist->get_permalink() : '#',
				],
				'show' => $user_has_wishlist && $this->is_page( 'account_section' ),
			],
		];

		foreach ( array_keys( $actions ) as $key ) {
			$actions[ $key ][ 'attributes' ][ 'class' ][] = 'nmgr-menu-item';
		}

		$account = nmgr()->account( $this->wishlist );
		$account_sections = $account->get_sections_data();

		$dropdown = nmgr_get_dropdown();
		$dropdown_toggler = '<a style="pointer-events:none;">' . $actions[ 'manage' ][ 'text' ] . ' &#x25B8;</a>';
		$dropdown->set_toggler( $dropdown_toggler );

		foreach ( $account_sections as $dkey => $daction ) {
			if ( in_array( $dkey, [ 'items', 'images' ] ) ) {
				continue;
			}

			$attributes = [
				'href' => $this->wishlist ? trailingslashit( $this->wishlist->get_permalink() ) . $dkey : '#',
			];

			if ( is_nmgr_account_section( $dkey ) ) {
				$attributes[ 'class' ] = [ 'active' ];
			}

			$dropdown->set_menu_item(
				($daction[ 'title' ] ?? '' ),
				($attributes )
			);
		}

		$manage_text = $dropdown->get();
		$actions[ 'manage' ][ 'text' ] = $manage_text;

		$factions = apply_filters( 'nmgr_wishlist_menu', $actions, $this->get_id() );
		$showing_actions = Fields::get_elements_to_show( $factions );
		Fields::sort_by_priority( $showing_actions );

		return $showing_actions;
	}

	private function get_menu_html() {
		ob_start();
		$menu = $this->process_menu();

		if ( !empty( $menu ) ) :
			?>
			<div class="nmgr-dashboard">
				<div class="nmgr-wishlist-menu">
					<?php
					foreach ( $menu as $action ) :
						if ( !empty( $action[ 'attributes' ][ 'href' ] ) ) :
							?>
							<a <?php echo nmgr_utils_format_attributes( $action[ 'attributes' ] ); ?>>
								<?php echo wp_kses( $action[ 'text' ], nmgr_allowed_post_tags() ); ?>
							</a>
							<?php
						elseif ( !empty( $action[ 'text' ] ) ) :
							echo wp_kses( $action[ 'text' ], nmgr_allowed_post_tags() );
						endif;
					endforeach;
					?>
				</div>

				<?php
				if ( nmgr_user_has_wishlist( $this->wishlist ) && is_nmgr_wishlist() ) :
					?>
					<div class="nmgr-dashboard-right">
						<?php
						if ( $this->wishlist->is_type( 'gift-registry' ) ) {
							$this->show_messages_icon( $this->wishlist );
						}

						$this->show_status_icon( $this->wishlist );
						?>
					</div>
				<?php endif; ?>

			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	private function show_messages_icon() {
		$account = nmgr()->account( $this->wishlist )->set_section( 'messages' );
		if ( !empty( $account->get_section_data() ) ) {
			$messages_count = $this->wishlist->get_unread_messages();

			if ( $messages_count ) :
				$title = sprintf(
					/* translators: %d: messages count */
					_n(
						'%d unread message',
						'%d unread messages',
						$messages_count,
						'nm-gift-registry' ),
					$messages_count
				);
				?>
				<a class="nmgr-messages-icon nmgr-tip" title="<?php esc_attr_e( $title ); ?>"
					 href="<?php echo esc_url( $this->wishlist->get_permalink() . '/messages' ); ?>">
					<div class="hang-badge">
						<?php
						$icon_args = [
							'icon' => 'bubble',
							'size' => '1.2',
							'fill' => '#eee',
						];

						echo wp_kses( nmgr_get_svg( $icon_args ), nmgr_allowed_svg_tags() );
						?>

						<span class="nmgr-badge">
							<?php echo $messages_count; ?>
						</span>
					</div>
				</a>
				<?php
			endif;
		}
	}

	private function show_status_icon() {
		$html = '';

		if ( !$this->wishlist ) {
			return;
		}

		if ( method_exists( $this->wishlist, 'get_password' ) && !empty( $this->wishlist->get_password() ) ) {
			$html = nmgr_get_svg( array(
				'icon' => 'lock',
				'fill' => '#999',
				'size' => '1.2',
				'sprite' => false,
				'title' => sprintf(
					/* translators: %s: wishlist type title */
					__( 'Your %s is password protected. Do not forget to share the password with your guests.', 'nm-gift-registry' ),
					nmgr_get_type_title( '', false, $this->type ) ),
				) );
		} elseif ( method_exists( $this->wishlist, 'get_status' ) && 'private' === $this->wishlist->get_status() ) {
			$html = nmgr_get_svg( array(
				'icon' => 'eye-cancel',
				'fill' => '#999',
				'size' => '1.2',
				'sprite' => false,
				'title' => sprintf(
					/* translators: %s: wishlist type title */
					__( 'Your %s is published privately. This means that only you can see it when logged in.', 'nm-gift-registry' ),
					nmgr_get_type_title( '', false, $this->type ) ),
				) );
		}

		if ( $html ) {
			?>
			<div class="nmgr-wishlist-status"><?php echo wp_kses( $html, nmgr_allowed_svg_tags() ); ?></div>
			<?php
		}
	}

	public function get() {
		if ( !is_nmgr_enabled( $this->type ) ) {
			return;
		}

		$body = $this->get_body_html();
		$menu = $this->get_menu_html();
		$notices = $this->get_notices_html(); //always get notices last

		$template_full = '<div class="woocommerce nmgr-template">' . $notices . $menu . $body . '</div>';
		return apply_filters( 'nmgr_wishlist_template', $template_full, $this );
	}

	private function content( $wishlist ) {
		do_action( 'nmgr_before_single', $wishlist );

		if ( post_password_required() ) {
			echo get_the_password_form();
			return;
		}
		?>
		<div id="nmgr-<?php the_ID(); ?>">
			<?php do_action( 'nmgr_wishlist', $wishlist ); ?>
		</div>

		<?php
		do_action( 'nmgr_after_single', $wishlist );
	}

	public static function get_template( $atts = [] ) {
		$id = $atts[ 'id' ] ?? 0;
		if ( !empty( $id ) && is_numeric( $id ) ) {
			$id = absint( $id );
		} elseif ( is_a( $id, \NMGR_Wishlist::class ) ) {
			$id = $id->get_id();
		} elseif ( empty( $id ) ) {
			$id = ( int ) nmgr_get_current_wishlist_id();
		}

		$single = new static( $id );
		$single->set_page( $atts[ 'page' ] ?? 'single' ); // Set single as the default page if no page is given.
		return $single->get();
	}

	public static function get_share_template( $id ) {
		$wishlist = is_a( $id, \NMGR_Wishlist::class ) ? $id : nmgr_get_wishlist( $id, true );
		$type = $wishlist ? $wishlist->get_type() : 'gift-registry';

		if ( !$wishlist ||
			!is_nmgr_enabled( $type ) ||
			('publish' !== $wishlist->get_status()) ) {
			return;
		}

		$url = $wishlist->get_permalink();
		$wishlist_title = $wishlist->get_title();
		$desc = $wishlist->get_description() ?
			$wishlist->get_description() :
			apply_filters( 'nmgr_default_share_description', sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Here is the link to my %s:', 'nm-gift-registry' ) : __( 'Here is the link to my %s:', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, nmgr_get_current_type() )
				) );
		$image = '';

		if ( method_exists( $wishlist, 'get_thumbnail_id' ) && $wishlist->get_thumbnail_id() ) {
			$image = wp_get_attachment_url( $wishlist->get_thumbnail_id() );
		}

		$shares = [
			'facebook' => [
				'url' => add_query_arg(
					array(
						'u' => rawurlencode( $url ),
						'p[title]' => rawurlencode( $wishlist_title ),
					), 'http://www.facebook.com/sharer/sharer.php' ),
			],
			'twitter' => [
				'url' => add_query_arg( array(
					'url' => rawurlencode( $url ),
					'text' => rawurlencode( $desc ),
					), 'https://x.com/share' ),
			],
			'pinterest' => [
				'url' => add_query_arg( array(
					'url' => rawurlencode( $url ),
					'description' => rawurlencode( $desc ),
					'media' => $image ? rawurlencode( $image ) : '',
					), 'http://pinterest.com/pin/create/button/' ),
			],
			'whatsapp' => [
				'url' => add_query_arg( array(
					'text' => rawurlencode( $wishlist_title . "\r\n\r\n" . $desc . "\r\n\r\n" . $url ),
					), 'https://wa.me/' ),
			],
			'email' => [
				'url' => add_query_arg( array(
					'subject' => rawurlencode( $wishlist_title ),
					'body' => rawurlencode( $desc . ' ' . $url ),
					'title' => rawurlencode( $wishlist_title ),
					), 'mailto:' ),
			],
		];

		foreach ( $shares as $key => $val ) {
			$shares[ $key ][ 'show' ] = nmgr_get_type_option( $type, "share_on_$key" );
			$shares[ $key ][ 'icon' ] = nmgr_get_svg( [
				'icon' => $key,
				'size' => 1.375,
				'sprite' => false,
				'fill' => 'white'
				] );
		}

		$fields = new \NMGR\Fields\Fields();
		$fields->set_id( 'sharing' );
		$fields->set_data( $shares );
		$fields->filter_showing();
		$sharing = $fields->get_data();

		ob_start();
		if ( $sharing ) :
			?>
			<div class="nmgr-sharing">
				<?php
				$title = nmgr()->is_pro ?
					__( 'Share on:', 'nm-gift-registry' ) :
					__( 'Share on:', 'nm-gift-registry-lite' );
				printf( '<h3 class="nmgr-template-title sharing">%s</h3>', esc_html( $title ) );
				?>

				<div class="nmgr-sharing-options">
					<?php
					foreach ( $sharing as $key => $val ) {
						?>
						<div class="share-item nmgr-tip nmgr-share-on-<?php echo esc_attr( $key ); ?>"
								 title="<?php
				echo esc_attr( sprintf(
						/* translators: %s: social media name */
						nmgr()->is_pro ? __( 'Click to share on %s', 'nm-gift-registry' ) : __( 'Click to share on %s', 'nm-gift-registry-lite' ),
						'twitter' === $key ? 'X' : $key
					) );
						?>">
							<a target="_blank" href="<?php echo esc_url( $val[ 'url' ] ); ?>"
								 style='display:inline-block;line-height:0;padding:8px;background-color:#ddd;border-radius:50%;'>
								<?php echo wp_kses( $val[ 'icon' ], nmgr_allowed_post_tags() ); ?>
							</a>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		endif;
		$content = ob_get_clean();

		return $content;
	}

}
