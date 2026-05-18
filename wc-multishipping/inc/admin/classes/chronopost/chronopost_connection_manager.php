<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_interface;
use WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_soap;
use WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt;

class chronopost_connection_manager {
	
	private static $instance = null;
	
	private $auth_handler = null;
	
	private function __construct() {
	}
	
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public function get_auth_handler() {
		if ( $this->auth_handler === null ) {
			$connection_type = self::get_default_connection_type();
			
			if ( $connection_type === 'jwt' ) {
				$this->auth_handler = new chronopost_auth_jwt();
			} else {
				$this->auth_handler = new chronopost_auth_soap();
			}
		}
		
		return $this->auth_handler;
	}
	
	public function is_pro_mode() {
		return self::get_default_connection_type() === 'jwt';
	}
	
	public function is_soap_mode() {
		return self::get_default_connection_type() === 'soap';
	}
	
	public function get_connection_type() {
		return self::get_default_connection_type();
	}
	
	public function set_connection_type( $type ) {
		if ( ! in_array( $type, [ 'soap', 'jwt' ] ) ) {
			return false;
		}
		
		update_option( 'wms_chronopost_connection_type', $type );
		$this->auth_handler = null;
		
		return true;
	}
	
	public function is_authenticated() {
		$auth = $this->get_auth_handler();
		return $auth->is_authenticated();
	}

	public static function get_default_connection_type() {
		$saved_connection_type = get_option( 'wms_chronopost_connection_type', '' );
		if ( in_array( $saved_connection_type, [ 'soap', 'jwt' ], true ) ) {
			return $saved_connection_type;
		}

		$auth_jwt = new chronopost_auth_jwt();
		if ( $auth_jwt->is_enrollment_complete() && ! $auth_jwt->is_refresh_token_expired() ) {
			return 'jwt';
		}

		$has_account_number = trim( (string) get_option( 'wms_chronopost_account_number', '' ) ) !== '';
		$has_account_password = trim( (string) get_option( 'wms_chronopost_account_password', '' ) ) !== '';

		return $has_account_number && $has_account_password ? 'soap' : 'jwt';
	}
}
