<?php

namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_settings;
use WCMultiShipping\inc\admin\classes\mondial_relay\mondialrelay_api_helper;
use WCMultiShipping\inc\admin\partials\settings\wms_partial_settings_button;

class mondial_relay_settings extends abstract_settings {
	const CONFIG_FILE = WMS_RESOURCES . 'mondial_relay' . DS . 'option_settings.json';

	const SHIPPING_METHOD_ID = 'mondial_relay';
	const SHIPPING_METHOD_DISPLAYED_NAME = 'Mondial Relay';

	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_' . self::SHIPPING_METHOD_ID, [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_' . self::SHIPPING_METHOD_ID, [ $this, 'update_settings' ] );
		new wms_partial_settings_button();

		add_action( 'wp_ajax_wms_mondial_relay_test_credentials', [ $this, 'wms_mondial_relay_test_credentials_ajax' ] );
		add_action( 'wp_ajax_wms_mondial_relay_log_export', [ $this, 'wms_export_log' ] );
	}

	public static function settings_tab() {
		wp_enqueue_script( 'wms_mondial_relay_settings', WMS_ADMIN_JS_URL . 'mondial_relay/mondial_relay_woocommerce_settings.js?t=' . time(), [ 'jquery' ] );
		wp_localize_script( 'wms_mondial_relay_settings', 'wmsSettings', [ 
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		] );
		wp_enqueue_style( 'wms_mondial_relay_settings', WMS_ADMIN_CSS_URL . 'mondial_relay/mondial_relay_woocommerce_settings.min.css?t=' . time() );
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function get_settings() {
		$first_status = [ '' => __( 'Do not change status', 'wc-multishipping' ) ];
		$all_status = array_merge( $first_status, wc_get_order_statuses() );

		$wc_status = array_filter( $all_status, function ($one_value) {
			return false === strpos( $one_value, 'Colissimo' );
		} );

		$value = get_option( 'wms_mondial_relay_enable', 'yes' );
		$price_before_discount = get_option( 'wms_mondial_relay_price_before_discount', 'yes' );

		$config_fields = [ 
			[ 
				"id" => "wms_mondial_relay_section_global_configuration",
				"type" => "title",
				"title" => __( "Global configuration", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_enable",
				"type" => "checkbox",
				"title" => __( "Enable this shipping method?", "wc-multishipping" ),
				"class" => "",
				"value" => $value,
			],
			[ 
				"id" => "wms_mondial_relay_section_global_configuration",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_account_information",
				"type" => "title",
				"title" => __( "Account information", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_customer_code",
				"type" => "text",
				"title" => __( "Customer Code", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_private_key",
				"type" => "text",
				"title" => __( "Private Key", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_brand_code",
				"type" => "text",
				"title" => __( "Brand Code", "wc-multishipping" ),
				"class" => "",
				"default" => "",
				"desc" => "<b>" . __( "Test credentials", "wc-multishipping" ) . "</b><br/>" . __( "Customer Code", "wc-multishipping" ) . " : BDTEST13 <br/> " . __( "Private Key", "wc-multishipping" ) . " : TestAPI1key <br/> " . __( "Brand Code", "wc-multishipping" ) . " : 11",
			],
			[ 
				"id" => "wms_mondial_relay_brand_code",
				"type" => "",
				"title" => "",
				"class" => "",
				"default" => "",
				"desc" => __( "Test credentials", "wc-multishipping" ) . "<br/>" . __( "Customer Code", "wc-multishipping" ) . " : BDTEST13 <br/> " . __( "Private Key", "wc-multishipping" ) . " : TestAPI1key <br/> " . __( "Brand Code", "wc-multishipping" ) . " : 11",
			],
			[ 
				"id" => "wms_mondial_relay_account_test_credentials",
				"type" => "button",
				"title" => __( "Access Test", "wc-multishipping" ),
				"class" => "button-secondary",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_section_account_information",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_pickup_points",
				"type" => "title",
				"title" => __( "Pickup Points Map", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_section_pickup_points_map_type",
				"type" => "select",

				"title" => __( "Display pickup points map via  (Pro Version only)", "wc-multishipping" ),
				"class" => "",
				"default" => "openstreetmap",
				"options" => [ 
					"openstreetmap" => "OpenStreetMap",
					"google_maps" => "Google Maps",
					"mondial_relay_map" => "Mondial Relay Widget",
				],
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
			],
			[ 
				"id" => "wms_mondial_relay_section_pickup_points_google_maps_api_key",
				"type" => "text",
				"title" => __( "Google Maps API Key (No need to fill this one if you use another solution than Google Maps)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_section_pickup_points",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_label",
				"type" => "title",
				"title" => __( "Label", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_section_label_generation_status",
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
				"id" => "wms_mondial_relay_section_label_status_post_generation",
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
				"id" => "wms_mondial_relay_section_label_send_email",
				"type" => "checkbox",
				"title" => __( "Send tracking link via email once the label is generated? (Pro version only)", "wc-multishipping" ),
				"class" => "",
				"custom_attributes" => [ 
					"disabled" => "disabled",
				],
			],
			[ 
				"id" => "wms_mondial_relay_section_label",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_shipping",
				"type" => "title",
				"title" => __( "Shipping Methods", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_price_before_discount",
				"type" => "checkbox",
				"title" => __( "Calculate shipping price before applying the discount?", "wc-multishipping" ),
				"class" => "",
				"value" => $price_before_discount,
			],
			[ 
				"id" => "wms_mondial_relay_section_shipping",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_sender_information",
				"type" => "title",
				"title" => __( "Your Sender Address", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_shipper_civility",
				"type" => "select",
				"title" => __( "Civility", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "MLLE",
				"options" => [ 
					"" => "",
					"MLLE" => "MLLE",
					"MR" => "MR",
					"MME" => "MME",
				],
			],
			[ 
				"id" => "wms_mondial_relay_shipper_name",
				"type" => "text",
				"title" => __( "First Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_name_2",
				"type" => "text",
				"title" => __( "Last Name", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_address_1",
				"type" => "text",
				"title" => __( "Address", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_address_2",
				"type" => "text",
				"title" => __( "Address 2", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_zip_code",
				"type" => "text",
				"title" => __( "Zip Code", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_city",
				"type" => "text",
				"title" => __( "City", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_country",
				"type" => "single_select_country",
				"title" => __( "Country", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_email",
				"type" => "text",
				"title" => __( "Email", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_phone",
				"type" => "text",
				"title" => __( "Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_shipper_mobile_phone",
				"type" => "text",
				"title" => __( "Mobile Phone", "wc-multishipping" ) . " " . __( "(required)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_section_sender_information",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_parcel",
				"type" => "title",
				"title" => __( "Parcels", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_section_parcel_insurance",
				"type" => "select",
				"title" => __( "Insurance", "wc-multishipping" ),
				"class" => "",
				"default" => "0",
				"options" => [ 
					"Sans assurance",
					"Assurance complémentaire N1",
					"Assurance complémentaire N2",
					"Assurance complémentaire N3",
					"Assurance complémentaire N4",
					"Assurance complémentaire N5",
				],
			],
			[ 
				"id" => "wms_mondial_relay_section_parcel_installation_duration",
				"type" => "number",
				"title" => __( "Installation Duration", "wc-multishipping" ),
				"class" => "",
				"default" => "",
				"custom_attributes" => [ 
					"min" => "0",
					"max" => "180",
				],
			],
			[ 
				"id" => "wms_mondial_relay_section_parcel_shipping_value",
				"type" => "number",
				"title" => __( "Default Shipping Value (in cents)", "wc-multishipping" ),
				"class" => "",
				"default" => "",
				"custom_attributes" => [ 
					"min" => "0",
					"max" => "9999999",
				],
			],
			[ 
				"id" => "wms_mondial_relay_section_parcel",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_log",
				"type" => "title",
				"title" => __( "Error Logs", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_log_export",
				"type" => "button",
				"title" => __( "Export error logs", "wc-multishipping" ),
				"class" => "button-secondary",
				"default" => "",
			],
			[ 
				"id" => "wms_mondial_relay_section_log",
				"type" => "sectionend",
			],
			[ 
				"id" => "wms_mondial_relay_section_debug",
				"type" => "title",
				"title" => __( "Debug mode", "wc-multishipping" ),
			],
			[ 
				"id" => "wms_mondial_relay_debug_mode",
				"title" => __( "Enable debug mode", "wc-multishipping" ),
				"default" => "0",
				"type" => "select",
				"options" => [ "No", "Yes" ],
			],
			[ 
				"id" => "wms_mondial_relay_section_debug",
				"type" => "sectionend",
			],
		];

		add_filter( 'woocommerce_create_account_default_checked', '__return_true' );


		return apply_filters( 'wc_settings_' . static::SHIPPING_METHOD_ID . '_settings', $config_fields );
	}


	public function wms_mondial_relay_test_credentials_ajax() {
		if ( ! current_user_can( 'administrator' ) )
			exit;

		$private_key = wms_get_var( 'cmd', 'private_key', '' );
		$code_enseigne = wms_get_var( 'cmd', 'code_enseigne', '' );

		if ( empty( $code_enseigne ) || empty( $private_key ) ) {
			$response = [ 
				'message' => __( 'Credentials not found', 'wc-multishipping' ),
				'error' => true,
			];

			wp_send_json( $response );
		}


		$params = [ 
			'Enseigne' => str_pad( $code_enseigne, 8 ),
			'Pays' => 'FR',
			'Ville' => '',
			'CP' => '35000',
			'Taille' => '',
			'Poids' => '',
			'Action' => '',
			'RayonRecherche' => '',
			'TypeActivite' => '',
			'DelaiEnvoi' => '',
		];

		$code = implode( "", $params );
		$code .= $private_key;
		$params["Security"] = strtoupper( md5( $code ) );

		$mondial_relay_api_helper = new mondial_relay_api_helper();
		$data = $mondial_relay_api_helper->get_pickup_point( $params );

		if ( $data->STAT === "0" ) {
			$response = [ 
				'message' => __( 'These account information are valid', 'wc-multishipping' ),
				'error' => false,
			];
		} else {

			$response = [ 
				'message' => sprintf( __( 'Error with Mondial Relay API: %s', 'wc-multishipping' ), $mondial_relay_api_helper->get_error_message( $data->STAT ) ),
				'error' => true,
			];
		}

		echo wp_send_json( $response );
	}
}
