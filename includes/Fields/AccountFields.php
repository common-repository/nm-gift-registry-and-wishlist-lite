<?php

namespace NMGR\Fields;

use NMGR\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class AccountFields extends Fields {

	protected $id = 'account';

	/**
	 * @var \NMGR_Account
	 */
	public $account;

	public function __construct( $account ) {
		$this->account = $account;
		$this->set_data( $this->data() );
		$this->filter_by_priority();
		$this->filter_showing();
	}

	protected function data() {
		$sections = [
			'profile' => [
				'title' => nmgr()->is_pro ?
				__( 'Profile', 'nm-gift-registry' ) :
				__( 'Profile', 'nm-gift-registry-lite' ),
				'priority' => 20,
				'content' => [ $this->account, 'get_profile_section' ],
				'show_for_user_only' => true,
			],
			'items' => [
				'title' => nmgr()->is_pro ?
				__( 'Items', 'nm-gift-registry' ) :
				__( 'Items', 'nm-gift-registry-lite' ),
				'priority' => 30,
				'content' => [ $this->account, 'get_items_section' ],
			],
			'shipping' => [
				'title' => nmgr()->is_pro ?
				__( 'Shipping', 'nm-gift-registry' ) :
				__( 'Shipping', 'nm-gift-registry-lite' ),
				'priority' => 50,
				'content' => [ $this->account, 'get_shipping_section' ],
				'show' => $this->account->is_gift_registry() && nmgr_get_option( 'enable_shipping' ),
				'show_for_user_only' => true,
			],
		];

		return $sections;
	}

}
