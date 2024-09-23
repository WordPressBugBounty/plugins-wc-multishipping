<?php


namespace WCMultiShipping\inc\admin\classes\chronopost;


use SoapClient;

class chronopost_api_helper {

	public function get_quick_cost( $params ) {
		$url = 'https://ws.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl';

		try {
			$client = new SoapClient( $url );
			$result = $client->quickCost( $params );

			return $result->return;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function get_pickup_point( $params ) {
		$url = 'https://ws.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';

		try {
			$client = new SoapClient( $url );
			$result = $client->recherchePointChronopostInterParService( $params );

			return $result->return;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function get_status( $params ) {
		$url = "https://ws.chronopost.fr/tracking-cxf/TrackingServiceWS?wsdl";

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
					wms_enqueue_message( sprintf( __( 'The label %s was cancelled', 'chronopost-woocommerce-shipping' ), $params['skybillValue'] ), "success" );

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
