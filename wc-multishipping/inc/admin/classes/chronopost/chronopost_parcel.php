<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;


use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_parcel;

class chronopost_parcel extends abstract_parcel {
	const INWARD_PARCEL_NUMBER_META_KEY = '_wms_inward_parcel_number';
	const OUTWARD_PARCEL_NUMBER_META_KEY = '_wms_outward_parcel_number';

	const IS_DELIVERED_META_KEY = '_wms_is_delivered';

	const IS_DELIVERED_CODE = [ 'DI1', 'DI2' ];
	const IS_DELIVERED_META_VALUE_TRUE = '1';
	const IS_DELIVERED_META_VALUE_FALSE = '0';

	const UPDATE_STATUS_PERIOD = '-15 days';

	const LAST_EVENT_CODE_META_KEY = 'wms_last_event_code';
	const LAST_EVENT_DATE_META_KEY = 'wmd_last_event_date';

	const TRACKING_URL = 'https://www.chronopost.fr/fr/chrono_suivi_search?listeNumerosLT={tracking_number}';

	public function __construct() {
		$chrono_order_class = new chronopost_order();
		$orders = $chrono_order_class->get_orders();
	}

	public static function get_integration_helper() {
		return new chronopost_helper();
	}

	public static function update_all_status() {
		$encoded_orders_to_update = get_option( chronopost_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME );
		if ( empty( $encoded_orders_to_update ) )
			return;


		$orders_to_update = json_decode( $encoded_orders_to_update );
		if ( empty( $orders_to_update ) )
			return;

		if ( ! is_array( $orders_to_update ) ) {
			$orders_to_update = [ $orders_to_update ];
		}

		$splitted_order_ids = array_splice( $orders_to_update, 0, 10 );

		$chronopost_label_class = new chronopost_label();
		$chronopost_api_helper = new chronopost_api_helper();

		foreach ( $splitted_order_ids as $one_order_id ) {
			if ( empty( $one_order_id ) ) {
				continue;
			}

			$order = wc_get_order( $one_order_id );
			if ( empty( $order ) )
				continue;

			$shipment_data = $order->get_meta( '_wms_chronopost_shipment_data' );
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

				$chronopost_label_class->payload = [];
				$chronopost_label_class->build_tracking_payload( $order );
				$api_result = $chronopost_api_helper->get_status( $chronopost_label_class->payload );

				if ( 0 != $api_result->errorCode || empty( $api_result->listEventInfoComp->events ) ) {
					unset( $orders_to_update[ array_search( $one_order_id, $orders_to_update ) ] );
					$orders_to_update[] = $one_order_id;
					continue;
				}
				$last_event = is_array( $api_result->listEventInfoComp->events ) ? end( $api_result->listEventInfoComp->events ) : $api_result->listEventInfoComp->events;
				$last_event_date = strtotime( str_replace( '/', '-', $last_event->eventDate ) );
				$event_last_code = $last_event->code;
				$last_event_label = $last_event->eventLabel;
				$is_delivered = ( 'D' == trim( $event_last_code ) ) ? true : false;

				$order->update_meta_data( self::LAST_EVENT_CODE_META_KEY, $event_last_code );
				$order->update_meta_data( self::LAST_EVENT_DATE_META_KEY, $last_event_date );
				$order->update_meta_data( self::LAST_EVENT_LABEL_META_KEY, $last_event_label );
				$order->update_meta_data(
					self::IS_DELIVERED_META_KEY,
					$is_delivered ? self::IS_DELIVERED_META_VALUE_TRUE : self::IS_DELIVERED_META_VALUE_FALSE
				);
				$order->save();

				if ( $is_delivered ) {
					$change_order_status = chronopost_order::WC_WMS_DELIVERED;
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

		update_option( chronopost_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME, json_encode( $orders_to_update ) );

		return;
	}

	public static function register_parcels_from_order( $order, $is_return_order = false ) {
		if ( empty( $order ) ) {
			wms_enqueue_message( __( 'Error while registering parcels for order => Order is empty.' ), 'error' );

			return false;
		}

		if ( ! chronopost_parcel::check_parcel_dimensions( $order ) )
			return false;

		$whitelist = [ "FR", "DE", "AT", "BE", "EE", "LV", "LT", "LU", "NL", "PT", "CH", "GB" ];

		if ( $is_return_order && ! in_array( $order->get_shipping_country(), $whitelist ) ) {
			wms_enqueue_message( __( 'Return labels are not available for this country', 'wc-multishipping' ), 'error' );

			return false;
		}

		$label_class = new chronopost_label();
		$api_helper = new chronopost_api_helper();

		$result = ! $is_return_order ? $label_class->build_outcome_payload( $order ) : $label_class->build_income_payload( $order );

		if ( empty( $result ) ) {
			if ( ! empty( $label_class->errors ) )
				wms_enqueue_message( $label_class->errors, 'error' );

			return false;
		}

		$api_result = $api_helper->register_multishipphing_parcels( $label_class->payload );

		if ( empty( $api_result ) )
			return false;


		if ( ! self::store_parcels_information( $order, $api_result, $is_return_order ) )
			return false;

		$post_generation_status = get_option( 'wms_chronopost_section_label_status_post_generation', '' );
		if ( ! empty( $post_generation_status ) ) {
			$order->set_status( $post_generation_status );
			$order->save();
		}

		return true;
	}

	public static function get_number_of_parcels( $order ) {
		return $order->get_meta( '_wms_chronopost_parcels_number', true ) ?: 1;
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

		$shipping_method_id = chronopost_order::get_shipping_method_name( $order );
		if ( empty( $shipping_method_id ) )
			return false;

		$parcels_dimensions = static::get_parcels_dimensions( $order );
		if ( empty( $parcels_dimensions ) )
			return false;

		foreach ( $parcels_dimensions as $one_parcel_dimension ) {

			if ( empty( $one_parcel_dimension['weight'] ) ) {
				wms_enqueue_message(
					__( 'Parcel weight is invalid. You should check the order\'s product weights or the parcel weight (in your order edition page).', 'wc-multishipping' ),
					'error'
				);

				return false;
			}

			$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );

			if ( $shipping_method_id === 'chronopost_relais' || $shipping_method_id === 'chronopost_relais_europe' || $shipping_method_id === 'chronopost_relais_dom' || $shipping_method_id === 'chronopost_2shop' || $shipping_method_id === 'chronopost_2shop_europe' ) {

				$max_weight = ( $woocommerce_weight_unit == 'g' ? 20000 : 20 ); // Kg
				$max_size = 100; // cm
				$max_global_size = 250; //cm
			} else {
				$max_weight = ( $woocommerce_weight_unit == 'g' ? 20000 : 30 );
				$max_size = 150; // cm
				$max_global_size = 300; // cm
			}
			if ( $one_parcel_dimension['weight'] > $max_weight ) {
				wms_enqueue_message( sprintf( __( 'Parcel dimensions exceed the weight limit (%s kg)', 'wc-multishipping' ), $max_weight ), 'error' );

				return false;
			}
			if ( $one_parcel_dimension['width'] > $max_size || $one_parcel_dimension['height'] > $max_size || $one_parcel_dimension['length'] > $max_size ) {
				wms_enqueue_message( sprintf( __( 'Parcel dimensions exceed the size limit (%s cm)', 'wc-multishipping' ), $max_size ), 'error' );

				return false;
			}
			if ( $one_parcel_dimension['width'] + ( 2 * $one_parcel_dimension['height'] ) + ( 2 * $one_parcel_dimension['length'] ) > $max_global_size ) {
				wms_enqueue_message( sprintf( __( 'Parcel dimensions exceed the total (L+2H+2l) size limit (%s cm)', 'wc-multishipping' ), $max_global_size ), 'error' );

				return false;
			}
		}

		return true;
	}


	public static function store_parcels_information( $order, $api_result, $return = false ) {
		if ( empty( $order ) || empty( $api_result ) || empty( $api_result->reservationNumber ) || empty( $api_result->resultParcelValue ) ) {
			wms_enqueue_message( __( 'Error while storing information => Missing parameter.' ), 'error' );

			return false;
		}
		$labels_type = ( ! $return ? '_wms_outward_parcels' : '_wms_inward_parcels' );

		$shipping_method_id = chronopost_order::get_shipping_method_name( $order );
		if ( empty( $shipping_method_id ) )
			return false;

		if ( ! chronopost_parcel::check_parcel_dimensions( $order ) )
			return false;
		$parcels_dimensions = chronopost_parcel::get_parcels_dimensions( $order );

		$new_shipment_data = [ 
			'_wms_shipping_provider' => 'chronopost',
			$labels_type => [ 
				'_wms_shipping_provider_method_id' => $shipping_method_id,
				'_wms_reservation_number' => $api_result->reservationNumber,
			],
		];


		if ( ! is_array( $api_result->resultParcelValue ) )
			$api_result->resultParcelValue = [ $api_result->resultParcelValue ];

		$tracking_infos = [];
		foreach ( $api_result->resultParcelValue as $parcel_number => $one_parcel ) {
			if ( empty( $one_parcel->skybillNumber ) ) {
				wms_logger( 'Error in function: store_outward_parcels_information ' );
				wms_logger( sprintf( '---- Details : Skybill Number is empty => %d' ), wms_display_value( $one_parcel->skybillNumber ) );

				return false;
			}

			$new_parcel = [ 
				'_wms_parcel_skybill_number' => $one_parcel->skybillNumber,
				'_wms_parcel' => (array) $one_parcel,
				'_wms_chronopost_parcel_dimensions' => $parcels_dimensions[ $parcel_number ],
			];
			$new_shipment_data[ $labels_type ]['_wms_parcels'][] = $new_parcel;
		}


		$old_shipment_data = $order->get_meta( '_wms_chronopost_shipment_data', true );
		$shipment_data_to_save = ( is_array( $old_shipment_data ) ? array_merge( $old_shipment_data, $new_shipment_data ) : $new_shipment_data );

		$order->update_meta_data( '_wms_chronopost_shipment_data', $shipment_data_to_save );
		$order->save();

		return true;
	}

	public static function store_parcel_labels_from_order( $order, $is_return_order = false ) {
		$shipment_data = $order->get_meta( '_wms_chronopost_shipment_data', true );
		$label_type = ( ! $is_return_order ? '_wms_outward_parcels' : '_wms_inward_parcels' );

		if ( empty( $shipment_data ) || empty( $shipment_data[ $label_type ]['_wms_parcels'] ) ) {
			wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => Shipment information are missing.', $order->get_id() ), 'error' );

			return false;
		}

		$reservation_number = $shipment_data[ $label_type ]['_wms_reservation_number'];
		if ( empty( $reservation_number ) ) {
			wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => Reservation number is missing.', $order->get_id() ), 'error' );

			return false;
		}

		$label_class = new chronopost_label();

		$label = $label_class->generate_labels_from_api( $reservation_number );
		if ( empty( $label ) )
			return false;


		if ( ! class_exists( 'PDF_lib' ) )
			include( WMS_LIB . 'PDF_lib.class.php' );

		$tracking_number = '';
		ob_start();
		$label_file_name = 'parcel_labels.pdf';
		$label_content_file = fopen( sys_get_temp_dir() . DS . $label_file_name, 'w' );
		fwrite( $label_content_file, $label );
		fclose( $label_content_file );

		$file_to_download_name = get_temp_dir() . 'wms_chronopost.label_(' . $reservation_number . ').pdf';

		$splitted_pdf_pages = @\PDF_lib::split_pdf( sys_get_temp_dir() . DS . $label_file_name, \PDF_lib::DESTINATION__STRING, $file_to_download_name, 2 );

		if ( empty( $splitted_pdf_pages ) ) {
			wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => Can\'t split pages.', $order->get_id() ), 'error' );

			return false;
		}

		foreach ( $shipment_data[ $label_type ]['_wms_parcels'] as $parcel_number => $one_parcel ) {

			if ( $is_return_order ) {
				if ( empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'][ $parcel_number ]['_wms_parcel_skybill_number'] ) ) {
					wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => Can\'t find outward order to attach inward label to.', $order->get_id() ), 'error' );
					break;
				}
				$tracking_number = $shipment_data['_wms_outward_parcels']['_wms_parcels'][ $parcel_number ]['_wms_parcel_skybill_number'];
			}

			if ( empty( $one_parcel['_wms_parcel_skybill_number'] ) ) {
				wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => Can\'t find inward skybill number.', $order->get_id() ), 'error' );
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
				wms_enqueue_message( sprintf( 'Error while storing parcel labels for order %s => SQL issue.', $order->get_id() ), 'error' );

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
			$shipment_data = $order->get_meta( '_wms_chronopost_shipment_data', true );
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
			$shipment_data = $order->get_meta( '_wms_chronopost_shipment_data', true );
			if ( empty( $shipment_data ) || empty( $shipment_data['_wms_outward_parcels'] ) && empty( $shipment_data['_wms_inward_parcels'] ) )
				continue;

			foreach ( $label_type as $one_label_type ) {

				if ( empty( $shipment_data[ $one_label_type ] ) )
					continue;

				foreach ( $shipment_data[ $one_label_type ]['_wms_parcels'] as $one_shipment ) {
					if ( empty( $one_shipment['_wms_parcel_skybill_number'] ) ) {
						wms_enqueue_message( sprintf( 'Error while getting tracking numbers from order %s => No skybill number found for this order.', $one_order_id ), 'error' );
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

		$parcels_number = $order->get_meta( '_wms_chronopost_parcels_number', true ) ?: 0;

		$parcels_dimensions = $order->get_meta( '_wms_chronopost_parcels_dimensions', true );
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

	public static function get_ad_valorem_insurance_amount( $order ) {
		$order_insurance_enable = $order->get_meta( '_wms_chronopost_add_insurance', true );
		if ( '' === $order_insurance_enable )
			$order_insurance_enable = get_option( 'wms_chronopost_section_insurance_ad_valorem_enabled' );

		if ( empty( $order_insurance_enable ) )
			return 0;

		$post_meta_insurance_amount = (float) $order->get_meta( '_wms_chronopost_insurance_amount', true );
		if ( $post_meta_insurance_amount === 0 )
			return 0;

		$order_total_amount = (float) $order->get_total() - $order->get_shipping_total();

		$amount_to_insure = ! empty( $post_meta_insurance_amount ) ? $post_meta_insurance_amount : $order_total_amount;

		$amount_to_insure = min( $amount_to_insure, 20000 );

		$wms_config_insurance_min_amount = (float) get_option( 'wms_chronopost_section_insurance_ad_valorem_min_amount' );
		if ( $amount_to_insure < $wms_config_insurance_min_amount )
			return 0;

		return (int) $amount_to_insure * 100;
	}

	public static function get_tracking_url( $tracking_number ) {
		return str_replace( '{tracking_number}', $tracking_number, static::TRACKING_URL );
	}

	private static function get_status_from_code( $code ) {
		if ( empty( $code ) )
			return false;

		$status_from_code = [ 
			'DC' => chronopost_order::WC_WMS_READY_TO_SHIP,
			'PC' => chronopost_order::WC_WMS_TRANSIT,
			'SC' => chronopost_order::WC_WMS_TRANSIT,
			'CL' => chronopost_order::WC_WMS_TRANSIT,
			'TS' => chronopost_order::WC_WMS_TRANSIT,
			'TA' => chronopost_order::WC_WMS_TRANSIT,
			'D' => chronopost_order::WC_WMS_DELIVERED,
		];

		if ( empty( $status_from_code[ $code ] ) )
			return false;

		return $status_from_code[ $code ];
	}

}
