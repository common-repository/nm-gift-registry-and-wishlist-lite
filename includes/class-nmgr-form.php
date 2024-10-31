<?php

use NMGR\Fields\Fields;

/**
 * Sync
 */
defined( 'ABSPATH' ) || exit;

/**
 * Handles generation, sanitization, validation and saving of wishlist form fields
 */
class NMGR_Form {

	/**
	 * Wishlist object passed to the form
	 *
	 * @var NMGR_Wishlist
	 */
	protected $wishlist;
	protected $type = 'gift-registry';
	private $all_fields = [];

	/**
	 * Current form fields being worked with
	 *
	 * @var array
	 */
	protected $data = array();
	private $error_data = [];

	/**
	 * The number of fields being retrieved from the form
	 *
	 * (Typically used when get_fields() is called directly or indirectly to enable
	 * the caller know the number of fields returned based on those excluded)
	 *
	 * @var int
	 */
	public $fields_count = 0;

	public static function run() {
		add_filter( 'nmgr_requested_fields', array( __CLASS__, 'modify_form_fields' ), 10 );
		add_action( 'woocommerce_form_field', array( __CLASS__, 'remove_optional_required_html' ), 10, 3 );
		add_action( 'woocommerce_form_field', array( __CLASS__, 'replace_name_attribute' ), 10, 3 );
		add_filter( 'woocommerce_form_field_nmgr-hidden', array( __CLASS__, 'create_hidden_field' ), 10, 4 );
		add_filter( 'woocommerce_form_field_nmgr-checkbox', array( __CLASS__, 'create_checkbox_switch' ), 10, 4 );
		add_filter( 'woocommerce_form_field_nmgr-radio-group', array( __CLASS__, 'create_radio_group_field' ), 10, 4 );
	}

	public function __construct( $wishlist_id = 0 ) {
		$wishlist = $wishlist_id ? nmgr_get_wishlist( $wishlist_id ) : nmgr()->wishlist();
		$this->wishlist = $wishlist ? $wishlist : nmgr()->wishlist();

		if ( $this->get_wishlist()->get_id() ) {
			$this->set_type( $this->get_wishlist()->get_type() );
		}
	}

	public function set_type( $type ) {
		$this->type = $type ?? $this->type;
	}

	public function get_type() {
		return $this->type;
	}

	/**
	 * Add new hidden field to woocommerce_form_field function
	 */
	public static function create_hidden_field( $field, $key, $args, $value ) {
		$field = '<input type="hidden" id="' . esc_attr( $args[ 'id' ] ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		return $field;
	}

	/**
	 * Add checkbox toggle switch to woocommerce_form_field function
	 */
	public static function create_checkbox_switch( $field, $key, $args, $value ) {
		return self::create_custom_checkbox_switch( $key, $args, $value );
	}

	public static function create_custom_checkbox_switch( $key, $args, $value ) {
		$checkbox_args = array(
			'input_name' => $key,
			'input_id' => $args[ 'id' ],
			'label_text' => $args[ 'label' ],
			'checked' => $value ? true : false,
			'input_attributes' => $args[ 'custom_attributes' ] ?? [],
		);

		if ( !empty( $args[ 'checkbox_args' ] ) ) {
			$checkbox_args = array_merge( $checkbox_args, $args[ 'checkbox_args' ] );
		}

		$sort = $args[ 'priority' ] ?? '';
		$field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';
		$container_class = esc_attr( implode( ' ', ( array ) ($args[ 'class' ] ?? [] ) ) );
		$container_id = esc_attr( $args[ 'id' ] ) . '_field';
		$field_html = nmgr_get_checkbox_switch( $checkbox_args );

		return sprintf( $field_container, $container_class, $container_id, $field_html );
	}

	public static function create_radio_group_field( $field, $key, $args, $value ) {
		if ( !empty( $args[ 'options' ] ) ) {
			$label_id = $args[ 'id' ] . '_' . current( array_keys( $args[ 'options' ] ) );

			$field = '';
			foreach ( $args[ 'options' ] as $option_key => $option_text ) {
				$checkbox_args = array(
					'input_type' => 'radio',
					'input_name' => $key,
					'input_value' => $option_key,
					'input_id' => $args[ 'id' ] . '_' . $option_key,
					'checked' => $value === $option_key,
					'input_attributes' => $args[ 'custom_attributes' ] ?? [],
					'input_class' => $args[ 'input_class' ] ?? [],
					'label_text' => $option_text,
					'label_class' => $args[ 'label_class' ] ?? [],
					'label_before' => true,
				);

				$field .= nmgr_get_checkbox_switch( $checkbox_args );
			}

			$sort = $args[ 'priority' ] ?? '';
			$label = '<label for="' . esc_attr( $label_id ) . '">' . ($args[ 'label' ] ?? '') . '</label>';
			$container_class = esc_attr( implode( ' ', ( array ) ($args[ 'class' ] ?? [] ) ) );
			$container_id = esc_attr( $args[ 'id' ] ) . '_field';
			$field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s %4$s</p>';

			return sprintf( $field_container, $container_class, $container_id, $label, $field );
		}
		return $field;
	}

	/**
	 * Remove the 'optional' html from all optional fields
	 * and replace the 'required' html with something we can use with tooltips
	 */
	public static function remove_optional_required_html( $field, $key, $args ) {
		if ( false !== strpos( $key, 'nmgr_' ) || isset( $args[ 'prefix' ] ) ) {
			$field = str_replace( '<span class="optional">(optional)</span>', '', $field );
		}

		if ( false !== strpos( $key, 'nmgr_' ) || isset( $args[ 'prefix' ] ) ) {
			$field = str_replace( '<abbr class="required', '<abbr class="nmgr-tip required', $field );
		}

		return $field;
	}

	public static function replace_name_attribute( $field, $key, $args ) {
		if ( false !== strpos( $key, 'nmgr_' ) && !empty( $args[ 'name' ] ) ) {
			$field = preg_replace( '/name="([^"]+)/', 'name="' . $args[ 'name' ], $field );
		}
		return $field;
	}

	/**
	 * Modify form fields before output
	 *
	 * @param array $fields Form fields
	 * @return array Modified form fields
	 */
	public static function modify_form_fields( $fields ) {
		foreach ( $fields as $field => $args ) {
			/**
			 * Add html5 required attribute to fields if required
			 *
			 * Let's not do this for woocommerce's shipping fields on the frontend to
			 * prevent html5 focussable error being generated when field is hidden
			 */
			if ( isset( $args[ 'required' ] ) && $args[ 'required' ] &&
				(is_nmgr_admin() || (!is_nmgr_admin() && false === strpos( $field, 'shipping_' )))
			) {
				$fields[ $field ][ 'custom_attributes' ][ 'required' ] = true;
			}

			switch ( $field ) {
				case 'first_name':
					if ( isset( $fields[ 'last_name' ] ) ) {
						$fields[ $field ][ 'class' ][] = 'form-row-first';
					}
					break;
				case 'last_name':
					if ( isset( $fields[ 'first_name' ] ) ) {
						$fields[ $field ][ 'class' ][] = 'form-row-last';
					}
					break;
				case 'partner_first_name':
					if ( isset( $fields[ 'partner_last_name' ] ) ) {
						$fields[ $field ][ 'class' ][] = 'form-row-first';
					}
					break;
				case 'partner_last_name':
					if ( isset( $fields[ 'partner_first_name' ] ) ) {
						$fields[ $field ][ 'class' ][] = 'form-row-last';
					}
					break;
				case 'nmgr_exclude_from_search':
					if ( nmgr()->is_pro && isset( $args[ 'label' ] ) && !empty( $args[ 'label' ] ) ) {
						$fields[ $field ][ 'label' ] = $args[ 'label' ] . nmgr_get_help_tip( sprintf(
									/* translators: %s: wishlist type title */
									__( 'Hide my %s from search results no matter the visibility.', 'nm-gift-registry' ), nmgr_get_type_title()
								) );
					}
					break;
			}
		}
		return $fields;
	}

	/**
	 * Whether the form retrieved any fields
	 *
	 * This function is typically used after get_fields() has been called directly, or
	 * indirectly using get_fields_html(), to determine whether any fields were actually
	 * returned based on those that should be excluded.
	 *
	 * @return boolean
	 */
	public function has_fields() {
		return ( bool ) $this->fields_count;
	}

	/**
	 * Get the wishlist object the form is working with
	 *
	 * @return NMGR_Wishlist
	 */
	public function get_wishlist() {
		return $this->wishlist;
	}

	/**
	 * Get the value stored for a form field in the wishlist object
	 *
	 * @param string $key The name of the form field
	 * @return mixed The value or null
	 */
	public function get_wishlist_value( $key ) {
		if ( is_callable( array( $this->wishlist, "get_$key" ) ) ) {
			$value = $this->wishlist->{"get_$key"}();
		} else {
			$value = $this->wishlist->get_data()[ $key ] ?? null;
		}

		return $value;
	}

	/**
	 * Set the default values of form fields
	 *
	 * @param array $fields
	 */
	public function set_defaults( $fields ) {
		// Don't set defaults in admin area
		if ( is_nmgr_admin() ) {
			return $fields;
		}

		$user = wp_get_current_user();
		$postmeta = is_a( $this->wishlist, 'NMGR_Wishlist' ) ? get_post_meta( $this->wishlist->get_id() ) : array();

		foreach ( $fields as $key => $args ) {
			if ( isset( $args[ 'default' ] ) ) {
				continue;
			}

			switch ( $key ) {
				case 'first_name':
					if ( !isset( $postmeta[ '_first_name' ] ) ) {
						$fields[ $key ][ 'default' ] = $user->first_name;
					}
					break;
				case 'last_name':
					if ( !isset( $postmeta[ '_last_name' ] ) ) {
						$fields[ $key ][ 'default' ] = $user->last_name;
					}
					break;
				case 'email':
					if ( !isset( $postmeta[ '_email' ] ) ) {
						$fields[ $key ][ 'default' ] = $user->user_email;
					}
					break;
			}
		}
		return $fields;
	}

	/**
	 * Set the value of form fields
	 *
	 * @param array $fields Form fields
	 */
	public function set_values( $fields ) {
		foreach ( $fields as $key => $args ) {
			if ( isset( $args[ 'value' ] ) ) {
				continue;
			}

			$value = $this->get_wishlist_value( $key );

			// Process certain fields differently (typically checkbox fields)
			switch ( $key ) {
				case 'exclude_from_search':
					if ( $this->wishlist && method_exists( $this->wishlist, 'is_excluded_from_search' ) ) {
						$value = filter_var( $this->wishlist->is_excluded_from_search(), FILTER_VALIDATE_BOOLEAN );
					}
					break;
			}

			$fields[ $key ][ 'value' ] = $value;
		}

		return $fields;
	}

	/**
	 * Add the plugin prefix to fields keys
	 *
	 * All field keys should have the prefix if not present
	 * Prefix is not added to fields that have $args['prefix'] set to false
	 *
	 * @param array $fields Form fields
	 */
	public function add_prefix( $fields ) {
		$prefixed = array();
		foreach ( $fields as $name => $args ) {
			if ( (isset( $args[ 'prefix' ] ) && !$args[ 'prefix' ]) || false !== strpos( $name, 'nmgr_' ) ) {
				$prefixed[ $name ] = $args;
				continue;
			}
			$prefixed[ 'nmgr_' . $name ] = $args;
		}
		return $prefixed;
	}

	/**
	 * Remove plugin prefix from supplied fields keys
	 * (This is usually necessary to prepare the fields for saving in the database)
	 *
	 * @return NMGR_Form $this
	 */
	public function remove_prefix() {
		$unprefixed = array();

		foreach ( $this->data as $name => $v ) {
			if ( !isset( $this->all_fields[ $name ] ) ||
				(isset( $this->all_fields[ $name ] ) && isset( $this->all_fields[ $name ][ 'prefix' ] ) && !$this->all_fields[ $name ][ 'prefix' ]) ) {
				$unprefixed[ $name ] = $v;
				continue;
			}

			$unprefixed[ str_replace( 'nmgr_', '', $name ) ] = $v;
		}

		$this->data = $unprefixed;
		return $this;
	}

	/**
	 * Whether there were errors in form validation
	 *
	 * @return boolean
	 */
	public function has_errors() {
		return !empty( $this->error_data );
	}

	/**
	 * Get error messages from form validation
	 *
	 * @return array Array of error messages
	 */
	public function get_error_messages() {
		$arr = [];
		foreach ( $this->error_data as $data ) {
			foreach ( $data as $msg ) {
				$arr[] = $msg;
			}
		}
		return $arr;
	}

	public function get_fields_error_messages() {
		return $this->error_data;
	}

	/**
	 * Set the current fields being worked with
	 *
	 * @param array $fields The fields to set as the current fields
	 * 									(This could be posted data from a form)
	 * @param bool $registered_only Set only fields registered in the form
	 * 												within the given fields as the current fields
	 */
	public function set_data( $fields ) {
		if ( empty( $this->all_fields ) ) {
			$this->all_fields = $this->get_fields( '', '', false );
		}

		$this->data = array_intersect_key( $fields, $this->all_fields );
		$this->sanitize();
		return $this;
	}

	/**
	 * Get the current fields being worked with
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get form fields
	 *
	 * @param string|array $fieldset Name of the fieldset to get fields for
	 * or array of field keys to get fields for. If not provided, this defaults to all fields
	 *
	 * A fieldset comprises fields which have the same $args['fieldset'] value
	 * It categories form fields into the same group.
	 *
	 * @param array $ignore Fields to ignore from the fieldset if the name of
	 * the fieldset is provided as a string or empty in $fieldset
	 *
	 * @param bool $exclude_hidden Whether to exclude fields that should not be displayed,
	 * based on the admin defined plugin settings. Default true.
	 *
	 * @param bool $prefix Whether to add prefix to the fields. Default true.
	 *
	 * @return array
	 */
	public function get_fields( $fieldset = '', $ignore = array(), $exclude_hidden = true, $prefix = true ) {
		$fields = $requested_fields = array();
		$is_gift_registry = 'gift-registry' === $this->get_type();

		$func_args = array(
			'fieldset' => $fieldset,
			'ignore' => $ignore,
			'exclude_hidden' => $exclude_hidden,
			'prefix' => $prefix,
			'form' => $this,
			'wishlist' => $this->get_wishlist()
		);

		$fields[ 'title' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'Title', 'nm-gift-registry' ) :
			__( 'Title', 'nm-gift-registry-lite' ),
			'placeholder' => sprintf(
				/* translators: %s: wishlist type title */
				nmgr()->is_pro ? __( '%s title', 'nm-gift-registry' ) : __( '%s title', 'nm-gift-registry-lite' ),
				nmgr_get_type_title( 'cf', false, $this->get_type() )
			),
			'required' => true,
			'show_in_email' => true,
			'show_in_settings' => false,
			'prefix' => false,
			'priority' => 10,
			'fieldset' => 'profile'
		);

		$fields[ 'first_name' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'First Name', 'nm-gift-registry' ) :
			__( 'First Name', 'nm-gift-registry-lite' ),
			'placeholder' => nmgr()->is_pro ?
			__( 'First name', 'nm-gift-registry' ) :
			__( 'First name', 'nm-gift-registry-lite' ),
			'autocomplete' => 'given-name',
			'show' => $is_gift_registry,
			'prefix' => false,
			'show_in_email' => true,
			'priority' => 20,
			'fieldset' => 'profile'
		);

		$fields[ 'last_name' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'Last Name', 'nm-gift-registry' ) :
			__( 'Last Name', 'nm-gift-registry-lite' ),
			'placeholder' => nmgr()->is_pro ?
			__( 'Last name', 'nm-gift-registry' ) :
			__( 'Last name', 'nm-gift-registry-lite' ),
			'autocomplete' => 'family-name',
			'show' => $is_gift_registry,
			'prefix' => false,
			'show_in_email' => true,
			'priority' => 30,
			'fieldset' => 'profile'
		);

		$fields[ 'partner_first_name' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'Partner First Name', 'nm-gift-registry' ) :
			__( 'Partner First Name', 'nm-gift-registry-lite' ),
			'placeholder' => nmgr()->is_pro ?
			__( 'Partner first name', 'nm-gift-registry' ) :
			__( 'Partner first name', 'nm-gift-registry-lite' ),
			'show_in_email' => true,
			'prefix' => false,
			'show' => $is_gift_registry,
			'priority' => 40,
			'fieldset' => 'profile',
		);

		$fields[ 'partner_last_name' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'Partner Last Name', 'nm-gift-registry' ) :
			__( 'Partner Last Name', 'nm-gift-registry-lite' ),
			'placeholder' => nmgr()->is_pro ?
			__( 'Partner last name', 'nm-gift-registry' ) :
			__( 'Partner last name', 'nm-gift-registry-lite' ),
			'show_in_email' => true,
			'prefix' => false,
			'show' => $is_gift_registry,
			'priority' => 50,
			'fieldset' => 'profile',
		);

		$fields[ 'email' ] = array(
			'type' => 'email',
			'label' => nmgr()->is_pro ?
			__( 'Email', 'nm-gift-registry' ) :
			__( 'Email', 'nm-gift-registry-lite' ),
			'placeholder' => nmgr()->is_pro ?
			__( 'Email', 'nm-gift-registry' ) :
			__( 'Email', 'nm-gift-registry-lite' ),
			'autocomplete' => 'email',
			'show_in_email' => true,
			'prefix' => false,
			'show' => $is_gift_registry,
			'priority' => 60,
			'required' => nmgr()->is_pro ? true : false,
			'validate' => array( 'email' ),
			'fieldset' => 'profile'
		);

		$fields[ 'description' ] = array(
			'type' => 'textarea',
			'label' => nmgr()->is_pro ?
			__( 'Description', 'nm-gift-registry' ) :
			__( 'Description', 'nm-gift-registry-lite' ),
			'prefix' => false,
			'show' => $is_gift_registry,
			'priority' => 70,
			'placeholder' => sprintf(
				/* translators: %s: wishlist type title */
				nmgr()->is_pro ? __( 'Describe your %s here, or write a message to your guests', 'nm-gift-registry' ) : __( 'Describe your %s here, or write a message to your guests', 'nm-gift-registry-lite' ),
				nmgr_get_type_title( '', false, $this->get_type() )
			),
			'fieldset' => 'profile'
		);

		$fields[ 'event_date' ] = array(
			'label' => nmgr()->is_pro ?
			__( 'Event Date', 'nm-gift-registry' ) :
			__( 'Event Date', 'nm-gift-registry-lite' ),
			'type' => 'nmgr-hidden',
			'validate' => array( 'date' ),
			'prefix' => false,
			'show' => $is_gift_registry,
			'show_in_email' => true,
			'priority' => 80,
			'fieldset' => 'profile',
		);

		$fields[ 'status' ] = array(
			'type' => 'nmgr-radio-group',
			'label' => nmgr()->is_pro ?
			__( 'Visibility', 'nm-gift-registry' ) :
			__( 'Visibility', 'nm-gift-registry-lite' ),
			'priority' => 90,
			'options' => array(
				'publish' => nmgr()->is_pro ?
				__( 'Public (Anyone can view it. Visible in search results to all).', 'nm-gift-registry' ) :
				__( 'Public (Anyone can view it. Visible in search results to all).', 'nm-gift-registry-lite' ),
				'private' => nmgr()->is_pro ?
				__( 'Private (Only you can view it when logged in. Visible in search results to you alone).', 'nm-gift-registry' ) :
				__( 'Private (Only you can view it when logged in. Visible in search results to you alone).', 'nm-gift-registry-lite' ),
			),
			'fieldset' => 'settings',
		);

		$fields[ 'password' ] = array(
			'label' => sprintf(
				/* translators: %s: wishlist type title */
				__( 'Password to access the %s', 'nm-gift-registry' ),
				nmgr_get_type_title( '', false, $this->get_type() )
			) . nmgr_get_help_tip( __( 'This only works if the visibility is public.', 'nm-gift-registry' ) ),
			'priority' => 100,
			'autocomplete' => 'off',
			'show_in_settings' => false,
			'fieldset' => 'settings'
		);

		$fields[ 'exclude_from_search' ] = array(
			'type' => 'nmgr-checkbox',
			'label' => nmgr()->is_pro ?
			__( 'Exclude from search results', 'nm-gift-registry' ) :
			__( 'Exclude from search results', 'nm-gift-registry-lite' ),
			'default' => '',
			'priority' => 110,
			'fieldset' => 'settings',
			'show' => $is_gift_registry,
			'checkbox_args' => [
				'show_hidden_input' => true,
			],
		);

		$fields[ 'archived' ] = array(
			'type' => 'nmgr-checkbox',
			'label' => (nmgr()->is_pro ?
			__( 'Archive', 'nm-gift-registry' ) :
			__( 'Archive', 'nm-gift-registry-lite' )
			) . nmgr_get_help_tip( sprintf(
					/* translators: %s: wishlist type title */
					nmgr()->is_pro ? __( 'Archiving the %s prevents it from being actively managed.', 'nm-gift-registry' ) : __( 'Archiving the %s prevents it from being actively managed.', 'nm-gift-registry-lite' ),
					nmgr_get_type_title( '', false, $this->get_type() )
				)
			),
			'priority' => 120,
			'default' => '',
			'show' => $is_gift_registry,
			'checkbox_args' => [
				'show_hidden_input' => true,
			],
		);

		$fields[ 'delete_wishlist' ] = array(
			'type' => 'nmgr-checkbox',
			'label' => __( 'Delete', 'nm-gift-registry' ),
			'fieldset' => 'settings',
			'priority' => 130,
			'custom_attributes' => [
				'data-notice' => sprintf(
					/* translators: %s: wishlist type title */
					__( 'Are you sure you want to delete this %s? This would also remove all associated data including items, images and messages if applicable.', 'nm-gift-registry' ),
					nmgr_get_type_title( '', false, $this->get_type() )
				),
			]
		);

		if ( $this->wishlist && $this->wishlist->get_total_purchased_amount() ) {
			$fields[ 'delete_wishlist' ][ 'custom_attributes' ][ 'disabled' ] = 1;
			$fields[ 'delete_wishlist' ][ 'description' ] = 'hello';
		}

		$fields[ 'wishlist_id' ] = array(
			'type' => 'nmgr-hidden',
			'fieldset' => 'core',
			'value' => $this->wishlist->get_id(),
		);

		$fields[ 'user_id' ] = array(
			'type' => 'nmgr-hidden',
			'prefix' => false,
			'fieldset' => 'core',
			'value' => $this->wishlist->get_user_id() ? $this->wishlist->get_user_id() : nmgr_get_current_user_id(),
		);

		$fields[ 'type' ] = array(
			'type' => 'nmgr-hidden',
			'prefix' => false,
			'fieldset' => 'core',
			'value' => $this->get_type(),
		);

		// @todo: Maybe delete. This may not be used
		$fields[ 'background_image_id' ] = array(
			'type' => 'nmgr-hidden',
			'prefix' => false,
			'fieldset' => 'image',
		);

		// Get shipping fields
		$fields = array_merge( $fields, ( array ) $this->get_shipping_fields() );

		// Filter all fields
		$fields = apply_filters( 'nmgr_fields', $fields, $func_args );

		// Set default field values
		$fields = $this->set_defaults( $fields );

		// Set field values from the stored data for the wishlist
		$fields = $this->set_values( $fields );

		$default_classes = [ 'nmgr-field', 'nmgr-validate' ];

		foreach ( $fields as $key => $args ) {
			$fields[ $key ][ 'class' ] = array_merge( ( array ) ($fields[ $key ][ 'class' ] ?? []), $default_classes );

			// Set required attribute for profile fields based on default settings and plugin settings
			if ( $is_gift_registry && ('profile' === ($args[ 'fieldset' ] ?? false)) &&
				false !== ($args[ 'show_in_settings' ] ?? true) ) {
				$required = isset( $args[ 'required' ] ) && $args[ 'required' ] ? 'required' : false;
				$fields[ $key ][ 'required' ] = ( bool ) ( 'required' === nmgr_get_type_option( $this->get_type(), "display_form_{$key}", $required ));
			}
		}

		// Get the requested fields, or all fields if no specific group is requested
		if ( $fieldset ) {
			if ( is_string( $fieldset ) ) {
				foreach ( $fields as $key => $args ) {
					if ( isset( $args[ 'fieldset' ] ) && ($args[ 'fieldset' ] === $fieldset) && !in_array( $key, ( array ) $ignore ) ) {
						$requested_fields[ $key ] = $args;
					}
				}
			} elseif ( is_array( $fieldset ) ) {
				foreach ( $fields as $key => $args ) {
					if ( in_array( $key, $fieldset ) ) {
						$requested_fields[ $key ] = $args;
					}
				}
			}
		} else {
			$requested_fields = array_diff_key( $fields, array_flip( ( array ) $ignore ) );
		}

		// Exclude hidden fields
		if ( $exclude_hidden && $is_gift_registry ) {
			foreach ( array_keys( $requested_fields ) as $key ) {
				if ( 'no' === nmgr_get_type_option( $this->get_type(), "display_form_{$key}", 'yes' ) ) {
					unset( $requested_fields[ $key ] );
				}

				// Hide password field if visibility field is hidden
				if ( 'password' === $key &&
					'no' === nmgr_get_type_option( $this->get_type(), 'display_form_status', 'yes' ) ) {
					unset( $requested_fields[ $key ] );
				}
			}
		}

		/**
		 * Add datepicker field for event_date if present
		 */
		if ( isset( $requested_fields[ 'event_date' ] ) ) {
			$event_date_display_field = [
				'label' => nmgr()->is_pro ?
				__( 'Event Date', 'nm-gift-registry' ) :
				__( 'Event Date', 'nm-gift-registry-lite' ),
				'autocomplete' => 'off',
				'custom_attributes' => array(
					'autocomplete' => 'off',
					'data-datepicker-alt-field' => '#event_date',
				),
				'show' => $is_gift_registry,
				'show_in_settings' => false,
				'prefix' => false,
				'fieldset' => 'profile',
				'priority' => $requested_fields[ 'event_date' ][ 'priority' ] ?? 0,
				'input_class' => array( 'nmgr-use-datepicker' ),
				'value' => nmgr_format_date( $this->wishlist->get_event_date() ),
			];

			if ( isset( $requested_fields[ 'event_date' ][ 'required' ] ) ) {
				$event_date_display_field[ 'required' ] = $requested_fields[ 'event_date' ][ 'required' ];
			}

			$event_date_display = [ 'event_date_display' => $event_date_display_field ];

			$pos = array_search( 'event_date', array_keys( $requested_fields ) );
			$requested_fields = array_merge(
				array_slice( $requested_fields, 0, $pos ),
				$event_date_display,
				array_slice( $requested_fields, $pos )
			);
		}

		if ( $prefix ) {
			// Add plugin prefix to all fields except fields with $args['prefix'] set to false
			$requested_fields = $this->add_prefix( $requested_fields );
		}

		$prepared_fields = apply_filters( 'nmgr_requested_fields', $requested_fields, $func_args );

		/**
		 * Enforce 'required' attribute for title field
		 * Title field is used to save the wishlist title in database as wordpress post title
		 */
		if ( isset( $prepared_fields[ 'title' ] ) ) {
			$prepared_fields[ 'title' ][ 'required' ] = true;
		}

		$this->fields_count = count( $prepared_fields );

		if ( !empty( $prepared_fields ) ) {
			Fields::sort_by_priority( $prepared_fields );
			$prepared_fields = Fields::get_elements_to_show( $prepared_fields );
		}

		return $prepared_fields;
	}

	/**
	 * Get form html for specified fields
	 *
	 * @param string|array $fieldset The name of the field group to get form html for
	 * or numeric array of field keys to get form html for or associative array of field keys
	 * and field values to get form html for. If not provided, this defaults to all fields.
	 *
	 * @param string $title The title to use to categorise this form fieldset. Default none
	 *
	 * @param bool $wrapper Whether to wrap the fieldset with opening and closing div tags
	 *
	 * @param bool $exclude_hidden Whether to exclude fields that should not be displayed,
	 * based on the admin defined plugin settings. Default true.
	 */
	public function get_fields_html( $fieldset = '', $ignore = array(), $title = '', $wrapper = true, $exclude_hidden = true ) {
		if ( is_array( $fieldset ) && !is_int( key( $fieldset ) ) ) {
			$fields = $fieldset;
			$this->fields_count = count( $fields );
		} else {
			$fields = $this->get_fields( $fieldset, $ignore, $exclude_hidden );
		}

		$class = is_string( $fieldset ) ? $fieldset . '-' : '';
		$is_admin = is_nmgr_admin();

		if ( !$this->fields_count ) {
			return;
		}

		ob_start();
		echo $wrapper ? wp_kses_post( "<div class='form-group fieldset nmgr-{$class}fields'>" ) : '';

		if ( apply_filters( 'nmgr_fields_title', $title, $fieldset ) ) {
			printf( wp_kses_post( "<h3 class='fieldset-title nmgr-{$class}fields-title'>%s</h3>" ), esc_html( $title ) );
		}

		foreach ( $fields as $name => $args ) {
			if ( $is_admin ) {
				/**
				 * We're not using woocommerce_form_field to compose the fields in the admin area in order to
				 * prevent conflicts because it is typically used for checkout fields on the frontend. So we want to
				 * modify each field used in the admin area so that it can be used with the woocommerce_wp_text e.t.c.
				 * functions instead (@see wc-meta-box-functions.php), which are the functions used by woocommerce
				 * to compose shipping and billing fields in the order screen.
				 */
				$args[ 'type' ] = isset( $args[ 'type' ] ) ? $args[ 'type' ] : 'text';
				$args[ 'id' ] = isset( $args[ 'id' ] ) ? $args[ 'id' ] : $name;
				$args[ 'label' ] = isset( $args[ 'label' ] ) ? $args[ 'label' ] : '';
				$args[ 'wrapper_class' ] = 'form-row ' . (isset( $args[ 'class' ] ) ? implode( ' ', ( array ) $args[ 'class' ] ) : '');
				$args[ 'class' ] = isset( $args[ 'input_class' ] ) ? implode( ' ', $args[ 'input_class' ] ) : '';

				if ( ($args[ 'required' ] ?? false ) ) {
					$required_text = nmgr()->is_pro ?
						__( 'required', 'nm-gift-registry' ) :
						__( 'required', 'nm-gift-registry-lite' );
					$args[ 'label' ] = $args[ 'label' ] . '&nbsp;<abbr class="required" title="' . esc_attr( $required_text ) . '">*</abbr>';
				}

				switch ( $name ) {
					case 'shipping_country':
						$shipping_countries = is_a( wc()->countries, 'WC_Countries' ) ? WC()->countries->get_shipping_countries() : array();
						$args[ 'type' ] = 'select';
						$args[ 'class' ] = 'js_field-country select short';
						$args[ 'options' ] = array( '' => nmgr()->is_pro ?
							__( 'Select a country&hellip;', 'nm-gift-registry' ) :
							__( 'Select a country&hellip;', 'nm-gift-registry-lite' ) ) + $shipping_countries;
						break;

					case 'shipping_state':
						$args[ 'class' ] = 'js_field-state select short';
						$args[ 'label' ] = nmgr()->is_pro ?
							__( 'State / County', 'nm-gift-registry' ) :
							__( 'State / County', 'nm-gift-registry-lite' );
						break;

					default:
						break;
				}

				ob_start();

				switch ( $args[ 'type' ] ) {
					case 'select':
						$args[ 'style' ] = 'width:100%;max-width:100%!important';
						woocommerce_wp_select( $args );
						break;
					case 'textarea':
						woocommerce_wp_textarea_input( $args );
						break;
					case 'checkbox':
						woocommerce_wp_checkbox( $args );
						break;
					case 'nmgr-checkbox':
						echo self::create_custom_checkbox_switch( $name, $args, $args[ 'value' ] );
						break;
					case 'radio':
						woocommerce_wp_radio( $args );
						break;
					case 'hidden':
					case 'nmgr-hidden':
						woocommerce_wp_hidden_input( $args );
						break;
					default:
						woocommerce_wp_text_input( $args );
						break;
				}

				$input = ob_get_clean();
				/**
				 *  Set the id on the input wrapper to mimic woocommerce_form_fields field wrapper so that
				 * the fields can be targetted in js script. This is only necessary for shipping fields which
				 * need to be toggled based on the country selected.
				 */
				echo str_replace( '<p', '<p id="' . esc_attr( $args[ 'id' ] ) . '_field' . '"', $input );
			} else {
				woocommerce_form_field( $name, $args, $args[ 'value' ] );
			}
		}

		echo $wrapper ? '</div>' : '';
		return ob_get_clean();
	}

	/**
	 * Get shipping fields for wishlist
	 *
	 * Uses the same shipping fields woocommerce uses
	 *
	 * Plugin prefix is not added to shipping fields because we want woocommerce
	 * 'wc-country-select' script to be able to manipulate the fields. So we have to
	 * process these fields specially after posting.
	 *
	 * @return array
	 */
	public function get_shipping_fields( $country = null ) {
		if ( !function_exists( 'wc' ) || !is_a( wc()->countries, 'WC_Countries' ) ) {
			return array();
		}

		$shipping_country = $country ?? $this->get_wishlist()->get_shipping_country();
		$fields = wc()->countries->get_address_fields( $shipping_country, 'shipping_' );

		$modified_fields = array_map( function ( $field, $key ) {

			// Get the wishlist's value for this field
			$value = $this->get_wishlist_value( $key );

			// Add field to the 'shipping' fieldset
			$field[ 'fieldset' ] = 'shipping';

			// Do not prefix field to allow woocommerce js work on it if necessary
			$field[ 'prefix' ] = false;

			// Set the field value as the wishlist's value
			$field[ 'default' ] = $value;

			if ( !nmgr_get_option( 'shipping_address_required' ) ) {
				$field[ 'required' ] = '';
			}

			return $field;
		}, $fields, array_keys( $fields ) );

		return array_combine( array_keys( $fields ), $modified_fields );
	}

	/**
	 * This submit button html group is an exact copy of that on woocommerce's form-edit-account.php template
	 * It is used to give consistency with woocommerce's own html.
	 */
	public function get_submit_button( $value = '' ) {
		$button = sprintf(
			'<button type="submit" class="save-action button" name="nmgr_save" value="%s">%s</button>',
			$value,
			nmgr()->is_pro ?
			__( 'Save changes', 'nm-gift-registry' ) :
			__( 'Save changes', 'nm-gift-registry-lite' )
		);

		if ( $value ) {
			$button .= '<input type="hidden" name="nmgr_save" value="' . $value . '">';
		}

		return $button;
	}

	/**
	 * Get a common nonce field for the form
	 *
	 * @return string Nonce hidden input
	 */
	public function get_nonce_field() {
		return wp_nonce_field( 'nmgr_form', self::get_nonce_key(), true, false );
	}

	public static function get_nonce_key() {
		return 'nmgr_form-nonce';
	}

	/**
	 * Get standard hidden fields to be used by all form instances
	 *
	 * @return html
	 */
	public function get_hidden_fields() {
		// Always add the wishlist id and user id fields
		$fields = $this->get_fields_html( 'core', '', '', false );
		$fields .= $this->get_nonce_field();

		return apply_filters( 'nmgr_hidden_fields', $fields, $this );
	}

	/**
	 * Verify the form nonce
	 *
	 * @param array $request Array to check in for existing nonce key or $_REQUEST if not supplied
	 * @return false|int False if the nonce is invalid, 1 if the nonce is valid and generated between
	 *                   0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
	 */
	public static function verify_nonce( $request = '' ) {
		$request = $request ? $request : $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
		$nonce_key = self::get_nonce_key();
		return isset( $request[ $nonce_key ] ) ?
			wp_verify_nonce( sanitize_key( $request[ $nonce_key ] ), 'nmgr_form' ) :
			false;
	}

	/**
	 * @return array Sanitized plugin prefixed posted data
	 */
	public function sanitize() {
		$unsanitized_data = $this->data;

		foreach ( array_keys( $this->all_fields ) as $key ) {
			if ( isset( $this->data[ $key ] ) ) {

				// get field types to sanitize
				$type = isset( $this->all_fields[ $key ][ 'type' ] ) ? $this->all_fields[ $key ][ 'type' ] : 'text';

				switch ( $type ) {
					case 'textarea':
						$this->data[ $key ] = sanitize_textarea_field( wp_unslash( $this->data[ $key ] ) );
						break;
					case 'password':
						$this->data[ $key ] = wp_unslash( $this->data[ $key ] );
						break;
					default:
						$this->data[ $key ] = sanitize_text_field( wp_unslash( $this->data[ $key ] ) );
						break;
				}

				$this->data[ $key ] = apply_filters( 'nmgr_sanitize_' . $type . '_field',
					apply_filters( 'nmgr_sanitize_field_' . $key, $this->data[ $key ], $this->data, $unsanitized_data ), $key );
			}
		}
		return $this;
	}

	public function get_error_fields() {
		return array_keys( $this->error_data );
	}

	public function add_error_message( $key, $message ) {
		$this->error_data[ $key ][] = $message;
	}

	public function validate() {
		$shipping_fields = array();

		/**
		 * Merge posted shipping fields with all fields if the shipping country is posted
		 * so that we can validate shipping fields properly
		 */
		if ( isset( $this->data[ 'shipping_country' ] ) ) {
			$shipping_fields = $this->get_shipping_fields( $this->data[ 'shipping_country' ] );
			$this->all_fields = array_merge( $this->all_fields, $shipping_fields );
		}

		foreach ( $this->all_fields as $key => $field ) {
			if ( !isset( $this->data[ $key ] ) ||
				('event_date' === $key && isset( $this->data[ 'event_date_display' ] )) ) {
				continue;
			}

			$field_label = isset( $field[ 'label' ] ) ?
				in_array( $key, array_keys( $shipping_fields ) ) ?
				sprintf(
					/* translators: %s: shipping field label */
					nmgr()->is_pro ? __( 'Shipping %s', 'nm-gift-registry' ) : __( 'Shipping %s', 'nm-gift-registry-lite' ),
					$field[ 'label' ]
				) :
				$field[ 'label' ] :
				'';

			$field_value = $this->data[ $key ];

			// Validate title field for profile form
			if ( 'title' === $key && in_array( strtolower( $field_value ), nmgr_get_base_actions() ) ) {
				$this->add_error_message( $key, ' x ' . $field_value );
			}

			// Validate required fields
			if ( isset( $field[ 'required' ] ) && !empty( $field[ 'required' ] ) && empty( $field_value ) ) {
				$this->add_error_message( $key, sprintf(
						/* translators: %s: shipping field label */
						nmgr()->is_pro ? __( '%s is a required field.', 'nm-gift-registry' ) : __( '%s is a required field.', 'nm-gift-registry-lite' ),
						'<strong>' . esc_html( $field_label ) . '</strong>'
					) );
			}

			if ( !empty( $field_value ) ) {
				if ( isset( $field[ 'validate' ] ) && !empty( $field[ 'validate' ] ) ) {
					foreach ( ( array ) $field[ 'validate' ] as $rule ) {
						switch ( $rule ) {
							case 'email':
								if ( !is_email( $field_value ) ) {
									$this->add_error_message( $key,
										sprintf(
											/* translators: %s: supplied email address */
											nmgr()->is_pro ? __( '%s is not a valid email address.', 'nm-gift-registry' ) : __( '%s is not a valid email address.', 'nm-gift-registry-lite' ),
											'<strong>' . esc_html( $field_label ) . '</strong>'
										)
									);
								}
								break;
							case 'postcode':
								$country = $this->data[ 'shipping_country' ] ? $this->data[ 'shipping_country' ] : $this->wishlist->get_shipping_country();
								$value = wc_format_postcode( $field_value, $country );

								if ( '' !== $value && !WC_Validation::is_postcode( $value, $country ) ) {
									$this->add_error_message( $key,
										nmgr()->is_pro ?
											__( 'Please enter a valid postcode / ZIP.', 'nm-gift-registry' ) :
											__( 'Please enter a valid postcode / ZIP.', 'nm-gift-registry-lite' )
									);
								}
								break;
							case 'state':
								$country = $this->data[ 'shipping_country' ] ? $this->data[ 'shipping_country' ] : $this->wishlist->get_shipping_country();
								$valid_states = is_a( wc()->countries, 'WC_Countries' ) ? WC()->countries->get_states( $country ) : array();

								if ( !empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
									$valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
									$field_value = wc_strtoupper( $field_value );

									if ( isset( $valid_state_values[ $field_value ] ) ) {
										$field_value = $valid_state_values[ $field_value ];
									}

									if ( !in_array( $field_value, $valid_state_values, true ) ) {
										$this->add_error_message( $key, sprintf(
												/* translators: %1$s: supplied state, %2$s: valid states */
												nmgr()->is_pro ? __( '%1$s is not valid. Please enter one of the following: %2$s', 'nm-gift-registry' ) : __( '%1$s is not valid. Please enter one of the following: %2$s', 'nm-gift-registry-lite' ),
												'<strong>' . esc_html( $field_label ) . '</strong>', implode( ', ', $valid_states )
											) );
									}
								}
								break;
							case 'date':
								if ( !nmgr_get_datetime( $field_value ) ) {
									$this->add_error_message( $key, sprintf(
											/* translators: %s: field label */
											nmgr()->is_pro ? __( '%s - Please enter the date in a valid format.', 'nm-gift-registry' ) : __( '%s - Please enter the date in a valid format.', 'nm-gift-registry-lite' ),
											'<strong>' . esc_html( $field_label ) . '</strong>'
										) );
								}
								break;
						}
					}
				}
			}
		}

		/**
		 * Allow others to validate posted data
		 *
		 * @param array $data Posted data
		 * @param array $fields Form fields
		 * @param NMGR_Form $this Form Object
		 */
		do_action( 'nmgr_validate_fields', $this->data, $this->all_fields, $this );

		return $this;
	}

	/**
	 * Utility function to save form fields to wishlist
	 *
	 * @return in Wishlist id on successful save
	 */
	public function save() {
		// Remove prefix from posted data so that we can save
		$this->remove_prefix();

		foreach ( $this->data as $key => $value ) {
			$this->wishlist->set_prop( $key, $value );
		}

		return $this->wishlist->save();
	}

}
