<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_settings;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_api_helper;
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
	}

	public static function settings_tab() {
		wp_enqueue_script( 'wms_chronopost_settings', WMS_ADMIN_JS_URL . 'chronopost/chronopost_woocommerce_settings.js?t=' . time(), [ 'jquery' ] );
		wp_localize_script( 'wms_chronopost_settings', 'wmsSettings', [ 
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		] );
		wp_enqueue_style( 'wms_chronopost_settings', WMS_ADMIN_CSS_URL . 'chronopost/chronopost_woocommerce_settings.min.css?t=' . time() );
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function get_settings() {
		$first_status = [ '' => __( 'Do not change status', 'wc-multishipping' ) ];
		$all_status = array_merge( $first_status, wc_get_order_statuses() );

		$wc_status = array_filter( $all_status, function ($one_value) {
			return false === strpos( $one_value, 'Colissimo' );
		} );

		$value = get_option( 'wms_chronopost_enable', 'yes' );
		$price_before_discount = get_option( 'wms_chronopost_price_before_discount', 'yes' );

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
				"id" => "wms_chronopost_section_account_information",
				"type" => "title",
				"title" => __( "Account Information", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_chronopost_account_number",
				"type" => "text",
				"title" => __( "Account number", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_name",
				"type" => "text",
				"title" => __( "Account name", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_password",
				"type" => "password",
				"title" => __( "Password", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_chronopost_account_test_credentials",
				"type" => "button",
				"title" => __( "Test credentials", "wc-multishipping" ),
				"class" => "button-secondary",
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
				"title" => __( "Display pickup points map via  (Pro Version only)", "wc-multishipping" ),
				"class" => "",
				"default" => "openstreetmap",
				"options" => [ 
					"openstreetmap" => "OpenStreetMap",
					"google_maps" => "Google Maps",
				],
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
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


				"title" => __( "Automatically generate label on these order status (Pro Version only)", "wc-multishipping" ),

				"class" => "",
				"default" => "",
				"options" => $wc_status,
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
			],
			[ 
				"id" => "wms_chronopost_section_label_status_post_generation",
				"type" => "select",
				"title" => __( "Status to set after label generation (Pro version only)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
				"options" => $wc_status,
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
			],
			[ 
				"id" => "wms_chronopost_section_label_send_email",
				"type" => "checkbox",
				"title" => __( "Send tracking link via email once the label is generated? (Pro version only)", "wc-multishipping" ),
				"class" => "",
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
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
				"default" => "0",
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

	public function wms_chronopost_test_credentials_ajax() {
		if ( ! current_user_can( 'administrator' ) )
			exit;


		$account_number = wms_get_var( 'string', 'account_number', '' );
		$account_password = wms_get_var( 'string', 'account_password', '' );

		if ( empty( $account_number ) || empty( $account_password ) ) {
			$response = [ 
				'message' => __( 'Credentials not found', 'wc-multishipping' ),
				'error' => true,
			];

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

		echo wp_send_json( $response );
	}
}
