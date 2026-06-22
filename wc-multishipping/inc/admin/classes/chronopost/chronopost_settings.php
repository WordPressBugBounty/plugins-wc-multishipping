<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_settings;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_api_helper;
use WCMultiShipping\inc\admin\classes\telemetry\wms_telemetry;
use WCMultiShipping\inc\admin\partials\settings\wms_partial_settings_button;

class chronopost_settings extends abstract_settings {
	const CONFIG_FILE = WMS_RESOURCES . 'chronopost' . DS . 'option_settings.json';

	const SHIPPING_METHOD_ID = 'chronopost';
	const SHIPPING_METHOD_DISPLAYED_NAME = 'Chronopost';


	public function __construct() {

		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_' . self::SHIPPING_METHOD_ID, [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_' . self::SHIPPING_METHOD_ID, [ $this, 'update_settings' ] );
		new wms_partial_settings_button();


		add_action( 'wp_ajax_wms_chronopost_test_credentials', [ $this, 'wms_chronopost_test_credentials_ajax' ] );
		add_action( 'wp_ajax_wms_chronopost_log_export', [ $this, 'wms_export_log' ] );

		add_action( 'wp_ajax_wms_chronopost_generate_pro_keys', [ $this, 'ajax_generate_pro_keys' ] );
		add_action( 'wp_ajax_wms_chronopost_revoke_pro', [ $this, 'ajax_revoke_pro' ] );
	}

	public static function settings_tab() {
		$auth_jwt        = new \WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt();
		$refresh_expired = $auth_jwt->is_refresh_token_expired();
		$portal_payload  = self::get_pro_portal_payload( $auth_jwt, $refresh_expired );

		wp_enqueue_script( 'wms_chronopost_settings', WMS_ADMIN_JS_URL . 'chronopost/chronopost_woocommerce_settings.js?t=' . time(), [ 'jquery' ] );
		wp_localize_script( 'wms_chronopost_settings', 'wmsSettings', [
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'pro_nonce'           => wp_create_nonce( 'wms_chronopost_pro_nonce' ),
			'i18n_generating'     => __( 'Generating keys…', 'wc-multishipping' ),
			'i18n_revoking'       => __( 'Disconnecting…', 'wc-multishipping' ),
			'i18n_connecting'     => __( 'Opening Chronopost Pro…', 'wc-multishipping' ),
			'i18n_popup_blocked'  => __( 'Unable to open the Chronopost Pro window. Please allow pop-ups for this site and try again.', 'wc-multishipping' ),
			'i18n_connect_error'  => __( 'Error connecting to Chronopost Pro:', 'wc-multishipping' ),
			'i18n_connect_failed' => __( 'Unable to prepare the Chronopost Pro connection on this site.', 'wc-multishipping' ),
			'pro_portal_uri'      => self::get_chronopost_pro_portal_uri(),
			'pro_portal_payload'  => is_wp_error( $portal_payload ) ? [] : $portal_payload,
		] );
		wp_enqueue_style( 'wms_chronopost_settings', WMS_ADMIN_CSS_URL . 'chronopost/chronopost_woocommerce_settings.min.css?t=' . time() );
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function get_settings() {
		$first_status = [ '' => __( 'Do not change status', 'wc-multishipping' ) ];
		$all_status = array_unique( array_merge( $first_status, wc_get_order_statuses() ) );

		$wc_status = array_filter( $all_status, function ($one_value) {
			return false === strpos( $one_value, 'Colissimo' );
		} );

		$value = get_option( 'wms_chronopost_enable', 'yes' );
		$price_before_discount = get_option( 'wms_chronopost_price_before_discount', 'yes' );
		$connection_type = chronopost_connection_manager::get_default_connection_type();
		$has_active_license = self::has_active_license();
		$has_saved_soap_password = '' !== trim( (string) get_option( 'wms_chronopost_account_password', '' ) );

		$auth_jwt            = new \WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt();
		$is_pro_mode         = ( $connection_type === 'jwt' );
		$is_enrolled         = $auth_jwt->is_enrollment_complete();
		$has_keys            = $auth_jwt->has_keys();
		$refresh_expired     = $auth_jwt->is_refresh_token_expired();
		$is_connected        = $is_pro_mode && $is_enrolled && ! $refresh_expired;
		$portal_uri          = self::get_chronopost_pro_portal_uri();
		$portal_payload      = self::get_pro_portal_payload( $auth_jwt, $refresh_expired );

		$config_fields = [
			[
				"id" => "wms_chronopost_section_global_configuration",
				"type" => "title",
				"title" => __( "Global configuration", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_enable",
				"type" => "checkbox",
				"title" => __( "Enable this shipping method?", "wc-multishipping" ),
				"class" => "",
				"value" => $value,
			],
			[ 
				"id" => "wms_chronopost_section_global_configuration",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_connection_type",
				"type" => "title",
				"title" => __( "Connection Type", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_connection_type",
				"type" => "select",
				"title" => __( "Connection Method", "wc-multishipping" ),
				"class" => "wms-chronopost-connection-type",
				"default" => $connection_type,
				"options" => [ 
					"soap" => __( "Classic Connection (Account credentials)", "wc-multishipping" ),
					"jwt" => __( "Chronopost Pro (Direct connection)", "wc-multishipping" ),
				],
				"desc" => __( "Chronopost Pro requires an active Chronopost Pro account", "wc-multishipping" ),
			],
			[
				"id"   => "wms_chronopost_section_connection_type",
				"type" => "sectionend",
			],
			[
				"id"    => "wms_chronopost_section_pro",
				"type"  => "title",
				"title" => __( "Account Information", "wc-multishipping" ),
				"desc"  => self::get_pro_section_html( $is_connected, $has_keys, $refresh_expired, $portal_uri, $portal_payload ),
			],
			[
				"id"   => "wms_chronopost_section_pro",
				"type" => "sectionend",
			],
			[
				"id"    => "wms_chronopost_section_account_information",
				"type"  => "title",
				"title" => __( "Account Information", "wc-multishipping" ),
				"desc"  => self::get_soap_section_html(),
			],
			[ 
				"id" => "wms_chronopost_account_number",
				"type" => "text",
				"title" => __( "Account number", "wc-multishipping" ),
				"class" => "",
				"row_class" => "wms-chronopost-soap-row",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_name",
				"type" => "text",
				"title" => __( "Account name", "wc-multishipping" ),
				"class" => "",
				"row_class" => "wms-chronopost-soap-row",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_password",
				"type" => "text",
				"title" => __( "Password", "wc-multishipping" ),
				"class" => "",
				"row_class" => "wms-chronopost-soap-row",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_test_credentials",
				"type" => "button",
				"title" => __( "Test credentials", "wc-multishipping" ),
				"class" => "button-secondary",
				"row_class" => "wms-chronopost-soap-row",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_section_account_information",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_pickup_points",
				"type" => "title",
				"title" => __( "Pickup Points Map", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_section_pickup_points_map_type",
				"type" => "select",
				"title" => self::get_feature_title(
					$has_active_license,
					__( "Display pickup points map via", "wc-multishipping" ),
					__( "Display pickup points map via (Pro Version only)", "wc-multishipping" )
				),
				"class" => "",
				"default" => "openstreetmap",
				"options" => [ 
					"openstreetmap" => "OpenStreetMap",
					"google_maps" => "Google Maps",
				],
				"custom_attributes" => self::get_feature_attributes( $has_active_license ),
			],
			[ 
				"id" => "wms_chronopost_section_pickup_points_google_maps_api_key",
				"type" => "text",
				"title" => __( "Google Maps API Key", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_section_pickup_points",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_label",
				"type" => "title",
				"title" => __( "Label", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_label_format",
				"type" => "select",
				"title" => __( "Format", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "PDF",
				"options" => [ "PDF" => "PDF", "THE" => "PDF imprimante thermique" ],
			],
			[ 
				"id" => "wms_chronopost_section_label_generation_status",
				"type" => "multiselect",
				"title" => self::get_feature_title(
					$has_active_license,
					__( "Automatically generate label on these order status", "wc-multishipping" ),
					__( "Automatically generate label on these order status (Pro Version only)", "wc-multishipping" )
				),
				"class" => "",
				"default" => "",
				"options" => $wc_status,
				"custom_attributes" => self::get_feature_attributes( $has_active_license ),
			],
			[ 
				"id" => "wms_chronopost_section_label_status_post_generation",
				"type" => "select",
				"title" => self::get_feature_title(
					$has_active_license,
					__( "Status to set after label generation", "wc-multishipping" ),
					__( "Status to set after label generation (Pro version only)", "wc-multishipping" )
				),
				"class" => "",
				"default" => "",
				"options" => $wc_status,
				"custom_attributes" => self::get_feature_attributes( $has_active_license ),
			],
			[ 
				"id" => "wms_chronopost_section_label_send_email",
				"type" => "checkbox",
				"title" => self::get_feature_title(
					$has_active_license,
					__( "Send tracking link via email once the label is generated?", "wc-multishipping" ),
					__( "Send tracking link via email once the label is generated? (Pro version only)", "wc-multishipping" )
				),
				"class" => "",
				"custom_attributes" => self::get_feature_attributes( $has_active_license ),
			],
			[ 
				"id" => "wms_chronopost_section_label",
				"type" => "sectionend",
			],

			[ 
				"id" => "wms_chronopost_section_shipping",
				"type" => "title",
				"title" => __( "Shipping Methods", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_price_before_discount",
				"type" => "checkbox",
				"title" => __( "Calculate shipping price before applying the discount?", "wc-multishipping" ),
				"class" => "",
				"value" => $price_before_discount,
			],
			[ 
				"id" => "wms_chronopost_section_shipping",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_sender_information",
				"type" => "title",
				"title" => __( "Your Sender Address", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_shipper_civility",
				"type" => "select",
				"title" => __( "Civility", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "Mrs",
				"options" => [ "E" => "Mrs", "M" => "Mr", "L" => "Ms" ],
			],
			[ 
				"id" => "wms_chronopost_shipper_name",
				"type" => "text",
				"title" => __( "First Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_name_2",
				"type" => "text",
				"title" => __( "Last Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_address_1",
				"type" => "text",
				"title" => __( "Address", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_address_2",
				"type" => "text",
				"title" => __( "Address 2", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_zip_code",
				"type" => "text",
				"title" => __( "Zip Code", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_city",
				"type" => "text",
				"title" => __( "City", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_country",
				"type" => "single_select_country",
				"title" => __( "Country", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_contact_name",
				"type" => "text",
				"title" => __( "Contact Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_email",
				"type" => "text",
				"title" => __( "Email", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_phone",
				"type" => "text",
				"title" => __( "Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_shipper_mobile_phone",
				"type" => "text",
				"title" => __( "Mobile Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_section_sender_information",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_billing_information",
				"type" => "title",
				"title" => __( "Your Billing Address", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_customer_civility",
				"type" => "select",
				"title" => __( "Civility", "wc-multishipping" ),
				"class" => "",
				"default" => "Mrs",
				"options" => [ "E" => "Mrs", "M" => "Mr", "L" => "Ms" ],
			],
			[ 
				"id" => "wms_chronopost_customer_name",
				"type" => "text",
				"title" => __( "First Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_name_2",
				"type" => "text",
				"title" => __( "Last Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_address_1",
				"type" => "text",
				"title" => __( "Address", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_address_2",
				"type" => "text",
				"title" => __( "Address 2", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_zip_code",
				"type" => "text",
				"title" => __( "Zip Code", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_city",
				"type" => "text",
				"title" => __( "City", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_country",
				"type" => "single_select_country",
				"title" => __( "Country", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_contact_name",
				"type" => "text",
				"title" => __( "Contact Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_email",
				"type" => "text",
				"title" => __( "Email", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_phone",
				"type" => "text",
				"title" => __( "Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_customer_mobile_phone",
				"type" => "text",
				"title" => __( "Mobile Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_section_billing_information",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_insurance_ad_valorem",
				"type" => "title",
				"title" => __( "Ad Valorem insurance", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_section_insurance_ad_valorem_enabled",
				"type" => "select",
				"title" => __( "Activate Ad Valorem insurance", "wc-multishipping" ),
				"class" => "",
				"default" => "1",
				"options" => [ "No", "Yes" ],
			],
			[ 
				"id" => "wms_chronopost_section_insurance_ad_valorem_min_amount",
				"type" => "number",
				"title" => __( "Minimum amount to be insured", "wc-multishipping" ),
				"class" => "",
				"default" => "0",
				"custom_attributes" => [ "min" => "0" ],
			],
			[ 
				"id" => "wms_chronopost_section_insurance_ad_valorem",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_saturday",
				"type" => "title",
				"title" => __( "Saturday Shipping", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_saturday_shipping_start_day",
				"type" => "select",
				"title" => __( "From day", "wc-multishipping" ),
				"class" => "",
				"default" => "1",
				"options" => [ "monday" => "Monday", "tuesday" => "Tuesday", "wednesday" => "Wednesday", "thursday" => "Thursday", "friday" => "Friday", "saturday" => "Saturday", "sunday" => "Sunday" ],
			],
			[ 
				"id" => "wms_chronopost_saturday_shipping_start_time",
				"type" => "number",
				"title" => __( "From hour", "wc-multishipping" ),
				"class" => "",
				"default" => "0",
				"custom_attributes" => [ "min" => "0", "max" => "23" ],
			],
			[ 
				"id" => "wms_chronopost_saturday_shipping_end_day",
				"type" => "select",
				"title" => __( "End day", "wc-multishipping" ),
				"class" => "",
				"default" => "monday",
				"options" => [ "monday" => "Monday", "tuesday" => "Tuesday", "wednesday" => "Wednesday", "thursday" => "Thursday", "friday" => "Friday", "saturday" => "Saturday", "sunday" => "Sunday" ],
			],
			[ 
				"id" => "wms_chronopost_saturday_shipping_end_time",
				"type" => "number",
				"title" => __( "End hour", "wc-multishipping" ),
				"class" => "",
				"default" => "0",
				"custom_attributes" => [ "min" => "0", "max" => "23" ],
			],
			[ 
				"id" => "wms_chronopost_section_saturday",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_log",
				"type" => "title",
				"title" => __( "Error Logs", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_log_export",
				"type" => "button",
				"title" => __( "Export error logs", "wc-multishipping" ),
				"class" => "button-secondary",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_section_log",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_chronopost_section_debug",
				"type" => "title",
				"title" => __( "Debug mode", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_debug_mode",
				"title" => __( "Enable debug mode", "wc-multishipping" ),
				"default" => "1",
				"type" => "select",
				"options" => [ "No", "Yes" ],
			],
			[ 
				"id" => "wms_chronopost_section_debug",
				"type" => "sectionend",
			],
		];

		return apply_filters( 'wc_settings_' . static::SHIPPING_METHOD_ID . '_settings', $config_fields );
	}

	public static function update_settings() {
		if ( isset( $_POST['wms_chronopost_account_password'] ) ) {
			$password = sanitize_text_field( wp_unslash( $_POST['wms_chronopost_account_password'] ) );

			if ( '' === $password ) {
				$_POST['wms_chronopost_account_password'] = get_option( 'wms_chronopost_account_password', '' );
			}
		}

		woocommerce_update_options( static::get_settings() );
	}


	public static function get_chronopost_pro_portal_uri() {
		$default = 'https://www.chronopost.fr/professionnel/#/connexion-creation-compte';
		$uri     = get_option( 'CHRONOPOST_PRO_PORTAL_URI', '' );

		if ( empty( $uri ) ) {
			$uri = get_option( 'wms_chronopost_pro_portal_uri', $default );
		}

		return ! empty( $uri ) ? $uri : $default;
	}

	private static function get_chronopost_my_shops_uri() {
		return 'https://www.chronopost.fr/moncompte/displayAllShops.do';
	}

	private static function get_chronopost_import_orders_uri() {
		return 'https://www.chronopost.fr/professionnel/#/expedier-colis';
	}

	private static function get_chronopost_customer_area_uri() {
		return 'https://www.chronopost.fr/moncompte/displayCustomerArea.do';
	}

	private static function get_chronopost_pro_module_version() {
		$default = '10.5.3';
		$version = get_option( 'wms_chronopost_pro_module_version', '' );

		if ( empty( $version ) ) {
			$version = $default;
		}

		return (string) apply_filters( 'wms_chronopost_pro_module_version', $version );
	}

	public static function get_pro_portal_payload( $auth_jwt, $refresh_expired ) {
		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			return new \WP_Error(
				'sodium_missing',
				__( 'The Sodium PHP extension is required for Chronopost PRO but is not available on this server.', 'wc-multishipping' )
			);
		}

		if ( ! $auth_jwt->has_keys() && ! $auth_jwt->generate_keys() ) {
			return new \WP_Error(
				'key_generation_failed',
				__( 'Failed to generate the Chronopost PRO key pair. Check server logs for details.', 'wc-multishipping' )
			);
		}

		$tokens = $auth_jwt->generate_tokens( false, false );
		if ( ! $tokens ) {
			return new \WP_Error(
				'token_generation_failed',
				__( 'Failed to prepare the Chronopost PRO sign-in tokens. Check server logs for details.', 'wc-multishipping' )
			);
		}

		$shop_version   = function_exists( 'WC' ) && WC() ? WC()->version : '';
		$plugin_version = self::get_chronopost_pro_module_version();

		return [
			'token'              => $tokens['access_token'],
			'refresh_token'      => $tokens['refresh_token'],
			'shop_type'          => 'WOO',
			'shop_uri'           => trailingslashit( get_site_url() ),
			'shop_name'          => get_bloginfo( 'name' ),
			'shop_version'       => $shop_version,
			'chronopost_version' => $plugin_version,
			'from_expired_token' => $refresh_expired ? 'true' : 'false',
		];
	}

	public static function get_pro_section_html( $is_connected, $has_keys, $refresh_expired, $portal_uri, $portal_payload ) {
		ob_start();
		$import_orders_uri = self::get_chronopost_import_orders_uri();
		$is_local_shop     = self::is_local_shop_environment();

		if ( $is_connected ) {
			?>
			<div class="wms-chronopost-panel wms-chronopost-panel--pro">
				<div class="wms-chronopost-panel__hero">
					<h3><?php esc_html_e( 'Chronopost PRO is connected', 'wc-multishipping' ); ?></h3>
					<p><?php esc_html_e( 'Import your WooCommerce orders from the Chronopost professional space.', 'wc-multishipping' ); ?></p>
				</div>
				<div class="wms-chronopost-actions">
					<a class="button button-primary" href="<?php echo esc_url( $import_orders_uri ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Chronopost order import', 'wc-multishipping' ); ?></a>
				</div>
				<p class="wms-chronopost-status-line wms-chronopost-status-line--connected">
					<strong><?php esc_html_e( 'Shop connected successfully', 'wc-multishipping' ); ?></strong>
				</p>
			</div>
			<?php
		} else {
			if ( is_wp_error( $portal_payload ) ) {
				?>
				<div class="wms-chronopost-panel wms-chronopost-panel--pro">
					<div class="wms-chronopost-inline-error">
						<p><?php echo esc_html( $portal_payload->get_error_message() ); ?></p>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}
			?>
			<div class="wms-chronopost-panel wms-chronopost-panel--pro">
				<div class="wms-chronopost-actions">
					<a class="button button-primary wms-chronopost-connect-shop<?php echo $refresh_expired ? ' reconnect' : ''; ?>" href="<?php echo esc_url( $portal_uri ); ?>" target="_blank"><?php echo esc_html( $refresh_expired ? __( 'Reconnect Chronopost PRO', 'wc-multishipping' ) : __( 'Connect Chronopost PRO', 'wc-multishipping' ) ); ?></a>
				</div>
				<p class="wms-chronopost-status-line wms-chronopost-status-line--disconnected">
					<strong><?php esc_html_e( 'Shop not connected', 'wc-multishipping' ); ?></strong>
				</p>
					<?php if ( $is_local_shop ) : ?>
						<p class="wms-chronopost-inline-note">
							<strong><?php esc_html_e( 'For information: Chronopost PRO cannot be linked from a local website.', 'wc-multishipping' ); ?></strong>
						</p>
						<p class="wms-chronopost-inline-note">
							<?php esc_html_e( 'If your shop URL is local or private, use a staging or production URL before linking it to Chronopost PRO.', 'wc-multishipping' ); ?>
						</p>
					<?php endif; ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	public static function get_connection_type_intro_html() {
		return '';
	}

	public static function get_soap_section_html() {
		return '<span class="wms-chronopost-section-anchor" aria-hidden="true"></span>';
	}

	public static function get_soap_password_help_html( $has_saved_password ) {
		ob_start();
		if ( $has_saved_password ) :
			?>
			<p><?php esc_html_e( 'A password is already stored. Leave this blank to keep it, or re-enter one to replace it.', 'wc-multishipping' ); ?></p>
			<?php
		endif;

		echo wp_kses_post( self::get_test_credentials_html() );

		return ob_get_clean();
	}

	public static function get_test_credentials_html() {
		ob_start();
		?>
		<strong><?php esc_html_e( 'Test credentials', 'wc-multishipping' ); ?></strong><br/>
		<?php echo esc_html__( 'Account number', 'wc-multishipping' ) . ' : 19869502'; ?><br/>
		<?php echo esc_html__( 'Account name', 'wc-multishipping' ) . ' : Contrat TEST'; ?><br/>
		<?php echo esc_html__( 'Password', 'wc-multishipping' ) . ' : 255562'; ?>
		<?php

		return ob_get_clean();
	}

	private static function is_local_shop_environment() {
		$site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';

		if ( '' === $site_host ) {
			return false;
		}

		if ( in_array( $site_host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return true;
		}

		foreach ( [ '.local', '.test', '.localhost' ] as $suffix ) {
			if ( substr( $site_host, - strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
	}

	private static function has_active_license() {
		$api_key         = trim( (string) get_option( 'wms_api_key', '' ) );
		$expiration_date = (int) get_option( 'wms_license_expiration_date', 0 );

		return ( $api_key !== '' && $expiration_date > time() );
	}

	private static function get_feature_title( $is_enabled, $enabled_title, $disabled_title ) {
		return $is_enabled ? $enabled_title : $disabled_title;
	}

	private static function get_feature_attributes( $is_enabled ) {
		return $is_enabled ? [] : [ 'disabled' => 'disabled' ];
	}


	public function ajax_generate_pro_keys() {
		check_ajax_referer( 'wms_chronopost_pro_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-multishipping' ) ] );
		}

		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			wp_send_json_error( [
				'message' => __( 'The Sodium PHP extension is required for Chronopost PRO but is not available on this server.', 'wc-multishipping' ),
			] );
		}

		$auth = new \WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt();

		$auth->revoke_authentication();

		if ( ! $auth->generate_keys() ) {
			wp_send_json_error( [ 'message' => __( 'Failed to generate keys. Check server logs for details.', 'wc-multishipping' ) ] );
		}

		$tokens = $auth->generate_tokens();
		if ( ! $tokens ) {
			wp_send_json_error( [ 'message' => __( 'Failed to generate initial tokens.', 'wc-multishipping' ) ] );
		}

		update_option( 'wms_chronopost_connection_type', 'jwt' );

		wp_send_json_success( [
			'refresh_token' => $tokens['refresh_token'],
			'callback_url'  => rest_url( 'chronopost/v1/renew' ),
			'message'       => __( 'Keys generated. Copy the Callback URL and Refresh Token into your Chronopost PRO account.', 'wc-multishipping' ),
		] );
	}

	public function ajax_revoke_pro() {
		check_ajax_referer( 'wms_chronopost_pro_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-multishipping' ) ] );
		}

		$auth = new \WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt();
		$auth->revoke_authentication();

		wp_send_json_success( [
			'message' => __( 'Chronopost PRO disconnected. You are now in classic (SOAP) mode.', 'wc-multishipping' ),
		] );
	}

	public function wms_chronopost_test_credentials_ajax() {
		if ( ! current_user_can( 'administrator' ) )
			exit;


		$account_number = wms_get_var( 'string', 'account_number', '' );
		$account_password = wms_get_var( 'string', 'account_password', '' );
		$use_saved_secret = '1' === wms_get_var( 'cmd', 'use_saved_secret', '' );

		if ( empty( $account_password ) && $use_saved_secret ) {
			$account_password = get_option( 'wms_chronopost_account_password', '' );
		}

		if ( empty( $account_number ) || empty( $account_password ) ) {
			$response = [ 
				'message' => __( 'Credentials not found', 'wc-multishipping' ),
				'error' => true,
			];
			wms_telemetry::track( 'carrier_credentials_tested', [
				'carrier' => 'chronopost',
				'result' => 'error',
				'error_category' => 'missing_credentials',
			] );

			wp_send_json( $response );
		}

		$chronopost_api_helper = new chronopost_api_helper();
		$data = $chronopost_api_helper->get_quick_cost( [ 
			'accountNumber' => $account_number,
			'password' => $account_password,
			'depCode' => '92500',
			'arrCode' => '75001',
			'weight' => '1',
			'productCode' => '1',
			'type' => 'D',
		] );


		if ( empty( $data ) ) {
			$response = [ 
				'message' => __( 'These account information are not valid', 'wc-multishipping' ),
				'error' => true,
			];
		} elseif ( in_array( $data->errorCode, [ 1, 2 ] ) ) {
			$errorCodeMeaning = [ 
				1 => 'System Error',
				2 => 'Data empty',
			];
			$response = [ 
				'message' => __( 'The Chronopost API returns an error: %1$s (%2$s)', 'wc-multishipping' ),
				'error' => true,
			];
		} elseif ( $data->errorCode == 3 ) {
			$response = [ 
				'message' => __( 'These account information are not valid', 'wc-multishipping' ),
				'error' => true,
			];
		} else {
			$response = [ 
				'message' => __( 'These account information are valid', 'wc-multishipping' ),
				'error' => false,
			];
		}

		$telemetry_properties = [
			'carrier' => 'chronopost',
			'result' => empty( $response['error'] ) ? 'success' : 'error',
		];

		if ( ! empty( $response['error'] ) ) {
			$telemetry_properties['error_category'] = 'credentials_error';
		}

		wms_telemetry::track( 'carrier_credentials_tested', $telemetry_properties );
		echo wp_send_json( $response );
	}
}
