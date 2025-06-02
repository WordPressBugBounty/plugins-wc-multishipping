<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_label;

class chronopost_label extends abstract_label {
	const DOWNLOAD_NAME = 'Chronopost';

	const SHIPPING_PROVIDER_ID = 'chronopost';

	static function get_api_helper() {
		return new chronopost_api_helper();
	}

	public function build_outcome_payload( $order ) {
		if ( empty( $this->with_account( $order ) ) )
			return false;
		if ( empty( $this->with_customer( $order ) ) )
			return false;
		if ( empty( $this->with_shipper( $order, false ) ) )
			return false;
		if ( empty( $this->with_recipient( $order, false ) ) )
			return false;
		if ( empty( $this->with_ref( $order ) ) )
			return false;
		if ( empty( $this->with_skybill_value( $order ) ) )
			return false;
		if ( empty( $this->with_scheduled_value( $order ) ) )
			return false;
		if ( empty( $this->with_credentials( $order ) ) )
			return false;

		return true;
	}

	public function build_income_payload( $order ) {
		if ( empty( $this->with_account( $order ) ) )
			return false;
		if ( empty( $this->with_customer( $order ) ) )
			return false;
		if ( empty( $this->with_shipper( $order, true ) ) )
			return false;
		if ( empty( $this->with_recipient( $order, true ) ) )
			return false;
		if ( empty( $this->with_ref( $order ) ) )
			return false;
		if ( empty( $this->with_skybill_value( $order, true ) ) )
			return false;
		if ( empty( $this->with_credentials( $order ) ) )
			return false;

		return true;
	}

	public function build_tracking_payload( $order ) {
		if ( empty( $this->with_tracking_number( $order ) ) )
			return false;
		if ( empty( $this->with_language() ) )
			return false;

		return true;
	}

	public function build_skybill_delete_payload( $tracking_number ) {
		return;

		return $this->with_credentials( $order )->with_skybill( $tracking_number );
	}

	public function with_account( $order ) {

		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );
		$shipping_method_id = $shipping_method->get_method_id();
		$method_settings = get_option( 'woocommerce_' . $shipping_method_id . '_' . $shipping_method->get_instance_id() . '_settings' );

		$header = [ 
			'idEmit' => 'WcMultiShipping',
			'accountNumber' => $method_settings['method_account_number'] ?? get_option( 'wms_chronopost_account_number', '' ),
			'subAccount' => get_option( 'wms_chronopost_subaccount_number', '' ),
		];

		$this->payload['headerValue'] = $header;

		return $this;
	}

	public function with_shipper( $order, $is_return_order = false ) {
		if ( ! $this->check_required_fields( 'shipper' ) )
			return false;

		if ( ! $is_return_order ) {
			$shipper = [ 
				'shipperCivility' => get_option( 'wms_chronopost_shipper_civility ', '' ),
				'shipperName' => substr( get_option( 'wms_chronopost_shipper_name', '' ), 0, 100 ),
				'shipperName2' => substr( get_option( 'wms_chronopost_shipper_name_2', '' ), 0, 100 ),
				'shipperAdress1' => substr( get_option( 'wms_chronopost_shipper_address_1', '' ), 0, 38 ),
				'shipperAdress2' => substr( get_option( 'wms_chronopost_shipper_address_2', '' ), 0, 38 ),
				'shipperZipCode' => substr( get_option( 'wms_chronopost_shipper_zip_code', '' ), 0, 9 ),
				'shipperCity' => substr( get_option( 'wms_chronopost_shipper_city', '' ), 0, 50 ),
				'shipperCountry' => substr( get_option( 'wms_chronopost_shipper_country', '' ), 0, 2 ),
				'shipperEmail' => substr( get_option( 'wms_chronopost_shipper_email', '' ), 0, 100 ),
				'shipperContactName' => substr( get_option( 'wms_chronopost_shipper_contact_name', '' ), 0, 80 ),
				'shipperPhone' => trim( substr( get_option( 'wms_chronopost_shipper_phone', '' ), 0, 17 ) ),
				'shipperMobilePhone' => '',
				'shipperPreAlert' => '',
			];
		} else {
			$customer_obj = new \WC_Customer( $order->get_customer_id() );

			$shipping_method_name = chronopost_order::get_shipping_method_name( $order );
			if ( empty( $shipping_method_name ) )
				return false;

			$address_type = 'shipping';
			if ( $shipping_method_name == 'chronopost_relais' || $shipping_method_name == 'chronopost_relais_europe' || $shipping_method_name == 'chronopost_relais_dom' ) {
				$address_type = 'billing';
			}

			$zipCode = call_user_func( [ $order, "get_{$address_type}_postcode" ] );
			if ( 4 == strlen( $zipCode ) )
				$zipCode = "0" . $zipCode;

			$shipper = [ 
				'shipperCivility' => 'M',
				'shipperName' => substr( remove_accents( call_user_func( [ $order, "get_{$address_type}_company" ] ) ), 0, 100 ),
				'shipperName2' => substr(
					remove_accents( call_user_func( [ $order, "get_{$address_type}_first_name" ] ) . ' ' . call_user_func( [ $order, "get_{$address_type}_last_name" ] ) ),
					0,
					100
				),
				'shipperAdress1' => substr( remove_accents( call_user_func( [ $order, "get_{$address_type}_address_1" ] ) ), 0, 38 ),
				'shipperAdress2' => substr( remove_accents( call_user_func( [ $order, "get_{$address_type}_address_2" ] ) ), 0, 38 ),
				'shipperZipCode' => substr( $zipCode, 0, 9 ),
				'shipperCity' => substr( remove_accents( call_user_func( [ $order, "get_{$address_type}_city" ] ) ), 0, 50 ),
				'shipperCountry' => substr( remove_accents( call_user_func( [ $order, "get_{$address_type}_country" ] ) ), 0, 2 ),
				'shipperEmail' => substr( $order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email(), 0, 80 ),

				'shipperContactName' => substr(
					remove_accents( call_user_func( [ $order, "get_{$address_type}_first_name" ] ) . ' ' . call_user_func( [ $order, "get_{$address_type}_last_name" ] ) ),
					0,
					100
				),
				'shipperPhone' => substr( trim( preg_replace( '/[^0-9\.\-]/', ' ', $order->get_billing_phone() ) ), 0, 17 ),
				'shipperMobilePhone' => trim( substr( $order->get_billing_phone(), 0, 17 ) ),
				'shipperPreAlert' => '',
			];
		}

		$this->payload['shipperValue'] = $shipper;

		return $this;
	}

	public function with_customer() {
		if ( ! $this->check_required_fields( 'customer' ) )
			return false;

		$customer = [ 
			'customerCivility' => get_option( 'wms_chronopost_customer_civility ', '' ),
			'customerName' => substr( get_option( 'wms_chronopost_customer_name', '' ), 0, 100 ),
			'customerName2' => substr( get_option( 'wms_chronopost_customer_name_2', '' ), 0, 100 ),
			'customerAdress1' => substr( get_option( 'wms_chronopost_customer_address_1', '' ), 0, 38 ),
			'customerAdress2' => substr( get_option( 'wms_chronopost_customer_address_2', '' ), 0, 38 ),
			'customerZipCode' => substr( get_option( 'wms_chronopost_customer_zip_code', '' ), 0, 9 ),
			'customerCity' => substr( get_option( 'wms_chronopost_customer_city', '' ), 0, 50 ),
			'customerCountry' => substr( get_option( 'wms_chronopost_customer_country', '' ), 0, 2 ),
			'customerEmail' => substr( get_option( 'wms_chronopost_customer_email', '' ), 0, 100 ),
			'customerContactName' => substr( get_option( 'wms_chronopost_customer_contact_name', '' ), 0, 80 ),
			'customerPhone' => trim( substr( get_option( 'wms_chronopost_customer_phone', '' ), 0, 17 ) ),
			'customerMobilePhone' => '',
			'customerPreAlert' => '',
		];


		$this->payload['customerValue'] = $customer;

		return $this;
	}

	public function with_recipient( $order, $is_return_order = 'false' ) {
		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );
		$is_relay_shipping_method = in_array( $shipping_method->get_method_id(), chronopost_order::ID_SHIPPING_METHODS_RELAY );

		$custom_phone = apply_filters( 'chronopost_get_custom_phone', '' );


		if ( ! $is_return_order ) {
			$customer_obj = new \WC_Customer( $order->get_customer_id() );

			$zipCode = $order->get_shipping_postcode();
			if ( 4 == strlen( $zipCode ) && $order->get_shipping_country() == 'FR' )
				$zipCode = "0" . $zipCode;

			$recipient = [ 
				'recipientName' => $is_relay_shipping_method ? substr( remove_accents( $order->get_shipping_company() ), 0, 100 ) : substr( remove_accents( $order->get_shipping_company() . ' ' . $order->get_shipping_last_name() . ' ' . $order->get_shipping_first_name() ), 0, 100 ),
				'recipientName2' => $is_relay_shipping_method ? substr( remove_accents( $order->get_shipping_last_name() . ' ' . $order->get_shipping_first_name() ), 0, 100 ) : substr( remove_accents( $order->get_shipping_company() . ' ' . $order->get_shipping_last_name() . ' ' . $order->get_shipping_first_name() ), 0, 100 ),
				'recipientAdress1' => substr( remove_accents( $order->get_shipping_address_1() ), 0, 38 ),
				'recipientAdress2' => substr( remove_accents( $order->get_shipping_address_2() ), 0, 38 ),
				'recipientZipCode' => substr( $zipCode, 0, 9 ),
				'recipientCity' => substr( remove_accents( $order->get_shipping_city() ), 0, 50 ),
				'recipientCountry' => substr( remove_accents( $order->get_shipping_country() ), 0, 2 ),
				'recipientContactName' => substr( remove_accents( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ), 0, 100 ),
				'recipientEmail' => substr( $order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email(), 0, 80 ),
				'recipientPhone' => preg_replace( '/\s|(\+33|0033)(\d)/', '0$2', preg_replace( '/[^0-9\+\-]/', '', $custom_phone ?? $order->get_billing_phone() ) ),
				'recipientMobilePhone' => preg_replace( '/\s|(\+33|0033)(\d)/', '0$2', preg_replace( '/[^0-9\+\-]/', '', $custom_phone ?? $order->get_billing_phone() ) ),
				'recipientPreAlert' => '',
			];
		} else {
			if ( ! $this->check_required_fields( 'shipper' ) )
				return false;

			$recipient = [ 
				'recipientName' => substr( get_option( 'wms_chronopost_shipper_name', '' ), 0, 100 ),
				'recipientName2' => substr( get_option( 'wms_chronopost_shipper_name_2', '' ), 0, 100 ),
				'recipientAdress1' => substr( get_option( 'wms_chronopost_shipper_address_1', '' ), 0, 38 ),
				'recipientZipCode' => substr( get_option( 'wms_chronopost_shipper_zip_code', '' ), 0, 9 ),
				'recipientCity' => substr( get_option( 'wms_chronopost_shipper_city', '' ), 0, 50 ),
				'recipientCountry' => substr( get_option( 'wms_chronopost_shipper_country', '' ), 0, 2 ),
				'recipientContactName' => substr( get_option( 'wms_chronopost_shipper_contact_name', '' ), 0, 80 ),
				'recipientEmail' => substr( get_option( 'wms_chronopost_shipper_email', '' ), 0, 100 ),
				'recipientPhone' => preg_replace( '/\s|(\+33|0033)(\d)/', '0$2', preg_replace( '/[^0-9\+\-]/', '', get_option( 'wms_chronopost_shipper_phone', '' ) ) ),
				'recipientMobilePhone' => preg_replace( '/\s|(\+33|0033)(\d)/', '0$2', preg_replace( '/[^0-9\+\-]/', '', get_option( 'wms_chronopost_shipper_mobile_phone', '' ) ) ),
				'recipientPreAlert' => '',
			];
		}

		if ( in_array( $shipping_method->get_method_id(), chronopost_order::ID_SHIPPING_METHODS_RELAY ) ) {
			$recipient['recipientAdress2'] = '';
		}

		$this->payload['recipientValue'] = $recipient;

		return $this;
	}

	public function with_ref( $order ) {
		$pickup_info = $order->get_meta( '_wms_chronopost_pickup_info', true );
		$recipient_ref = ( ! empty( $pickup_info ) ) ? $pickup_info['pickup_id'] : $order->get_customer_id();

		$parcels_number = get_post_meta( $order->get_id(), '_wms_chronopost_parcels_number', true ) ?: 1;
		$ref = [];
		for ( $i = 1; $i <= $parcels_number; $i++ ) {
			array_push( $ref, [ 
				'recipientRef' => $recipient_ref,
				'shipperRef' => $order->get_id(),
			] );
		}

		$this->payload['refValue'] = $ref;

		return $this;
	}

	public function with_saturday_shipping( $order ) {
		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );
		$shipping_method_id = $shipping_method->get_method_id();
		$method_settings = get_option( 'woocommerce_' . $shipping_method_id . '_' . $shipping_method->get_instance_id() . '_settings' );

		if ( ! isset( $method_settings['deliver_on_saturday'] ) )
			return 0;

		$shipping_method_shipping_on_saturday = $method_settings['deliver_on_saturday'] == 'yes' ? true : false;

		$post_meta_shipping_on_saturday = $order->get_meta( '_wms_chronopost_ship_on_saturday', true );
		$deliver_on_saturday = ( '' === $post_meta_shipping_on_saturday ) ? $shipping_method_shipping_on_saturday : $post_meta_shipping_on_saturday;

		$is_sending_day = self::is_sending_day();

		if ( empty( $post_meta_shipping_on_saturday ) ) {
			$saturday_shipping = 0;
		} else {
			if ( $post_meta_shipping_on_saturday || ( $deliver_on_saturday && $is_sending_day ) ) {
				if ( $shipping_method_id === 'chronorelaisdom' ) {
					$saturday_shipping = 368;
				} elseif ( $shipping_method_id == 'chronosameday' ) {
					$saturday_shipping = '974';
				} else {
					$saturday_shipping = 6;
				}
			}
		}

		$weight = chronopost_parcel::get_total_weight( $order->get_items() );
		if ( $shipping_method_id == 'chronorelaiseurope' ) {
			$weight <= 3 ? $saturday_shipping = '337' : $saturday_shipping = '338';
		}

		return $saturday_shipping;
	}

	public function with_skybill_value( $order, $is_return_order = false ) {
		$number_of_parcel = chronopost_parcel::get_number_of_parcels( $order );
		if ( empty( $number_of_parcel ) )
			return false;

		if ( ! chronopost_parcel::check_parcel_dimensions( $order ) )
			return false;

		$parcels_dimensions = chronopost_parcel::get_parcels_dimensions( $order );
		if ( empty( $parcels_dimensions ) )
			return false;

		$shipping_method_id = chronopost_order::get_shipping_method_name( $order );
		if ( empty( $shipping_method_id ) )
			return false;

		$all_shipping_methods_class = WC()->shipping()->load_shipping_methods();
		$product_code = ( $is_return_order ? $all_shipping_methods_class[ $shipping_method_id ]->get_return_product_code() : $all_shipping_methods_class[ $shipping_method_id ]->get_product_code() );
		$saturday_shipping = $this->with_saturday_shipping( $order );

		if ( $is_return_order && $this->payload['recipientValue']['recipientCountry'] !== 'FR' && $order->get_shipping_country() !== 'FR' ) {
			$product_code = '3T';
			$saturday_shipping = '332';
		}

		$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );

		$skybill = [];
		for ( $i = 0; $i < $number_of_parcel; $i++ ) {

			if ( $woocommerce_weight_unit == 'g' && ! empty( $parcels_dimensions[ $i ]['weight'] ) )
				$parcels_dimensions[ $i ]['weight'] = $parcels_dimensions[ $i ]['weight'] / 1000;

			$skybill[] = [ 
				'codCurrency' => 'EUR',
				'codValue' => '',
				'content1' => '',
				'content2' => '',
				'content3' => '',
				'content4' => '',
				'content5' => '',
				'customsCurrency' => 'EUR',
				'customsValue' => '',
				'evtCode' => 'DC',
				'insuredCurrency' => 'EUR',
				'insuredValue' => chronopost_parcel::get_ad_valorem_insurance_amount( $order ),
				'objectType' => 'MAR',
				'productCode' => $product_code,
				'service' => $saturday_shipping,
				'shipDate' => date( 'c' ),
				'shipHour' => date( 'H' ),
				'skybillRank' => $i + 1,
				'bulkNumber' => $number_of_parcel,
				'weight' => $parcels_dimensions[ $i ]['weight'],
				'weightUnit' => 'KGM',
				'height' => $parcels_dimensions[ $i ]['height'],
				'length' => $parcels_dimensions[ $i ]['length'],
				'width' => $parcels_dimensions[ $i ]['width'],
			];

			if ( $shipping_method_id == 'chronopost_13_fresh' ) {
				$skybill[ $i ] = array_merge( $skybill[ $i ], [ 'as' => '' ] );
			} else if ( $shipping_method_id == 'chronopost_ambient_relais_13' ) {
				$skybill[ $i ] = array_merge( $skybill[ $i ], [ 'as' => 'A02' ] );
			}
		}

		$skybillParams = [ 
			'mode' => get_option( 'wms_chronopost_label_format', 'PDF' ),
			'withReservation' => 0,
		];

		$this->payload['skybillValue'] = $skybill;
		$this->payload['skybillParamsValue'] = $skybillParams;
		$this->payload['numberOfParcel'] = $number_of_parcel;

		return $this;
	}

	public function with_scheduled_value( $order ) {
		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );
		$shipping_method_id = $shipping_method->get_method_id();

		if ( $shipping_method_id != 'chronopost_13_fresh' )
			return $this;

		$expiration_date = $order->get_meta( '_wms_chronopost_expiration_date', true );

		if ( empty( $expiration_date ) ) {
			wms_enqueue_message( __( 'You need to set an Expiration date for the orders\'s article(s) to generate the label. Please edit the order and set the Expiration date in the metabox on the right.', 'wc-multishipping' ), 'error' );

			return false;
		}

		$scheduled_value = [ 
			'expirationDate' => $expiration_date,
		];

		$this->payload['scheduledValue'] = $scheduled_value;

		return $this;
	}

	public function with_credentials( $order ) {
		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );
		$shipping_method_id = $shipping_method->get_method_id();
		$method_settings = get_option( 'woocommerce_' . $shipping_method_id . '_' . $shipping_method->get_instance_id() . '_settings' );

		$account_number = $method_settings['method_account_number'] ?? get_option( 'wms_chronopost_account_number', '' );
		$account_password = $method_settings['method_account_password'] ?? get_option( 'wms_chronopost_account_password', '' );

		if ( empty( $account_number ) || empty( $account_password ) ) {
			wms_enqueue_message( __( 'Your Chronopost account credentials are empty. Please set them in the Chronopost configuration', 'wc-multishipping' ), 'error' );

			return false;
		}

		$this->payload['accountNumber'] = $account_number;
		$this->payload['password'] = $account_password;

		return $this;
	}

	public function with_chronoprecise() {
		return;
		if ( $shipping_method_id == 'chronoprecise' ) {
			$chronopostprecise_creneaux_info = $order->get_meta( '_shipping_method_chronoprecise' );
			if ( is_array( $chronopostprecise_creneaux_info ) ) {
				$chronopostprecise_creneaux_info = array_shift( $chronopostprecise_creneaux_info );
			}

			$_dateRdvStart = new DateTime( $chronopostprecise_creneaux_info['deliveryDate'] );
			$_dateRdvStart->setTime( $chronopostprecise_creneaux_info['startHour'], $chronopostprecise_creneaux_info['startMinutes'] );

			$_dateRdvEnd = new DateTime( $chronopostprecise_creneaux_info['deliveryDate'] );
			$_dateRdvEnd->setTime( $chronopostprecise_creneaux_info['endHour'], $chronopostprecise_creneaux_info['endMinutes'] );

			$scheduledValue = [ 
				'appointmentValue' => [ 
					'timeSlotStartDate' => $_dateRdvStart->format( 'Y-m-d' ) . 'T' . $_dateRdvStart->format( 'H:i:s' ),
					'timeSlotEndDate' => $_dateRdvEnd->format( 'Y-m-d' ) . 'T' . $_dateRdvEnd->format( 'H:i:s' ),
					'timeSlotTariffLevel' => $chronopostprecise_creneaux_info['tariffLevel'],
				],
			];
			$expeditionArray['scheduledValue'] = $scheduledValue;


			foreach ( $expeditionArray['skybillValue'] as &$skybillValue ) {
				$skybillValue['productCode'] = $chronopostprecise_creneaux_info['productCode'];
				$skybillValue['service'] = $chronopostprecise_creneaux_info['serviceCode'];
				if ( isset( $chronopostprecise_creneaux_info['asCode'] ) ) {
					$skybillValue['as'] = $chronopostprecise_creneaux_info['asCode'];
				}
			}
		}
	}

	public static function is_sending_day() {
		$start_day = get_option( 'wms_chronopost_saturday_shipping_start_day', 'tuesday' );
		$end_day = get_option( 'wms_chronopost_saturday_shipping_end_day', 'thursday' );
		$start_time = get_option( 'wms_chronopost_saturday_shipping_start_time', '15' );
		$end_time = get_option( 'wms_chronopost_saturday_shipping_end_time', '18' );


		$saturday_start = strtotime( $start_day . ' + ' . $start_time . ' hours' );
		$saturday_end = strtotime( $end_day . ' + ' . $end_time . ' hours' );

		if ( time() >= $saturday_start && time() <= $saturday_end ) {
			return true;
		}

		return false;
	}

	private function with_skybill( $tracking_number ) {
		$this->payload['skybillValue'] = $tracking_number;

		return $this;
	}

	protected function with_tracking_number( $order ) {
		$shipment_data = $order->get_meta( '_wms_chronopost_shipment_data', true );
		if ( empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'][0]['_wms_parcel_skybill_number'] ) )
			return false;

		$this->payload['skybillNumber'] = $shipment_data['_wms_outward_parcels']['_wms_parcels'][0]['_wms_parcel_skybill_number'];

		return true;
	}

	protected function with_language() {
		$this->payload['language'] = get_locale();

		return true;
	}

	private function check_required_fields( $field_type ) {
		if ( empty( $field_type ) )
			return false;

		$required_fields = [ 
			'customer' => [ 
				'wms_chronopost_customer_civility',
				'wms_chronopost_customer_name',
				'wms_chronopost_customer_name_2',
				'wms_chronopost_customer_address_1',
				'wms_chronopost_customer_address_2',
				'wms_chronopost_customer_zip_code',
				'wms_chronopost_customer_city',
				'wms_chronopost_customer_country',
				'wms_chronopost_customer_contact_name',
			],
			'shipper' => [ 
				'wms_chronopost_shipper_civility',
				'wms_chronopost_shipper_name',
				'wms_chronopost_shipper_name_2',
				'wms_chronopost_shipper_address_1',
				'wms_chronopost_shipper_zip_code',
				'wms_chronopost_shipper_city',
				'wms_chronopost_shipper_country',
				'wms_chronopost_shipper_contact_name',
			],
		];


		$misssing_fields = [];
		foreach ( $required_fields[ $field_type ] as $one_required_field ) {
			if ( empty( get_option( $one_required_field, '' ) ) ) {
				$misssing_fields[] = $one_required_field;
			}
		}
		if ( ! empty( $misssing_fields ) ) {
			$this->errors[] = sprintf(
				__( 'Some fields are missing in "Shipping Address" section, please check your %s', 'wc-multishipping' ),
				'<a href="' . get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=chronopost">' . __( 'Chronopost configuration page', 'wc-multishipping' ) . '</a>'
			);

			return false;
		}

		return true;
	}
}