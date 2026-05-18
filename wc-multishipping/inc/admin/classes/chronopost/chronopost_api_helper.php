<?php


namespace WCMultiShipping\inc\admin\classes\chronopost;


use SoapClient;

class chronopost_api_helper {

	const PRO_RELAY_ACCOUNT_NUMBER = '19999700';
	const PRO_RELAY_PASSWORD = '058888';
	const DEFAULT_RELAY_MAX_POINTS = 5;
	const DEFAULT_RELAY_MAX_DISTANCE = 15;

	private function is_soap_available( $context = '' ) {
		$available = extension_loaded( 'soap' ) && class_exists( '\SoapClient' );
		if ( $available ) {
			return true;
		}

		$message = __( 'The PHP SOAP extension is not enabled on this server. Chronopost webservices are unavailable until it is installed.', 'wc-multishipping' );
		if ( $context !== '' ) {
			$message .= ' Context: ' . $context;
		}

		wms_logger( $message );

		return false;
	}

	public function get_quick_cost( $params ) {
		$url = 'https://ws.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl';
		if ( ! $this->is_soap_available( 'quickcost' ) ) {
			return false;
		}

		try {
			$client = new SoapClient( $url );
			$result = $client->quickCost( $params );

			return $result->return;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function get_pickup_point( $params ) {
		if ( ! is_array( $params ) ) {
			return $this->build_pickup_error( __( 'Invalid pickup point request.', 'wc-multishipping' ) );
		}

		$shipping_method_id = $this->normalize_shipping_method_id( $params['shipping_method_id'] ?? $params['method_id'] ?? '' );
		if ( ! empty( $shipping_method_id ) ) {
			return $this->search_relay_points( $shipping_method_id, $params );
		}

		$soap_method = $this->resolve_pickup_soap_method_from_params( $params );

		return $this->execute_pickup_point_request( $soap_method, $params );
	}

	public function search_relay_points( $shipping_method_id, array $address = [] ) {
		$shipping_method_id = $this->normalize_shipping_method_id( $shipping_method_id );
		if ( empty( $shipping_method_id ) ) {
			return $this->build_pickup_error( __( 'Shipping method not found for relay lookup.', 'wc-multishipping' ) );
		}

		$method_config = $this->get_relay_method_config( $shipping_method_id );
		if ( empty( $method_config ) ) {
			return $this->build_pickup_error( __( 'This Chronopost shipping method does not support relay lookup.', 'wc-multishipping' ) );
		}

		$credentials = $this->get_pickup_credentials();
		if ( empty( $credentials['accountNumber'] ) || empty( $credentials['password'] ) ) {
			return $this->build_pickup_error( __( 'Chronopost credentials are missing for relay lookup.', 'wc-multishipping' ) );
		}

		$zip_code  = $this->sanitize_pickup_value( $address['zipCode'] ?? $address['postcode'] ?? '' );
		$city      = $this->sanitize_pickup_value( $address['city'] ?? '' );
		$country   = $this->sanitize_pickup_value( $address['countryCode'] ?? $address['country'] ?? 'FR' );
		$address_1 = $this->sanitize_pickup_value( $address['address'] ?? $address['address_1'] ?? $this->get_customer_shipping_address() );

		if ( '' === $zip_code || '' === $city ) {
			return $this->build_pickup_error( __( 'Zip code and city are required to retrieve relay points.', 'wc-multishipping' ) );
		}

		$params = [
			'accountNumber'      => $credentials['accountNumber'],
			'password'           => $credentials['password'],
			'zipCode'            => $zip_code,
			'city'               => $city,
			'countryCode'        => $country,
			'type'               => $method_config['dropoff_type'],
			'productCode'        => $method_config['product_code'],
			'service'            => 'T',
			'weight'             => 2000,
			'shippingDate'       => date( 'd/m/Y' ),
			'maxPointChronopost' => isset( $address['maxPointChronopost'] ) ? (int) $address['maxPointChronopost'] : self::DEFAULT_RELAY_MAX_POINTS,
			'maxDistanceSearch'  => isset( $address['maxDistanceSearch'] ) ? (int) $address['maxDistanceSearch'] : self::DEFAULT_RELAY_MAX_DISTANCE,
			'holidayTolerant'    => 1,
		];

		if ( $method_config['add_address'] ) {
			$params['address'] = $address_1;
		}

		return $this->execute_pickup_point_request( $method_config['soap_method'], $params );
	}

	private function execute_pickup_point_request( $soap_method, array $params ) {
		$url = 'https://ws.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';

		if ( ! $this->is_soap_available( 'pickup_points' ) ) {
			return $this->build_pickup_error( __( 'The PHP SOAP extension is not enabled on this server. Please contact your hosting provider to enable it. This extension is required to retrieve Chronopost pickup points.', 'wc-multishipping' ) );
		}

		if ( ! extension_loaded( 'curl' ) ) {
			return $this->build_pickup_error( __( 'The CURL extension is not enabled on this server. Please contact your hosting provider to enable it. This extension is required to retrieve pickup points.', 'wc-multishipping' ) );
		}

		try {
			$client = new SoapClient( $url, [
				'trace'              => 1,
				'connection_timeout' => 10,
			] );
			$result = $client->$soap_method( $params );

			return $result->return ?? false;
		} catch (\SoapFault $e) {
			wms_logger( sprintf( __( 'Chronopost SOAP Error: %s', 'wc-multishipping' ), $e->getMessage() ) );

			return $this->build_pickup_error( __( 'Unable to connect to Chronopost API. Please try again later.', 'wc-multishipping' ) );
		} catch (\Exception $e) {
			wms_logger( sprintf( __( 'Chronopost Error: %s', 'wc-multishipping' ), $e->getMessage() ) );

			return $this->build_pickup_error( __( 'An unexpected error occurred while retrieving pickup points.', 'wc-multishipping' ) );
		}
	}

	private function get_pickup_credentials() {
		$connection_manager = chronopost_connection_manager::get_instance();
		if ( $connection_manager->is_pro_mode() ) {
			return [
				'accountNumber' => self::PRO_RELAY_ACCOUNT_NUMBER,
				'password'      => self::PRO_RELAY_PASSWORD,
			];
		}

		return [
			'accountNumber' => (string) get_option( 'wms_chronopost_account_number', '' ),
			'password'      => (string) get_option( 'wms_chronopost_account_password', '' ),
		];
	}

	private function get_relay_method_config( $shipping_method_id ) {
		$shipping_method_id = $this->normalize_shipping_method_id( $shipping_method_id );
		if ( empty( $shipping_method_id ) ) {
			return null;
		}

		$inter_methods = [
			'chronopost_relais_europe',
			'chronopost_relais_dom',
			'chronopost_2shop_europe',
		];
		$two_shop_methods = [
			'chronopost_2shop',
			'chronopost_2shop_europe',
		];

		return [
			'soap_method'  => in_array( $shipping_method_id, $inter_methods, true ) ? 'recherchePointChronopostInter' : 'recherchePointChronopost',
			'add_address'  => ! in_array( $shipping_method_id, $inter_methods, true ),
			'dropoff_type' => in_array( $shipping_method_id, $two_shop_methods, true ) ? 'C' : 'P',
			'product_code' => $this->get_shipping_method_product_code( $shipping_method_id ),
		];
	}

	private function get_shipping_method_product_code( $shipping_method_id ) {
		$shipping_method_id = $this->normalize_shipping_method_id( $shipping_method_id );

		if ( function_exists( 'WC' ) && WC() && WC()->shipping() ) {
			$shipping_methods = WC()->shipping()->load_shipping_methods();
			if ( ! empty( $shipping_methods[ $shipping_method_id ] ) && method_exists( $shipping_methods[ $shipping_method_id ], 'get_product_code' ) ) {
				return (string) $shipping_methods[ $shipping_method_id ]->get_product_code();
			}
		}

		$fallback_codes = [
			'chronopost_relais'           => '86',
			'chronopost_relais_europe'    => '49',
			'chronopost_relais_dom'       => '4P',
			'chronopost_2shop'            => '5X',
			'chronopost_2shop_europe'     => '6B',
			'chronopost_ambient_relais_13'=> '5Q',
		];

		return $fallback_codes[ $shipping_method_id ] ?? '86';
	}

	private function resolve_pickup_soap_method_from_params( array $params ) {
		$product_code = (string) ( $params['productCode'] ?? '' );
		$inter_product_codes = [ '49', '4P', '6B' ];

		return in_array( $product_code, $inter_product_codes, true ) ? 'recherchePointChronopostInter' : 'recherchePointChronopost';
	}

	private function normalize_shipping_method_id( $shipping_method_id ) {
		$shipping_method_id = (string) $shipping_method_id;
		if ( strpos( $shipping_method_id, ':' ) !== false ) {
			$shipping_method_id = substr( $shipping_method_id, 0, strpos( $shipping_method_id, ':' ) );
		}

		return trim( $shipping_method_id );
	}

	private function sanitize_pickup_value( $value ) {
		return trim( sanitize_text_field( (string) $value ) );
	}

	private function get_customer_shipping_address() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->customer ) {
			return '';
		}

		return $this->sanitize_pickup_value( WC()->customer->get_shipping_address() );
	}

	private function build_pickup_error( $message ) {
		$error_obj = new \stdClass();
		$error_obj->errorCode = 999;
		$error_obj->errorMessage = $message;

		return $error_obj;
	}

	public function get_status( $params ) {
		$url = "https://ws.chronopost.fr/tracking-cxf/TrackingServiceWS?wsdl";
		if ( ! $this->is_soap_available( 'tracking_status' ) ) {
			return false;
		}

		try {
			$client = new SoapClient( $url );
			$result = $client->trackSkybillV2( $params );

			return $result->return;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function register_multishipphing_parcels( $params ) {

		$shipping_service_url = 'https://ws.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl';
		if ( ! $this->is_soap_available( 'shipping_labels' ) ) {
			return false;
		}
		try {
			$client = new SoapClient( $shipping_service_url, [ 'trace' => true ] );
			$result = $client->shippingMultiParcelWithReservationV3( $params );

			if ( ! empty( $result->return->errorCode ) || empty( $result->return->reservationNumber ) ) {
				if ( is_admin() ) {
					wms_enqueue_message(
						sprintf(
							__( 'Order %s : Parcel not generated. %s', 'wc-multishipping' ),
							reset( $params['refValue'] )['shipperRef'],
							$result->return->errorMessage
						),
						'error'
					);
				}

				return false;
			}

			return $result->return;
		} catch (SoapFault $fault) {
			wms_logger( __( 'Webservice error: Soap Connection Issue (shippingMultiParcelWithReservationV3)', 'wc-multishipping' ) );

			return false;
		} catch (\Exception $e) {
			wms_logger( __( 'Webservice error: Soap Connection Issue (shippingMultiParcelWithReservationV3)', 'wc-multishipping' ) );
			if ( is_admin() ) {
				wms_enqueue_message(
					sprintf(
						__( 'Order %s : Parcel not generated. %s', 'wc-multishipping' ),
						reset( $params['refValue'] )['shipperRef'],
						$e->faultstring
					),
					'error'
				);
			}
			return false;
		}
	}

	public function get_labels_from_api( $tracking_number ) {
		if ( ! $this->is_soap_available( 'download_labels' ) ) {
			return false;
		}

		$client = new SoapClient(
			'https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl', [ 'trace' => true ]
		);
		try {
			$result = $client->getReservedSkybillWithTypeAndMode(
				[ 
					'reservationNumber' => $tracking_number,
					'mode' => get_option( 'wms_chronopost_label_format', 'PDF' ),
				]
			);

			if ( $result->return->errorCode === 0 ) {
				return base64_decode( $result->return->skybill );
			}
		} catch (\Exception $e) {
			wms_logger( __( 'Webservice error: Soap Connection Issue (get_labels_from_reservation)', 'wc-multishipping' ) );
			wms_logger(
				sprintf(
					__( '------------ Details: %s', 'wc-multishipping' ),
					print_r( $e, true )
				)
			);

			return false;
		}

		return false;
	}

	public function cancel_skybill( $params ) {
		if ( ! $this->is_soap_available( 'cancel_label' ) ) {
			return false;
		}

		$client = new SoapClient(
			'https://www.chronopost.fr/tracking-cxf/TrackingServiceWS?wsdl', [ 
				'trace' => 0,
				'connection_timeout' => 10,
			]
		);
		try {
			$result = $client->cancelSkybill( $params );
			if ( $result ) {
				if ( $result->return->errorCode == 0 ) {
					wms_enqueue_message( sprintf( __( 'The label %s was cancelled', 'wc-multishipping' ), $params['skybillValue'] ), "success" );

					return true;
				} else {
					return false;
					switch ( $result->return->errorCode ) {
						case '1':
							wms_enqueue_message( sprintf( __( 'An error occured when cancelling the %s label', 'wc-multishipping' ), $params['skybillValue'] ), 'error' );
							break;
						case '2':
							wms_enqueue_message(
								sprintf(
									__(
										'The %s package does not belong to the contract passed as parameter or has not yet been registered in the Chronopost tracking system',
										'wc-multishipping'
									),
									$params['skybillValue']
								),
								'error'
							);
							break;
						case '3':
							wms_enqueue_message(
								sprintf(
									__( 'The %s package can not be cancelled because it was already handled by Chronopost', 'wc-multishipping' ),
									$params['skybillValue']
								),
								'error'
							);
							break;
						default:
							break;
					}

					return false;
				}
			} else {
				wms_enqueue_message(
					sprintf(
						'Désolé, une erreur est survenu lors de la suppression de l\'étiquette %s. Merci de contacter Chronopost ou de réessayer plus tard',
						$params['skybillValue']
					),
					'error'
				);

				return false;
			}
		} catch (\Exception $e) {
			wms_logger( __( 'Webservice error: Soap Connection Issue (get_labels_from_reservation)', 'wc-multishipping' ) );
			wms_logger(
				sprintf(
					__( '------------ Details: %s', 'wc-multishipping' ),
					print_r( $e, true )
				)
			);

			return false;
		}

		return false;
	}


}
