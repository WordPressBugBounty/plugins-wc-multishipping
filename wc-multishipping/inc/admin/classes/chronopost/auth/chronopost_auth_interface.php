<?php

namespace WCMultiShipping\inc\admin\classes\chronopost\auth;

interface chronopost_auth_interface {
	
	public function authenticate();
	
	public function is_authenticated();
	
	public function get_credentials();
	
	public function revoke_authentication();
	
	public function get_auth_type();
}
