<?php

namespace WCMultiShipping\inc\admin\classes\customer;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_customer_registration {

	const OPTION_INSTALLATION_REGISTERED = 'wms_customer_installation_registered';
	const OPTION_CUSTOMER_EMAIL = 'wms_customer_email';

	public static function register_hooks() {
		add_action( 'admin_init', [ __CLASS__, 'redirect_to_config' ] );
		add_action( 'admin_post_wms_save_customer_email', [ __CLASS__, 'save_customer_email' ] );
	}

	public static function is_fresh_new_install() {
		$mondial_relay_customer_code = get_option( 'wms_mondial_relay_customer_code', '' );
		$mondial_relay_private_key = get_option( 'wms_mondial_relay_private_key', '' );
		$mondial_relay_configured = ! empty( $mondial_relay_customer_code ) && ! empty( $mondial_relay_private_key );

		$chronopost_account_number = get_option( 'wms_chronopost_account_number', '' );
		$chronopost_account_password = get_option( 'wms_chronopost_account_password', '' );
		$chronopost_configured = ! empty( $chronopost_account_number ) && ! empty( $chronopost_account_password );

		return ! $mondial_relay_configured && ! $chronopost_configured;
	}

	public static function redirect_to_config() {
		if ( get_option( self::OPTION_INSTALLATION_REGISTERED ) ) {
			return;
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-multishipping' ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( get_transient( 'wms_activation_redirect' ) ) {
			delete_transient( 'wms_activation_redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=wc-multishipping' ) );
			exit;
		}
	}

	public static function save_customer_email() {
		if ( ! isset( $_POST['wms_customer_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wms_customer_email_nonce'] ) ), 'wms_customer_email_action' ) ) {
			wp_die( __( 'Erreur de sécurité', 'wc-multishipping' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permissions insuffisantes', 'wc-multishipping' ) );
		}

		$email = isset( $_POST['wms_email'] ) ? sanitize_email( $_POST['wms_email'] ) : '';
		if ( ! empty( $email ) && is_email( $email ) ) {
			update_option( self::OPTION_CUSTOMER_EMAIL, $email );
			
			self::send_registration_to_api( $email );
		}

		update_option( self::OPTION_INSTALLATION_REGISTERED, true );

		wp_safe_redirect( admin_url( 'admin.php?page=wc-multishipping' ) );
		exit;
	}

	public static function send_to_api( $data ) {
		$security_token = 'GmYCImJAK61qWO8WVDa64AK7IaXghfAorZtS-RpW8';
		
		$api_url = 'http://www.wcmultishipping.com/api/webhook/registration';
		
		if ( ! isset( $data['site_url'] ) ) {
			$data['site_url'] = get_site_url();
		}
		
		$args = array(
			'body' => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-WMS-Security-Token' => $security_token,
			),
			'timeout' => 15,
			'sslverify' => true,
		);
		
		wp_remote_post( $api_url, $args );
		
	}

	private static function send_registration_to_api( $email ) {
		self::send_to_api(  array(
			'email' => $email,
			'is_fresh_new_install' => self::is_fresh_new_install(),
		) );
	}
}
