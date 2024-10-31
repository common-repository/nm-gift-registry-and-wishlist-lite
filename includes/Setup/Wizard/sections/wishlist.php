<?php
defined( 'ABSPATH' ) || exit;

use NMGR\Setup\Wizard\Fields;

$fields = new Fields();
$fields->set_type( $current_section );
$is_gr_plugin_update = $fields->is_gift_registry_plugin_update();
?>

<?php
$wishlist_type_fields = $fields->get( $current_section );

foreach ( $wishlist_type_fields as $key => $args ) {
	$checkbox_args = array(
		'input_name' => $key,
		'input_id' => 'nmgr-' . $key,
		'input_class' => [ 'wishlist-type-input' ],
		'checked' => checked( $args[ 'default' ], 1, false ),
		'label_text' => $args[ 'label' ],
		'label_before' => true,
		'show_hidden_input' => true,
	);

	if ( $is_gr_plugin_update ) {
		$checkbox_args[ 'input_attributes' ] = [
			'disabled' => 'disabled',
		];
		$checkbox_args[ 'show_hidden_input' ] = 1;
	}

	echo '<div class="nmgr-wishlist-type nmgr-tc">' . nmgr_get_checkbox_switch( $checkbox_args ) . '</div>';
}
?>


<?php if ( $is_gr_plugin_update ) : ?>
	<fieldset class="nmgr-tc nmgr-container" style="line-height:1.3;">
		<?php
		echo esc_html( nmgr()->is_pro ?
				__( 'You are already using the plugin as a gift registry. Simply review the settings below and you\'re good to go.', 'nm-gift-registry' ) :
				__( 'You are already using the plugin as a gift registry. Simply review the settings below and you\'re good to go.', 'nm-gift-registry-lite' )
		);
		?>
	</fieldset>
<?php endif; ?>


<fieldset class="nmgr-container">
	<legend>General</legend>
	<?php
	if ( $is_gr_plugin_update ) {
		$fields->output_table( $fields->get( [ 'page_id', 'enable_archives' ] ) );
	} else {
		$fields->output_table( $fields->get( 'general' ) );
	}
	?>
</fieldset>


<?php if ( !$is_gr_plugin_update ) : ?>
	<fieldset class="nmgr-container">
		<legend>Add to wishlist</legend>
		<?php $fields->output_table( $fields->get( 'add_to_wishlist' ) ); ?>
	</fieldset>
<?php endif; ?>


<?php if ( !$is_gr_plugin_update ) : ?>
	<fieldset class="nmgr-container">
		<legend>Sharing</legend>
		<?php $fields->output_table( $fields->get( 'sharing' ) ); ?>
	</fieldset>
<?php endif; ?>


<?php if ( !$is_gr_plugin_update ) : ?>
	<?php if ( nmgr()->is_pro && 'gift-registry' === $current_section ) : ?>
		<fieldset class="nmgr-container">
			<legend>Shipping</legend>
			<?php $fields->output_table( $fields->get( 'shipping' ) ); ?>
		</fieldset>
	<?php endif; ?>
<?php endif; ?>

<input type="hidden" name="nmgr_setup_save" value="<?php echo esc_attr( $current_section ); ?>">

