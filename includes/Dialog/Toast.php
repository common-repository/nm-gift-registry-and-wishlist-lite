<?php

namespace NMGR\Dialog;

class Toast {

	protected $notice_type = 'success';
	protected $width = 350;
	protected $content;
	protected $colors = [
		'notice' => '#1e85be',
		'error' => '#b81c23',
		'success' => '#0f834d',
	];

	/**
	 * Set a toast notice type to get.
	 * @param string $type The type of notice. Values are notice, error and success.
	 * Default is success.
	 */
	public function set_notice_type( $type ) {
		$this->notice_type = $type;
	}

	public function set_content( $content ) {
		$this->content = $content;
	}

	protected function styles() {
		?>
		<style>
			.nmgr-notice .nmgr-content {
				padding: 11px 25px 11px 11px;
			}

			.nmgr-close-btn {
				background: none !important;
				border: none;
				color: black;
				cursor: pointer;
				display: block;
				padding: 0;
				position: absolute;
				top: 0;
				right: 0;
				width: 36px;
				height: 36px;
				text-align: center;
				border-radius: 0;
				margin: 0;
			}

			.nmgr-close-btn:before {
				font: normal 20px/1 dashicons;
				line-height: 1.8;
				content: "\f158";
			}

			.nmgr-notice {
				z-index: 100102;
				box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
				border-radius: calc(.3rem - 1px);
				position: relative; /* for admin (close btn disappears in admin if style is removed) */
				width: <?php echo ( int ) $this->width; ?>px;
				color: white;
			}

			.nmgr-toaster {
				position: fixed;
				left: 0;
				bottom: 0;
				margin: 1rem;
				width: max-content;
				max-width: 95vw;
				z-index: 999999;
				display: table;
			}

			.nmgr-toaster > :not(:last-child) {
				margin-bottom: .75rem;
			}

			.nmgr-toaster > * {
				left: 0 !important;
				top: 0 !important;
				position: relative !important;
			}

			.nmgr-toaster .button.wc-forward {
				display: none;
			}

			@media (max-width: <?php echo ( int ) $this->width; ?>px) {
				.nmgr-toaster {
					margin: 0;
					left: 50%;
					transform: translateX(-50%);
				}

				.nmgr-notice {
					max-width: 95vw;
				}
			}

			<?php echo esc_attr( ".nmgr-notice.{$this->notice_type}" ); ?> {
				background: <?php echo esc_attr( $this->colors[ $this->notice_type ] ); ?>
			}
		</style>
		<?php
	}

	protected function template() {
		?>
		<div tabindex="-1" class="nmgr-notice <?php echo esc_attr( $this->notice_type ); ?>">
			<button type="button" class="nmgr-close-btn"></button>
			<div class="nmgr-content">
				<?php
				$this->styles();
				echo wp_kses( $this->content, nmgr_allowed_post_tags() );
				?>
			</div>
		</div>
		<?php
	}

	public function get() {
		ob_start();
		$this->print();
		return ob_get_clean();
	}

	public function print() {
		$this->template();
	}

}
