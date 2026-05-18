<?php

namespace WCMultiShipping\inc\admin\classes\chronopost\auth;

class chronopost_auth_soap implements chronopost_auth_interface {
	
	public function authenticate() {
		$account_number = get_option( 'wms_chronopost_account_number', '' );
		$account_password = get_option( 'wms_chronopost_account_password', '' );
		
		if ( empty( $account_number ) || empty( $account_password ) ) {
			return false;
		}
		
		return true;
	}
	
	public function is_authenticated() {
		$account_number = get_option( 'wms_chronopost_account_number', '' );
		$account_password = get_option( 'wms_chronopost_account_password', '' );
		
		return ! empty( $account_number ) && ! empty( $account_password );
	}
	
	public function get_credentials() {
		return [
			'account_number' => get_option( 'wms_chronopost_account_number', '' ),
			'password' => get_option( 'wms_chronopost_account_password', '' ),
			'subaccount' => get_option( 'wms_chronopost_subaccount_number', '' ),
		];
	}
	
	public function revoke_authentication() {
		delete_option( 'wms_chronopost_account_number' );
		delete_option( 'wms_chronopost_account_password' );
		delete_option( 'wms_chronopost_subaccount_number' );
		
		return true;
	}
	
	public function get_auth_type() {
		return 'soap';
	}
}
