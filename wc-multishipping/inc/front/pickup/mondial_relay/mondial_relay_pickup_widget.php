<?php


namespace WCMultiShipping\inc\front\pickup\mondial_relay;


use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_api_helper;
use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_order;
use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_shipping_methods;
use WCMultiShipping\inc\front\pickup\abstract_classes\abstract_pickup_widget;

class mondial_relay_pickup_widget extends abstract_pickup_widget {
	const PICKUP_LOCATION_SESSION_VAR_NAME = 'wms_selected_mondial_relay_pickup_info';

	const SHIPPING_PROVIDER_ID = 'mondial_relay';

	const SHIPPING_PROVIDER_NAME = 'Mondial Relay';


	public static function is_shipping_method_enabled() {
		return 'yes' === get_option( 'wms_mondial_relay_enable', 'yes' );
	}

	public function get_pickup_point() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_pickup_selection' ) )
			wp_die( 'Invalid nonce' );

		$shipping_provider = wms_get_var( 'cmd', 'shipping_provider', '' );
		if ( static::SHIPPING_PROVIDER_ID !== $shipping_provider )
			return;

		$city = wms_get_var( 'string', 'city', '' );
		$zip_code = wms_get_var( 'cmd', 'zipcode', '' );
		$country = wms_get_var( 'cmd', 'country', 'FR' );

		if ( empty( $city ) || empty( $zip_code ) ) {
			wp_send_json(
				[ 
					'error' => true,
					'error_message' => __( 'Cannot find city or zip code', 'wc-multishipping' ),
				]
			);
		}
		$customer_code = get_option('wms_mondial_relay_customer_code', '');
		$private_key = get_option( 'wms_mondial_relay_private_key', '' );
		$nb_pickups = get_option( 'wms_mondial_relay_section_pickup_points_points_number', '10' );


		if ( empty( $customer_code ) || empty( $private_key ) ) {
			wp_send_json(
				[ 
					'error' => true,
					'error_message' => sprintf( __( 'Unable to load pickup points: Your %s account credentials are empty. Please set them in the %s configuration', 'wc-multishipping' ), self::SHIPPING_PROVIDER_NAME, self::SHIPPING_PROVIDER_NAME ),
				]
			);
		}

		$params = [ 
			'Enseigne' => $customer_code,
			'Pays' => $country,
			'Ville' => '',
			'CP' => $zip_code,
			'Poids' => '100',
			'NombreResultats' => $nb_pickups,
		];

		$code = implode( "", $params );
		$code .= $private_key;
		$params["Security"] = strtoupper( md5( $code ) );

		$mondial_relay_api_helper = new mondial_relay_api_helper();
		$result = $mondial_relay_api_helper->get_pickup_point( $params );

		if ( '0' !== $result->STAT ) {
			wp_send_json(
				[ 
					'error' => true,
					'error_message' => sprintf( __( 'Error: %s', 'wc-multishipping' ), wms_display_value( $mondial_relay_api_helper->get_error_message( $result->STAT ) ) ),
				]
			);
		}

		$pickup_points = [];

		foreach ( $result->PointsRelais->PointRelais_Details as $one_pickup ) {
			$additional_pickup = [ 
				'id' => wms_display_value( $one_pickup->Num ),
				'name' => wms_display_value( $one_pickup->LgAdr1 ),
				'address' => wms_display_value( $one_pickup->LgAdr3 ),
				'city' => wms_display_value( $one_pickup->Ville ),
				'zip_code' => wms_display_value( $one_pickup->CP ),
				'country' => wms_display_value( $one_pickup->Pays ),
				'latitude' => wms_display_value( str_replace( ',', '.', $one_pickup->Latitude ) ),
				'longitude' => wms_display_value( str_replace( ',', '.', $one_pickup->Longitude ) ),
			];

			$days = [ 'Horaires_Lundi', 'Horaires_Mardi', 'Horaires_Mercredi', 'Horaires_Jeudi', 'Horaires_Vendredi', 'Horaires_Samedi', 'Horaires_Dimanche' ];
			foreach ( $days as $day_num => $one_day ) {
				if ( '0000' === $one_pickup->$one_day->string['1'] || empty( $one_pickup->$one_day->string['1'] ) )
					continue;

				if ( '0000' === $one_pickup->$one_day->string['3'] || empty( $one_pickup->$one_day->string['3'] ) ) {
					$additional_pickup['opening_time'][] = wms_display_value( substr_replace( $one_pickup->$one_day->string['0'], ':', 2, 0 ) . ' - ' . substr_replace( $one_pickup->$one_day->string['1'], ':', 2, 0 ) );
				} else {
					$additional_pickup['opening_time'][] = wms_display_value( substr_replace( $one_pickup->$one_day->string['0'], ':', 2, 0 ) . '-' . substr_replace( $one_pickup->$one_day->string['1'], ':', 2, 0 ) . ' - ' . substr_replace( $one_pickup->$one_day->string['2'], ':', 2, 0 ) . '-' . substr_replace( $one_pickup->$one_day->string['3'], ':', 2, 0 ) );
				}
			}

			$pickup_points[] = $additional_pickup;
		}

		wp_send_json(
			[ 
				'error' => false,
				'error_message' => '',
				'data' => $pickup_points,
			]
		);
	}

	public static function get_order_class() {
		return new mondial_relay_order();
	}

	public static function get_shipping_methods_class() {
		return new mondial_relay_shipping_methods();
	}
}
