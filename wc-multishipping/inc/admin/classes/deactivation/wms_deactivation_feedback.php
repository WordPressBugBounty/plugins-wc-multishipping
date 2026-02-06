<?php

namespace WCMultiShipping\inc\admin\classes\deactivation;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_deactivation_feedback {

	public static function register_hooks() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wms_send_deactivation_feedback', [ __CLASS__, 'send_feedback' ] );
	}

	public static function enqueue_scripts( $hook ) {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wms-deactivation-feedback',
			WMS_ADMIN_CSS_URL . 'deactivation-feedback.min.css',
			[],
			WMS_VERSION
		);

		wp_enqueue_script(
			'wms-deactivation-feedback',
			WMS_ADMIN_JS_URL . 'deactivation-feedback.js',
			[ 'jquery' ],
			WMS_VERSION,
			true
		);

		wp_localize_script(
			'wms-deactivation-feedback',
			'wmsDeactivationFeedback',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wms_deactivation_feedback' ),
				'plugin_slug' => 'wc-multishipping/wc-multishipping.php',
				'strings' => [
					'title' => esc_html__( 'We value your feedback', 'wc-multishipping' ),
					'description' => esc_html__( 'Please let us know why you are deactivating WCMultiShipping:', 'wc-multishipping' ),
					'reason_1' => esc_html__( 'I no longer need the plugin', 'wc-multishipping' ),
					'reason_2' => esc_html__( 'I found a better plugin', 'wc-multishipping' ),
					'reason_3' => esc_html__( 'The plugin is not working as expected', 'wc-multishipping' ),
					'reason_4' => esc_html__( 'The plugin broke my site', 'wc-multishipping' ),
					'reason_5' => esc_html__( 'This is a temporary deactivation', 'wc-multishipping' ),
					'reason_6' => esc_html__( 'Other', 'wc-multishipping' ),
					'placeholder' => esc_html__( 'Please share more details (optional)', 'wc-multishipping' ),
					'submit' => esc_html__( 'Submit & Deactivate', 'wc-multishipping' ),
					'skip' => esc_html__( 'Skip', 'wc-multishipping' ),
					'cancel' => esc_html__( 'Cancel', 'wc-multishipping' ),
				]
			]
		);
	}

	public static function send_feedback() {
		check_ajax_referer( 'wms_deactivation_feedback', 'nonce' );

		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( $_POST['details'] ) : '';

		$api_key = get_option( 'wms_api_key', '' );
		$customer_email = get_option( 'wms_customer_email', '' );

		$data = [
			'reason' => $reason,
			'details' => $details,
			'site_url' => get_site_url(),
			'plugin_version' => WMS_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
			'timestamp' => current_time( 'mysql' ),
			'wms_api_key' => $api_key,
			'email' => $customer_email,
		];

		$security_token = 'GmYCImJAK61qWO8WVDa64AK7IaXghfAorZtS-RpW8';
		

		$response = wp_remote_post(
			'https://www.wcmultishipping.com/api/webhook/feedback',
			[
				'method' => 'POST',
				'timeout' => 15,
				'headers' => [
					'Content-Type' => 'application/json',
					'X-WMS-Security-Token' => $security_token,
				],
				'body' => wp_json_encode( $data ),
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Unable to send feedback. You can still deactivate the plugin.', 'wc-multishipping' )
			] );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		
		if ( $response_code >= 200 && $response_code < 300 ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Thank you for your feedback!', 'wc-multishipping' )
			] );
		} else {
			wp_send_json_error( [
				'message' => esc_html__( 'Unable to send feedback. You can still deactivate the plugin.', 'wc-multishipping' )
			] );
		}
	}
}
