<?php
defined( 'ABSPATH' ) || exit;
?>

<p class="nmgr-tc nmgr-version"><?php echo esc_html( nmgr()->version ); ?></p>

<?php
if ( 'update' === ($_GET[ 'nmgr-key' ] ?? '') ) {
	?>
	<h3 class="nmgr-tc whats-new">
		<?php
		echo esc_html__( nmgr()->is_pro ?
				__( 'What\'s New', 'nm-gift-registry' ) :
				__( 'What\'s New', 'nm-gift-registry-lite' )  );
		?>
	</h3>
	<p class="nmgr-tc">
		<?php
		echo esc_html__( nmgr()->is_pro ?
				__( 'This version of the plugin introduced some major changes. The most important ones are listed below.', 'nm-gift-registry' ) :
				__( 'This version of the plugin introduced some major changes. The most important ones are listed below.', 'nm-gift-registry-lite' )  );
		?>
	</p>

	<ul>
		<li>
			<?php
			echo esc_html__( nmgr()->is_pro ?
					__( 'You can now use the plugin as both a full wishlist plugin and a full gift registry plugin.', 'nm-gift-registry' ) :
					__( 'You can now use the plugin as both a full wishlist plugin and a full gift registry plugin.', 'nm-gift-registry-lite' )  );
			?>
		</li>
		<li>
			<?php
			echo esc_html__( nmgr()->is_pro ?
					__( 'Every thing concerning a customer\'s wishlist or gift registry is now managed through a single page. There is no more "page for managing wishlists", "page for viewing wishlist archives" or "page for viewing single wishlists". A single page takes care of all of these. As a result of this, wishlists are no more managed in the WooCommerce my-account area.', 'nm-gift-registry' ) :
					__( 'Every thing concerning a customer\'s wishlist or gift registry is now managed through a single page. There is no more "page for managing wishlists", "page for viewing wishlist archives" or "page for viewing single wishlists". A single page takes care of all of these. As a result of this, wishlists are no more managed in the WooCommerce my-account area.', 'nm-gift-registry-lite' )  );
			?>
		</li>
		<li>
			<?php
			echo esc_html__( nmgr()->is_pro ?
					__( 'Some plugin settings have been removed to make configuring the plugin easy and straightforward. If you have used these settings already, there\'s no need to worry as your configuration would still remain. Some of these settings can be changed using filters.', 'nm-gift-registry' ) :
					__( 'Some plugin settings have been removed to make configuring the plugin easy and straightforward. If you have used these settings already, there\'s no need to worry as your configuration would still remain. Some of these settings can be changed using filters.', 'nm-gift-registry-lite' )  );
			?>
		</li>
		<li>
			<?php
			echo esc_html__( nmgr()->is_pro ?
					__( 'You can choose to create an order in the admin area when updating the purchased quantity of an item so that the update can be tracked just like an order created at the WooCommerce checkout.', 'nm-gift-registry' ) :
					__( 'You can choose to create an order in the admin area when updating the purchased quantity of an item so that the update can be tracked just like an order created at the WooCommerce checkout.', 'nm-gift-registry-lite' )  );
			?>
		</li>
	</ul>
	<div class="nmgr-note nmgr-tc">
		<?php
		echo esc_html( nmgr()->is_pro ?
				__( 'Click continue now to review your current settings.', 'nm-gift-registry' ) :
				__( 'Click continue now to review your current settings.', 'nm-gift-registry-lite' )  );
		?>
	</div>
	<?php
} else {
	?>
	<div class="nmgr-tc">
		<p class="nmgr-welcome-text">
			<?php
			echo esc_html( nmgr()->is_pro ?
					__( 'Welcome! Here you can quickly set up the plugin to work for you just the way you want.', 'nm-gift-registry' ) :
					__( 'Welcome! Here you can quickly set up the plugin to work for you just the way you want.', 'nm-gift-registry-lite' )  );
			?>
		</p>
		<p class="nmgr-setup-info">
			<?php
			$wishlist_text = nmgr_get_type_title( '', '', 'wishlist' );
			$gift_registry_text = nmgr_get_type_title( '', '', 'gift-registry' );

			if ( nmgr()->is_pro ) {
				$label_text = sprintf(
					/* translators: 1: wishist, 2: gift registry */
					__( 'You can use the plugin as a %1$s, %2$s or both.', 'nm-gift-registry' ),
					'<span class="nmgr-wishlist-text">' . $wishlist_text . '</span>',
					'<span class="nmgr-wishlist-text">' . $gift_registry_text . '</span>',
				);
			} else {
				$label_text = sprintf(
					/* translators: 1: wishist, 2: gift registry */
					__( 'You can use the plugin as a %1$s, %2$s or both.', 'nm-gift-registry-lite' ),
					'<span class="nmgr-wishlist-text">' . $wishlist_text . '</span>',
					'<span class="nmgr-wishlist-text">' . $gift_registry_text . '</span>',
				);
			}
			echo wp_kses_post( $label_text );
			?>
		</p>
		<div class="nmgr-note">
			<?php
			echo esc_html( nmgr()->is_pro ?
					__( 'Note that all these settings can be changed in the plugin settings page at anytime.', 'nm-gift-registry' ) :
					__( 'Note that all these settings can be changed in the plugin settings page at anytime.', 'nm-gift-registry-lite' )  );
			?>
		</div>
	</div>

	<?php
}


