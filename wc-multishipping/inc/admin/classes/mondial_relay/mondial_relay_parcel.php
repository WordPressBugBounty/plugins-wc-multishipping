<?php

namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_parcel;

class mondial_relay_parcel extends abstract_parcel {
	const INWARD_PARCEL_NUMBER_META_KEY = '_wms_inward_parcel_number';
	const OUTWARD_PARCEL_NUMBER_META_KEY = '_wms_outward_parcel_number';

	const IS_DELIVERED_META_VALUE_TRUE = '1';
	const IS_DELIVERED_META_VALUE_FALSE = '0';

	const LAST_EVENT_CODE_META_KEY = '_wms_last_event_code';
	const LAST_EVENT_DATE_META_KEY = '_wms_last_event_date';
	const LAST_EVENT_LABEL_META_KEY = '_wms_last_event_label';

	const IS_DELIVERED_META_KEY = '_wms_is_delivered';

	const ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME = 'wms_mondial_relay_order_ids_to_update_tracking';


	const TRACKING_URL = 'https://www.mondialrelay.com/public/permanent/tracking.aspx?ens={ens}&exp={tracking_number}&language=fr';

	public static function get_integration_helper() {
		return new mondial_relay_helper();
	}


	public static function update_all_status() {
		$encoded_orders_to_update = get_option( mondial_relay_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME );
		if ( empty( $encoded_orders_to_update ) )
			return;


		$orders_to_update = json_decode( $encoded_orders_to_update );
		if ( empty( $orders_to_update ) )
			return;

		if ( ! is_array( $orders_to_update ) ) {
			$orders_to_update = [ $orders_to_update ];
		}

		$splitted_order_ids = array_splice( $orders_to_update, 0, 10 );

		$mondial_relay_label_class = new mondial_relay_label();
		$mondial_relay_api_helper = new mondial_relay_api_helper();

		foreach ( $splitted_order_ids as $one_order_id ) {
			if ( empty( $one_order_id ) ) {
				continue;
			}

			$order = wc_get_order( $one_order_id );
			if ( empty( $order ) )
				continue;

			$shipment_data = $order->get_meta( '_wms_mondial_relay_shipment_data' );
			if ( empty( $shipment_data ) || empty( $shipment_data['_wms_outward_parcels'] || empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'] ) ) ) {
				unset( $orders_to_update[ array_search( $one_order_id, $orders_to_update ) ] );
				$orders_to_update[] = $one_order_id;
				continue;
			}

			foreach ( $shipment_data['_wms_outward_parcels']['_wms_parcels'] as $one_outward_parcel ) {
				$tracking_number = $one_outward_parcel['_wms_parcel_skybill_number'];
				if ( empty( $tracking_number ) ) {
					unset( $orders_to_update[ array_search( $one_order_id, $orders_to_update ) ] );
					$orders_to_update[] = $one_order_id;
					continue;
				}

				$mondial_relay_label_class->payload = [];
				$mondial_relay_label_class->build_tracking_payload( $order );
				$api_result = $mondial_relay_api_helper->get_status( $mondial_relay_label_class->payload );

				$valid_status = [ '0', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89' ];
				if ( ! in_array( $api_result->STAT, $valid_status ) ) {
					unset( $orders_to_update[ array_search( $one_order_id, $orders_to_update ) ] );
					$orders_to_update[] = $one_order_id;
					continue;
				}

				foreach ( $api_result->Tracing->ret_WSI2_sub_TracingColisDetaille as $one_tracking_status ) {
					if ( empty( $one_tracking_status->Date ) )
						continue;

					$last_event_date = strtotime( str_replace( '/', '-', $one_tracking_status->Date ) );
					$last_event_label = $one_tracking_status->Libelle;
				}

				$event_last_code = $api_result->STAT;
				$is_delivered = ( '82' == $event_last_code ) ? true : false;

				$order->update_meta_data( self::LAST_EVENT_CODE_META_KEY, $event_last_code );
				$order->update_meta_data( self::LAST_EVENT_DATE_META_KEY, $last_event_date );
				$order->update_meta_data( self::LAST_EVENT_LABEL_META_KEY, $last_event_label );
				$order->update_meta_data(
					self::IS_DELIVERED_META_KEY,
					$is_delivered ? self::IS_DELIVERED_META_VALUE_TRUE : self::IS_DELIVERED_META_VALUE_FALSE
				);
				$order->save();

				if ( $is_delivered ) {
					$change_order_status = mondial_relay_order::WC_WMS_DELIVERED;
					unset( $orders_to_update[ array_search( $one_order_id, $orders_to_update ) ] );
				} else {
					$change_order_status = self::get_status_from_code( $event_last_code );
				}
			}
			if ( ! empty( $change_order_status ) ) {
				$order->set_status( $change_order_status );
				$order->save();
			}
		}

		update_option( mondial_relay_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME, json_encode( $orders_to_update ) );

		return;
	}

	public static function register_parcels_from_order( $order, $is_return_order = false ) {
		if ( empty( $order ) ) {
			wms_enqueue_message( __( 'Error while registering parcels for order => Order is empty.' ), 'error' );

			return false;
		}

		if ( ! mondial_relay_parcel::check_parcel_dimensions( $order ) )
			return false;

		$whitelist = [ "DE", "BE", "ES", "FR", "IT", "LU", "PT", "GB", "IE", "NL", "AT" ];

		if ( ! in_array( $order->get_shipping_country(), $whitelist ) ) {
			wms_enqueue_message( __( 'Shipping not available for this country', 'wc-multishipping' ), 'error' );

			return false;
		}

		$mondial_relay_label_class = new mondial_relay_label();
		$mondial_relay_api_helper = new mondial_relay_api_helper();

		$result = ! $is_return_order ? $mondial_relay_label_class->build_outcome_payload( $order ) : $mondial_relay_label_class->build_income_payload( $order );

		if ( empty( $result ) ) {
			if ( ! empty( $mondial_relay_label_class->errors ) )
				wms_enqueue_message( $mondial_relay_label_class->errors, 'error' );

			return false;
		}

		$api_result = $mondial_relay_api_helper->register_parcels( $mondial_relay_label_class->payload );

		if ( $api_result->STAT !== '0' ) {
			if ( 92 == $api_result->STAT ) {
				wms_enqueue_message(
					sprintf(
						__( 'Error while registering parcels for order => You don\'t have enough credits to generate a shipping label. You need to refill your account from this page : %s', 'wc-multishipping' ),
						'<a href="https://www.mondialrelay.fr/mon-profil-mondial-relay/compte-prepaye/" target="_blank">https://www.mondialrelay.fr/mon-profil-mondial-relay/compte-prepaye/</a>'

					),
					'error'
				);

				return false;
			}

			wms_enqueue_message( sprintf( __( 'Error while registering parcels for order => %s', 'wc-multishipping' ), $mondial_relay_api_helper->get_error_message( $api_result->STAT ) ), 'error' );

			return false;
		}

		if ( ! self::store_parcels_information( $order, $api_result, $is_return_order ) )
			return false;

		$post_generation_status = get_option( 'wms_mondial_relay_section_label_status_post_generation', '' );
		if ( ! empty( $post_generation_status ) ) {
			$order->set_status( $post_generation_status );
			$order->save();
		}

		return true;
	}

	public static function get_number_of_parcels( $order ) {
		return $order->get_meta( '_wms_mondial_relay_parcels_number', true ) ?: 1;
	}

	public static function get_total_weight( $order_items, $round = false ) {
		if ( empty( $order_items ) ) {
			wms_enqueue_message( __( 'Error while getting dimensions => No order items.' ), 'error' );

			return false;
		}

		$weight = 0;

		foreach ( $order_items as $item_id => $values ) {

			if ( ! $values->get_product_id() && ! $values->product_id )
				continue;
			$variation_id = $values->get_variation_id();
			$product_id = ! empty( $variation_id ) ? $values->get_variation_id() : ( isset( $values->product_id ) ? $values->product_id : $values->get_product_id() );
			$product = wc_get_product( $product_id );

			$items_weight = wc_get_weight( $product->get_weight(), get_option( 'woocommerce_weight_unit' ) );

			if ( $round ) {
				$items_weight = round( $items_weight );
			}
			$weight += $values->get_quantity() * $items_weight;
		}

		return $weight;
	}

	public static function check_parcel_dimensions( $order ) {
		if ( empty( $order ) ) {
			wms_enqueue_message( __( 'Can\'t check dimensions => No order selected', 'wc-multishipping' ), 'error' );

			return false;
		}

		$shipping_method_id = mondial_relay_order::get_shipping_method_name( $order );
		if ( empty( $shipping_method_id ) )
			return false;

		$parcels_dimensions = self::get_parcels_dimensions( $order );
		if ( empty( $parcels_dimensions ) )
			return false;

		$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );
		$total_weight = 0;

		foreach ( $parcels_dimensions as $one_parcel_dimension ) {

			if ( empty( $one_parcel_dimension['weight'] ) ) {
				wms_enqueue_message(
					__( 'Parcel weight is invalid. You should check the order\'s product weights or the parcel weight (in your order edition page).', 'wc-multishipping' ),
					'error'
				);

				return false;
			}

			if ( $woocommerce_weight_unit == 'kg' )
				$one_parcel_dimension['weight'] = $one_parcel_dimension['weight'] * 1000;

			$total_weight += $one_parcel_dimension['weight'];
		}


		if ( $total_weight < 15 ) {
			wms_enqueue_message( __( 'Parcel weight is lower than minimum value (15g)', 'wc-multishipping' ), 'error' );

			return false;
		}

		$all_shipping_methods_class = WC()->shipping()->load_shipping_methods();
		$product_code = $all_shipping_methods_class[ $shipping_method_id ]->get_product_code();


		if ( in_array( $product_code, array( '24R', 'HOM', 'HOC', 'HOD' ) ) && $total_weight > 30000 ) {
			wms_enqueue_message( __( 'Parcel weight is too big (max value = 30kg)', 'wc-multishipping' ), 'error' );

			return false;
		}

		if ( in_array( $product_code, array( 'LD1' ) ) && $total_weight > 70000 ) {
			wms_enqueue_message( __( 'Parcel weight is too big (max value = 70kg)', 'wc-multishipping' ), 'error' );

			return false;
		}

		if ( in_array( $product_code, array( 'DRI', 'LDS' ) ) && $total_weight > 1300000 ) {
			wms_enqueue_message( __( 'Parcel weight is too big (max value = 130kg)', 'wc-multishipping' ), 'error' );

			return false;
		}


		return true;
	}

	public static function store_parcels_information( $order, $api_result, $return = false ) {
		if ( empty( $order ) || empty( $api_result ) || empty( $api_result->ExpeditionNum ) || empty( $api_result->ExpeditionNum ) ) {
			wms_enqueue_message( __( 'Error while storing information => Missing parameter.' ), 'error' );

			return false;
		}
		$labels_type = ( ! $return ? '_wms_outward_parcels' : '_wms_inward_parcels' );

		$shipping_method_id = mondial_relay_order::get_shipping_method_name( $order );
		if ( empty( $shipping_method_id ) )
			return false;

		if ( ! self::check_parcel_dimensions( $order ) )
			return false;
		$parcels_dimensions = self::get_parcels_dimensions( $order );

		$new_shipment_data = [ 
			'_wms_shipping_provider' => 'mondial_relay',
			$labels_type => [ 
				'_wms_shipping_provider_method_id' => $shipping_method_id,
				'_wms_reservation_number' => $api_result->ExpeditionNum,
				'_wms_label_URL' => $api_result->URL_Etiquette,
			],
		];


		if ( empty( $api_result->ExpeditionNum ) ) {
			wms_logger( 'Error in function: store_outward_parcels_information ' );
			wms_logger( sprintf( '---- Details : Skybill Number is empty => %d', wms_display_value( $api_result->ExpeditionNum ) ) );

			return false;
		}

		$new_parcel = [ 
			'_wms_parcel_skybill_number' => $api_result->ExpeditionNum,
			'_wms_parcel' => (array) $api_result,
			'_wms_parcel_dimensions' => $parcels_dimensions[0],
		];
		$new_shipment_data[ $labels_type ]['_wms_parcels'][] = $new_parcel;


		$old_shipment_data = $order->get_meta( '_wms_mondial_relay_shipment_data', true );
		$shipment_data_to_save = ( is_array( $old_shipment_data ) ? array_merge( $old_shipment_data, $new_shipment_data ) : $new_shipment_data );

		$order->update_meta_data( '_wms_mondial_relay_shipment_data', $shipment_data_to_save );
		$order->save();

		return true;
	}

	public static function store_parcel_labels_from_order( $order, $is_return_order = false ) {
		$shipment_data = $order->get_meta( '_wms_mondial_relay_shipment_data', true );
		$label_type = ( ! $is_return_order ? '_wms_outward_parcels' : '_wms_inward_parcels' );
		if ( empty( $shipment_data ) || empty( $shipment_data[ $label_type ]['_wms_parcels'] ) ) {
			wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => Shipment information are missing.', 'wc-multishipping' ), $order->get_id() ), 'error' );

			return false;
		}

		$label_URL = $shipment_data[ $label_type ]['_wms_label_URL'];
		$reservation_number = $shipment_data[ $label_type ]['_wms_reservation_number'];

		if ( empty( $label_URL ) || empty( $reservation_number ) ) {
			wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => Label URL is missing.', 'wc-multishipping' ), $order->get_id() ), 'error' );

			return false;
		}

		$label_class = new mondial_relay_label();
		$label = $label_class->generate_labels_from_api( $label_URL );
		if ( empty( $label ) )
			return false;


		if ( ! class_exists( 'PDF_lib' ) )
			include ( WMS_LIB . 'PDF_lib.class.php' );

		$tracking_number = '';
		ob_start();
		$label_file_name = 'parcel_labels.pdf';
		$label_content_file = fopen( sys_get_temp_dir() . DS . $label_file_name, 'w' );
		fwrite( $label_content_file, $label );
		fclose( $label_content_file );

		$file_to_download_name = get_temp_dir() . 'wms_mondial_relay.label_(' . $reservation_number . ').pdf';
		$splitted_pdf_pages = @\PDF_lib::split_pdf( sys_get_temp_dir() . DS . $label_file_name, \PDF_lib::DESTINATION__STRING, $file_to_download_name );

		if ( empty( $splitted_pdf_pages ) ) {
			wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => Can\'t split pages.', 'wc-multishipping' ), $order->get_id() ), 'error' );

			return false;
		}

		foreach ( $shipment_data[ $label_type ]['_wms_parcels'] as $parcel_number => $one_parcel ) {

			if ( $is_return_order ) {
				if ( empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'][ $parcel_number ]['_wms_parcel_skybill_number'] ) ) {
					wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => Can\'t find outward order to attach inward label to.', 'wc-multishipping' ), $order->get_id() ), 'error' );
					break;
				}
				$tracking_number = $shipment_data['_wms_outward_parcels']['_wms_parcels'][ $parcel_number ]['_wms_parcel_skybill_number'];
			}

			if ( empty( $one_parcel['_wms_parcel_skybill_number'] ) ) {
				wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => Can\'t find inward skybill number.', 'wc-multishipping' ), $order->get_id() ), 'error' );
				continue;
			}

			$sql_result = $label_class->save_label_in_db(
				$order->get_id(),
				$splitted_pdf_pages[ $parcel_number ],
					$label_class::LABEL_FORMAT_PDF,
				$one_parcel['_wms_parcel_skybill_number'],
				$reservation_number,
				$is_return_order,
				$tracking_number
			);
			if ( empty( $sql_result ) ) {
				wms_enqueue_message( sprintf( __( 'Error while storing parcel labels for order %s => SQL issue.', 'wc-multishipping' ), $order->get_id() ), 'error' );

				return false;
			}
		}

		return true;
	}

	public static function get_formated_tracking_number_from_orders( $order_IDs = [] ) {
		if ( empty( $order_IDs ) )
			return false;
		if ( ! is_array( $order_IDs ) )
			$order_IDs = [ $order_IDs ];

		$label_types = [ '_wms_outward_parcels', '_wms_inward_parcels' ];

		$all_parcel_labels = [];

		foreach ( $order_IDs as $one_order_id ) {
			$order = wc_get_order( $one_order_id );
			$shipment_data = $order->get_meta( '_wms_mondial_relay_shipment_data', true );
			if ( empty( $shipment_data ) )
				continue;


			foreach ( $label_types as $one_label_type ) {

				if ( empty( $shipment_data[ $one_label_type ] ) || empty( $shipment_data[ $one_label_type ]['_wms_parcels'] ) || ! is_array( $shipment_data[ $one_label_type ]['_wms_parcels'] ) )
					continue;


				foreach ( $shipment_data[ $one_label_type ]['_wms_parcels'] as $one_parcel_number => $one_parcel ) {
					if ( empty( $one_parcel['_wms_parcel_skybill_number'] ) )
						continue;


					$one_parcel_information = new \stdClass();
					$one_parcel_information->order_id = $one_order_id;
					$one_parcel_information->tracking_number = $one_parcel['_wms_parcel_skybill_number'];

					$all_parcel_labels[ $one_order_id ][ $one_parcel_number ][ $one_label_type ] = $one_parcel_information;
				}
			}
		}

		return $all_parcel_labels;
	}

	public static function get_tracking_numbers_from_order_ids( $order_ids ) {
		if ( empty( $order_ids ) ) {
			wms_enqueue_message( __( 'Error while getting tracking numbers: No order(s) selected' ), 'error' );

			return false;
		}

		$label_type = [ '_wms_outward_parcels', '_wms_inward_parcels' ];
		if ( ! is_array( $order_ids ) )
			$order_ids = [ $order_ids ];

		$tracking_numbers = [];
		foreach ( $order_ids as $one_order_id ) {

			$order = wc_get_order( $one_order_id );
			$shipment_data = $order->get_meta( '_wms_mondial_relay_shipment_data', true );
			if ( empty( $shipment_data ) || empty( $shipment_data['_wms_outward_parcels'] ) && empty( $shipment_data['_wms_inward_parcels'] ) )
				continue;

			foreach ( $label_type as $one_label_type ) {

				if ( empty( $shipment_data[ $one_label_type ] ) )
					continue;

				foreach ( $shipment_data[ $one_label_type ]['_wms_parcels'] as $one_shipment ) {
					if ( empty( $one_shipment['_wms_parcel_skybill_number'] ) ) {
						wms_enqueue_message( sprintf( __( 'Error while getting tracking numbers from order %s => No skybill number found for this order.', 'wc-multishipping' ), $one_order_id ), 'error' );
						break;
					}
					$tracking_numbers[] = $one_shipment['_wms_parcel_skybill_number'];
				}
			}
		}

		return $tracking_numbers;
	}

	public static function get_parcels_dimensions( $order ) {
		if ( empty( $order ) ) {
			wms_enqueue_message( __( 'Error while getting dimensions => Order is empty.' ), 'error' );

			return false;
		}

		$parcels_number = $order->get_meta( '_wms_mondial_relay_parcels_number', true ) ?: 0;

		$parcels_dimensions = $order->get_meta( '_wms_mondial_relay_parcels_dimensions', true );
		if ( empty( $parcels_dimensions ) )
			$parcels_dimensions = [];

		if ( empty( $parcels_dimensions ) || ! is_array( $parcels_dimensions ) ) {
			for ( $i = 0; $i <= $parcels_number; $i++ ) {

				$parcels_dimensions[ $i ] = [ 
					'weight' => self::get_total_weight( $order->get_items() ),
					'length' => 1,
					'height' => 1,
					'width' => 1,
				];
			}
		}

		return $parcels_dimensions;
	}

	public static function get_insurance( $order ) {
		$order_insurance_enable = $order->get_meta( '_wms_mondial_relay_insurance', true );
		if ( '' === $order_insurance_enable )
			$order_insurance_enable = get_option( 'wms_mondial_relay_section_parcel_insurance', '0' );

		return $order_insurance_enable;
	}

	public static function get_installation_duration( $order ) {
		$installation_duration = $order->get_meta( '_wms_mondial_relay_installation_duration', true );

		if ( '' === $installation_duration )
			$installation_duration = get_option( 'wms_mondial_relay_section_parcel_installation_duration', '0' );

		return $installation_duration;
	}

	public static function get_shipping_value( $order ) {
		$shipping_value = $order->get_meta( '_wms_mondial_relay_shipping_value', true );

		if ( '' === $shipping_value )
			$shipping_value = get_option( 'wms_mondial_relay_section_parcel_shipping_value', '0' );

		return $shipping_value;
	}

	private static function get_status_from_code( $code ) {
		if ( empty( $code ) )
			return false;

		$status_from_code = [ 
			'80' => mondial_relay_order::WC_WMS_READY_TO_SHIP,
			'81' => mondial_relay_order::WC_WMS_TRANSIT,
			'82' => mondial_relay_order::WC_WMS_DELIVERED,
			'83' => mondial_relay_order::WC_WMS_ANOMALY,
		];

		if ( empty( $status_from_code[ $code ] ) )
			return false;

		return $status_from_code[ $code ];
	}

	public static function get_tracking_url( $tracking_number ) {
		$customer_code = get_option( 'wms_mondial_relay_customer_code', '' );
		$brand_code = get_option( 'wms_mondial_relay_brand_code', '' );

		return str_replace( [ '{ens}', '{tracking_number}' ], [ $customer_code . $brand_code, $tracking_number ], static::TRACKING_URL );
	}
}
