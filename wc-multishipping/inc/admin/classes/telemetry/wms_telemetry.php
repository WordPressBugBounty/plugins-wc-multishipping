<?php

namespace WCMultiShipping\inc\admin\classes\telemetry;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_telemetry {
	const OPTION_ENABLED = 'wms_telemetry_enabled';
	const OPTION_INSTALL_ID = 'wms_telemetry_install_id';
	const OPTION_QUEUE = 'wms_telemetry_queue';
	const OPTION_SEEN_EVENTS = 'wms_telemetry_seen_events';
	const ENDPOINT = 'https://www.wcmultishipping.com/api/webhook/plugin-telemetry';
	const SECURITY_TOKEN = 'GmYCImJAK61qWO8WVDa64AK7IaXghfAorZtS-RpW8';
	const SCHEMA_VERSION = 1;
	const MAX_QUEUE_SIZE = 30;
	const MAX_ATTEMPTS = 2;
	const MAX_RETRY_PER_REQUEST = 1;
	const HTTP_TIMEOUT = 1.5;

	private static $is_flushing = false;

	private static $allowed_events = [
		'plugin_onboarding_started',
		'plugin_onboarding_step_viewed',
		'plugin_onboarding_step_completed',
		'plugin_onboarding_completed',
		'plugin_onboarding_dismissed',
		'carrier_config_saved',
		'carrier_credentials_tested',
		'shipping_rate_preview_run',
		'shipping_rate_preview_result',
		'shipping_rate_error_seen',
		'plugin_settings_saved',
	];

	private static $allowed_properties = [
		'carrier',
		'step',
		'result',
		'error_category',
		'configured_carriers_count',
		'is_pro_known',
	];

	public static function register_hooks() {
		add_action( 'admin_post_wms_save_telemetry_settings', [ __CLASS__, 'handle_save_settings' ] );
		self::register_retry_hooks();
	}

	public static function register_retry_hooks() {
		add_action( 'admin_init', [ __CLASS__, 'flush_queue' ] );
	}

	public static function is_enabled() {
		return true;
	}

	public static function has_saved_preference() {
		return false !== get_option( self::OPTION_ENABLED, false );
	}

	public static function set_enabled( $enabled ) {
		update_option( self::OPTION_ENABLED, 'yes', false );
	}

	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage WCMultiShipping settings.', 'wc-multishipping' ) );
		}

		check_admin_referer( 'wms_telemetry_settings', 'wms_telemetry_nonce' );

		self::set_enabled( true );
		self::track( 'plugin_settings_saved', [ 'result' => 'telemetry_enabled' ] );

		wms_enqueue_message( __( 'Telemetry preference saved.', 'wc-multishipping' ), 'success' );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-multishipping&view=dashboard' ) );
		exit;
	}

	public static function get_install_id() {
		$install_id = (string) get_option( self::OPTION_INSTALL_ID, '' );

		if ( '' === $install_id ) {
			$install_id = str_replace( '-', '', wp_generate_uuid4() );
			update_option( self::OPTION_INSTALL_ID, $install_id, false );
		}

		return $install_id;
	}

	public static function track_once( $event, $dedupe_key, $properties = [] ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$seen = get_option( self::OPTION_SEEN_EVENTS, [] );
		if ( ! is_array( $seen ) ) {
			$seen = [];
		}

		$key = sanitize_key( $event . '_' . $dedupe_key );
		if ( isset( $seen[ $key ] ) ) {
			return;
		}

		$seen[ $key ] = current_time( 'mysql' );
		update_option( self::OPTION_SEEN_EVENTS, $seen, false );

		self::track( $event, $properties );
	}

	public static function track( $event, $properties = [] ) {
		if ( ! self::is_enabled() || ! in_array( $event, self::$allowed_events, true ) ) {
			return;
		}

		$payload = [
			'schema_version' => self::SCHEMA_VERSION,
			'event' => $event,
			'anonymous_install_id' => self::get_install_id(),
			'context' => self::get_context(),
			'properties' => self::sanitize_properties( $properties ),
		];

		self::flush_queue( self::MAX_RETRY_PER_REQUEST );

		if ( ! self::send_payload( $payload ) ) {
			self::enqueue( $payload, 1 );
		}
	}

	public static function flush_queue( $limit = self::MAX_RETRY_PER_REQUEST ) {
		if ( self::$is_flushing ) {
			return;
		}

		$queue = self::get_queue();
		if ( empty( $queue ) ) {
			return;
		}

		$remaining = [];
		$sent_count = 0;
		self::$is_flushing = true;

		foreach ( $queue as $entry ) {
			$payload = self::sanitize_payload( $entry['payload'] ?? [] );
			$attempts = (int) ( $entry['attempts'] ?? 0 );

			if ( null !== $limit && $sent_count >= $limit ) {
				$remaining[] = [
					'payload' => $payload,
					'attempts' => $attempts,
				];
				continue;
			}

			$sent_count++;
			if ( self::send_payload( $payload ) ) {
				continue;
			}

			$attempts++;
			if ( $attempts < self::MAX_ATTEMPTS ) {
				$remaining[] = [
					'payload' => $payload,
					'attempts' => $attempts,
				];
			}
		}

		update_option( self::OPTION_QUEUE, array_slice( $remaining, -self::MAX_QUEUE_SIZE ), false );
		self::$is_flushing = false;
	}

	private static function send_payload( $payload ) {
		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'body' => wp_json_encode( self::sanitize_payload( $payload ) ),
				'headers' => [
					'Content-Type' => 'application/json',
					'X-WMS-Security-Token' => self::SECURITY_TOKEN,
				],
				'timeout' => self::HTTP_TIMEOUT,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		return $response_code >= 200 && $response_code < 300;
	}

	private static function sanitize_payload( $payload ) {
		$payload = is_array( $payload ) ? $payload : [];

		unset( $payload['license_key'] );

		return $payload;
	}

	private static function get_context() {
		return [
			'plugin_version' => defined( 'WMS_VERSION' ) ? WMS_VERSION : '',
			'wp_version_major' => self::major_version( get_bloginfo( 'version' ) ),
			'wc_version_major' => self::major_version( defined( 'WC_VERSION' ) ? WC_VERSION : '' ),
			'php_version_major' => self::major_version( PHP_VERSION ),
			'locale' => get_locale(),
			'is_pro_known' => self::has_license_key(),
		];
	}

	private static function major_version( $version ) {
		$parts = explode( '.', (string) $version );
		return isset( $parts[0] ) ? sanitize_key( $parts[0] ) : '';
	}

	private static function has_license_key() {
		$stored_key = (string) get_option( 'wms_api_key', '' );
		return '' !== trim( $stored_key );
	}

	private static function sanitize_properties( $properties ) {
		$sanitized = [];

		foreach ( (array) $properties as $key => $value ) {
			if ( ! in_array( $key, self::$allowed_properties, true ) ) {
				continue;
			}

			if ( 'configured_carriers_count' === $key ) {
				$sanitized[ $key ] = max( 0, (int) $value );
				continue;
			}

			if ( 'is_pro_known' === $key ) {
				$sanitized[ $key ] = (bool) $value;
				continue;
			}

			$sanitized[ $key ] = sanitize_key( (string) $value );
		}

		return $sanitized;
	}

	private static function enqueue( $payload, $attempts = 0 ) {
		$queue = self::get_queue();
		$queue[] = [
			'payload' => $payload,
			'attempts' => max( 0, (int) $attempts ),
		];

		update_option( self::OPTION_QUEUE, array_slice( $queue, -self::MAX_QUEUE_SIZE ), false );
	}

	private static function get_queue() {
		$queue = get_option( self::OPTION_QUEUE, [] );
		return is_array( $queue ) ? $queue : [];
	}

}
