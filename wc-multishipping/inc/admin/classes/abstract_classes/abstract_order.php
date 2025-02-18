<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

use Automattic\WooCommerce\Utilities\OrderUtil;

abstract class abstract_order {
	const PICKUP_INFO_META_KEY = '';

	const UPDATE_STATUS_PERIOD = '-90 days';
	const ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME = 'wms_order_ids_to_update_tracking';

	const SHIPPING_PROVIDER_DISPLAYED_NAME = '';
	const SHIPPING_PROVIDER_ID = '';

	public $helper;

	public $from = '';
	public $join = [];
	public $where = [];

	abstract static protected function get_integration_helper();

	abstract static protected function delete_label_meta_from_tracking_numbers( $tracking_numbers );

	public function __construct() {
		global $wpdb;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {

			$this->from = "{$wpdb->prefix}wc_orders";

			$this->join[ "{$wpdb->prefix}woocommerce_order_items" ] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items
            ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id";

			$this->join[ "{$wpdb->prefix}woocommerce_order_itemmeta" ] = "JOIN {$wpdb->prefix}woocommerce_order_itemmeta 
		    ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id";

			$this->join[ "{$wpdb->prefix}wc_orders_addresses" ] = "INNER JOIN {$wpdb->prefix}wc_order_addresses
            ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}wc_order_addresses.order_id";

			$this->where[] = "({$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
            AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . "
            AND ({$wpdb->prefix}wc_orders.status <> 'trash' AND {$wpdb->prefix}wc_orders.status <> 'auto-draft' AND {$wpdb->prefix}wc_orders.status <> 'wc-checkout-draft'))";
		} else {
			$this->from = "{$wpdb->prefix}posts";

			$this->join[ "{$wpdb->prefix}woocommerce_order_items" ] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items
            ON {$wpdb->prefix}woocommerce_order_items.order_id = {$wpdb->prefix}posts.ID";

			$this->join[ "{$wpdb->prefix}woocommerce_order_itemmeta" ] = "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS {$wpdb->prefix}woocommerce_order_itemmeta
            ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id";

			$this->join[ "{$wpdb->prefix}postmeta" ] = "INNER JOIN {$wpdb->prefix}postmeta
            ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}woocommerce_order_items.order_id";

			$this->where[] = "({$wpdb->prefix}posts.post_type = 'shop_order' OR {$wpdb->prefix}posts.post_type = 'shop_order_placehold') 
        	AND ({$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
			AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . ")
			AND ({$wpdb->prefix}posts.post_status <> 'trash' AND {$wpdb->prefix}posts.post_status <> 'auto-draft')";
		}
	}

	public function get_orders( $current_page = 10, $per_page = 0, $args = [], $filters = [] ) {
		global $wpdb;

		$additional_where_conditions = $this->add_where_conditions( $filters );
		if ( ! empty( $additional_where_conditions ) )
			$this->where = array_merge( $additional_where_conditions, $this->where );

		$query = "SELECT DISTINCT {$wpdb->prefix}woocommerce_order_items.order_id, " . $this->from . ".*";
		$query .= " FROM $this->from ";
		$query .= implode( ' ', $this->join );
		$query .= ' WHERE ' . implode( ' AND ', $this->where );

		$query .= $this->add_order_by( $args );

		if ( 0 < $current_page && 0 < $per_page ) {
			$offset = ( $current_page - 1 ) * $per_page;
			$query .= " LIMIT $per_page OFFSET $offset";
		}

		$results = $wpdb->get_results( $query );

		$orders_id = [];
		if ( $results ) {
			foreach ( $results as $result ) {
				$orders_id[] = $result->order_id;
			}
		}

		return $orders_id;
	}

	public static function count_all_orders() {
		global $wpdb;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {

			$query = "SELECT COUNT(DISTINCT {$wpdb->prefix}woocommerce_order_items.order_id) AS nb
                    FROM {$wpdb->prefix}wc_orders
                    
                    JOIN {$wpdb->prefix}woocommerce_order_items
                    ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
                    
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta
                    ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
                    
                    WHERE ({$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
                    AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . ")
                    AND ({$wpdb->prefix}wc_orders.status <> 'trash'
                    AND {$wpdb->prefix}wc_orders.status <> 'auto-draft')";
		} else {

			$query = "SELECT COUNT(DISTINCT {$wpdb->prefix}woocommerce_order_items.order_id) AS nb
                    FROM {$wpdb->prefix}posts
                    
                    JOIN {$wpdb->prefix}woocommerce_order_items
                    ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}woocommerce_order_items.order_id
                    
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta
                    ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
                    
                    WHERE ({$wpdb->prefix}posts.post_type = 'shop_order' OR {$wpdb->prefix}posts.post_type = 'shop_order_placehold') 
                    AND ({$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
                    AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . ")
                    AND ({$wpdb->prefix}posts.post_status <> 'trash'
                    AND {$wpdb->prefix}posts.post_status <> 'auto-draft')";
		}

		$result = $wpdb->get_row( $query );

		if ( ! empty( $result ) && property_exists( $result, 'nb' ) ) {
			return (int) $result->nb;
		}

		return 0;
	}


	static function get_shipping_method_sql_condition() {
		$class_to_call = get_called_class();
		$chrono_shipping_methods = array_keys( $class_to_call::AVAILABLE_SHIPPING_METHODS );

		array_walk( $chrono_shipping_methods, function (&$text) {
			$text = "'" . $text . "'";
		} );

		return '(' . implode( ',', $chrono_shipping_methods ) . ')';
	}

	private function add_where_conditions( $request_filters = [] ) {
		$filters = [];
		global $wpdb;

		if ( empty( $request_filters ) )
			return $filters;

		if ( ! empty( $request_filters['search'] ) ) {

			$search = sanitize_text_field( $request_filters['search'] );


			$filters['search'] = '(';

			$filters['search'] .= "({$wpdb->prefix}woocommerce_order_items.order_id = '%{$search}%')";


			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {

				$filters['search'] .= " OR (DATE_FORMAT({$wpdb->prefix}wc_orders.date_created_gmt, '%m-%d-%Y') LIKE '%{$search}%')";



				$filters['search'] .= " 
            OR {$wpdb->prefix}wc_order_addresses.first_name LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.last_name LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.address_1 LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.address_2 LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.city LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.state LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.postcode LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.country LIKE '%{$search}%'
			OR {$wpdb->prefix}wc_order_addresses.email LIKE '%{$search}%'";

				$filters['search'] .= " OR ({$wpdb->prefix}woocommerce_order_items.order_item_type = 'shipping'
            AND {$wpdb->prefix}woocommerce_order_items.order_item_name LIKE '%{$search}%')";

				$filters['search'] .= " OR ({$wpdb->prefix}wc_orders.status LIKE '%{$search}%')";
				$filters['search'] .= ')';
			} else {

				$filters['search'] .= " OR (DATE_FORMAT({$wpdb->prefix}posts.post_date_gmt, '%m-%d-%Y') LIKE '%{$search}%')";

				$filters['search'] .= " OR (
            ({$wpdb->prefix}postmeta.meta_key = '_shipping_first_name'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_last_name'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_address_1'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_address_2'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_city'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_country'
			OR {$wpdb->prefix}postmeta.meta_key = '_shipping_postcode')
			AND {$wpdb->prefix}postmeta.meta_value LIKE '%{$search}%')";

				$filters['search'] .= " OR ({$wpdb->prefix}woocommerce_order_items.order_item_type = 'shipping'
            AND {$wpdb->prefix}woocommerce_order_items.order_item_name LIKE '%{$search}%')";

				$filters['search'] .= " OR ({$wpdb->prefix}posts.post_status LIKE '%{$search}%')";
				$filters['search'] .= ')';
			}
		}

		foreach ( $request_filters as $one_filter_type => $one_filter_value ) {

			$filter_values = is_array( $one_filter_value ) ? $one_filter_value : [ $one_filter_value ];

			$filter_values = array_filter( $filter_values, function ($value) {
				return ! empty( $value );
			} );

			$request_filters[ $one_filter_type ] = array_map( 'sanitize_text_field', $filter_values );
		}

		if ( ! empty( $request_filters['shipping_country'] ) && ( is_array( $request_filters['shipping_country'] ) ) ) {

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$filters[] = "{$wpdb->prefix}wc_order_addresses.country IN ('" . implode( "', '", $request_filters['shipping_country'] ) . "')";
			} else {
				$join_alias = "{$wpdb->prefix}postmeta";

				if ( ! empty( $filters['search'] ) ) {
					$this->join[ $join_alias ] = "INNER JOIN {$wpdb->prefix}postmeta AS postmeta2
				ON {$wpdb->prefix}postmeta.post_id = postmeta2.post_id";
					$join_alias = "postmeta2";
				}

				$filters[] = "($join_alias.meta_key = '_shipping_country'
            AND $join_alias.meta_value IN ('" . implode( "', '", $request_filters['shipping_country'] ) . "'))";
			}
		}

		if ( ! empty( $request_filters['shipping_methods'] ) && ( is_array( $request_filters['shipping_methods'] ) ) ) {

			$filters[] = "{$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
            AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value
            IN ('" . implode( "', '", $request_filters['shipping_methods'] ) . "')";
		}

		if ( ! empty( $request_filters['woo_status'] ) && ( is_array( $request_filters['woo_status'] ) ) ) {

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$filters[] = "{$wpdb->prefix}wc_orders.status IN ('" . implode( "', '", $request_filters['woo_status'] ) . "')";
			} else {
				$filters[] = "{$wpdb->prefix}posts.post_status IN ('" . implode(
					"', '",
					$request_filters['woo_status']
				) . "')";
			}
		}


		return $filters;
	}

	private function add_order_by( $args ) {
		global $wpdb;

		if ( empty( $args['orderby'] ) )
			return OrderUtil::custom_orders_table_usage_is_enabled() ? " ORDER BY {$wpdb->prefix}wc_orders.date_created_gmt DESC " : " ORDER BY {$wpdb->prefix}posts.post_date DESC ";


		switch ( $args['orderby'] ) {
			case 'wms_id':
				$ord = 'woocommerce_order_items.order_id';
				break;
			case 'wms_date':
				$ord = OrderUtil::custom_orders_table_usage_is_enabled() ? "wc_orders.date_created_gmt" : "posts.post_date";
				break;
			case 'wms_shipping_method':
				$ord = 'woocommerce_order_items.order_item_name';
				break;
			case 'wms_woo_status':
				$ord = OrderUtil::custom_orders_table_usage_is_enabled() ? "wc_orders.status" : "posts.post_status";
				break;
			default:
				$ord = OrderUtil::custom_orders_table_usage_is_enabled() ? "wc_orders.date_created_gmt" : "posts.post_date";
				break;
		}
		if ( empty( $ord ) ) {
			switch ( $args['orderby'] ) {
				case 'wms_customer':
					$where = OrderUtil::custom_orders_table_usage_is_enabled() ? "{$wpdb->prefix}wc_order_addresses.first_name = " : "postmeta.meta_key = '_shipping_first_name'";
					break;
				case 'wms_address':
					$where = "postmeta.meta_key = '_shipping_address_1'";
					break;
				case 'wms_country':
					$where = "postmeta.meta_key = '_shipping_country'";
					break;
				case 'wms_shipping_method':
					$where = "woocommerce_order_items.order_item_type = 'shipping'";
					break;
				default:
					$where = '';
					break;
			}
			if ( ! empty( $this->where ) ) {
				$this->join[ "{$wpdb->prefix}postmeta" ] = "INNER JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}order_id.id AND " . $wpdb->prefix . $where;
				$ord = 'postmeta.meta_value';
			}
		}

		$ord = " ORDER BY {$wpdb->prefix}" . $ord . ' ';

		if ( ! empty( $args['order'] ) )
			$ord .= $args['order'] . ' ';

		return $ord;
	}

	public static function get_all_countries() {
		global $wpdb;
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return $wpdb->get_results(
				"SELECT DISTINCT country
						FROM {$wpdb->prefix}wc_order_addresses
						INNER JOIN {$wpdb->prefix}wc_orders
						ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}wc_order_addresses.order_id
						JOIN {$wpdb->prefix}woocommerce_order_items
        				ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
        			JOIN {$wpdb->prefix}woocommerce_order_itemmeta
        				ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
  						AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition()
			);
		} else {
			return $wpdb->get_col(
				"SELECT DISTINCT meta_value
						FROM {$wpdb->prefix}posts
						INNER JOIN {$wpdb->prefix}postmeta
						ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}posts.ID
						WHERE meta_key = '_shipping_country'"
			);
		}
	}

	public static function get_all_woo_status() {
		global $wpdb;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return $wpdb->get_col(
				"SELECT DISTINCT {$wpdb->prefix}wc_orders.status
					FROM {$wpdb->prefix}wc_orders
         			JOIN {$wpdb->prefix}woocommerce_order_items
        				ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
        			JOIN {$wpdb->prefix}woocommerce_order_itemmeta
        				ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
  						AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . "
					ORDER BY {$wpdb->prefix}wc_orders.status ASC"
			);
		} else {
			return $wpdb->get_col(
				"SELECT DISTINCT {$wpdb->prefix}posts.post_status
					FROM {$wpdb->prefix}posts
         			JOIN {$wpdb->prefix}woocommerce_order_items
        				ON {$wpdb->prefix}posts.id = {$wpdb->prefix}woocommerce_order_items.order_id
        			JOIN {$wpdb->prefix}woocommerce_order_itemmeta
        				ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id
					WHERE {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = 'method_id'
  						AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_value IN " . self::get_shipping_method_sql_condition() . "
					ORDER BY {$wpdb->prefix}posts.post_status ASC"
			);
		}
	}


	public static function get_shipping_method_name( $order ) {
		if ( empty( $order ) ) {
			wms_enqueue_message( __( 'Can not get shipping method name => No order selected.', 'wc-multishipping' ) );

			return false;
		}

		if ( is_int( $order ) )
			$order = wc_get_order( $order );

		$order_shipping_method = $order->get_shipping_methods();
		$shipping_method = reset( $order_shipping_method );

		return empty( $shipping_method ) ? false : $shipping_method->get_method_id();
	}


	public static function register_parcels_labels( $order, $is_return_order = false ) {
		if ( empty( $order ) || ! isset( $is_return_order ) )
			return false;

		$labels_information = static::get_labels_from_order( $order->get_id() );
		if ( empty( $labels_information ) && $is_return_order ) {
			wms_enqueue_message( sprintf( __( 'Inward label can not be generated for order n°%s as the order has not been sent yet', 'wc-multishipping' ), $order->get_id() ), 'error' );

			return false;
		}

		$integration_helper = static::get_integration_helper();
		$parcel_class = $integration_helper->get_parcel_class();

		if ( ! $parcel_class::register_parcels_from_order( $order, $is_return_order ) )
			return false;
		if ( ! $parcel_class::store_parcel_labels_from_order( $order, $is_return_order ) )
			return false;

		wms_enqueue_message( __( 'Label successfully generated', 'wc-multishipping' ) );



		return true;
	}

	public static function is_wms_shipping_method( $order ) {
		$method_id = self::get_shipping_method_name( $order );
		$available_relay_shipping_methods = static::ID_SHIPPING_METHODS_RELAY;

		if ( ! in_array( $method_id, $available_relay_shipping_methods ) )
			return false;

		return true;
	}

	public static function get_tracking_numbers_from_order( $order_id, $is_return_order = false ) {
		if ( empty( $order_id ) )
			return '';

		$order = wc_get_order( $order_id );
		$shipment_data = $order->get_meta( '_wms_' . static::SHIPPING_PROVIDER_ID . '_shipment_data', true );
		if ( empty( $shipment_data ) || empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'] ) )
			return false;


		$label_type = ( ! $is_return_order ? '_wms_outward_parcels' : '_wms_inward_parcels' );
		$tracking_numbers = [];

		foreach ( $shipment_data[ $label_type ]['_wms_parcels'] as $one_parcel ) {
			if ( empty( $one_parcel ) || empty( $one_parcel['_wms_parcel_skybill_number'] ) )
				continue;

			$tracking_numbers[] = $one_parcel['_wms_parcel_skybill_number'];
		}

		return $tracking_numbers;
	}

	public static function get_labels_from_order( $order_id, $is_return_order = false ) {

		$tracking_numbers = self::get_tracking_numbers_from_order( $order_id, $is_return_order );
		if ( empty( $tracking_numbers ) )
			return false;

		$integration_helper = static::get_integration_helper();
		$label_class = $integration_helper->get_label_class();
		$labels = [];

		foreach ( $tracking_numbers as $one_tracking_number ) {
			if ( empty( $one_tracking_number ) )
				continue;

			$label_information = $label_class::get_info_from_tracking_numbers( $one_tracking_number );
			if ( empty( $label_information ) )
				continue;


			$labels[ $one_tracking_number ]['outward'] = $label_information;
		}

		return $labels;
	}

	function add_orders_to_update() {
		global $wpdb;

		$fromDate = date( 'Y-m-d', strtotime( self::UPDATE_STATUS_PERIOD ) );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$this->where[] = "{$wpdb->prefix}wc_orders.date_created_gmt > '" . $fromDate . "'";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-completed')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-cancelled')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-refunded')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-failed')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-checkout-draft')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'wc-pending')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'trash')";
			$this->where[] = "({$wpdb->prefix}wc_orders.status <> 'auto-draft')";
		} else {
			$this->where[] = "{$wpdb->prefix}posts.post_date > '" . $fromDate . "'";
			$this->where[] = "({$wpdb->prefix}postmeta.meta_value IS NULL OR {$wpdb->prefix}postmeta.meta_value = '0')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-completed')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-cancelled')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-refunded')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-failed')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-checkout-draft')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'wc-pending')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'trash')";
			$this->where[] = "({$wpdb->prefix}posts.post_status <> 'auto-draft')";
		}

		$matching_order_ids = $this->get_orders();
		if ( empty( $matching_order_ids ) )
			return;

		$url_id_to_update_encoded = get_option( static::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME );
		if ( ! empty( $url_id_to_update_encoded ) ) {
			$url_id_to_update = json_decode( $url_id_to_update_encoded, true );

			if ( ! is_array( $url_id_to_update ) ) {
				$url_id_to_update = [ $url_id_to_update ];
			}

			$new_order_ids_to_update = array_merge( $url_id_to_update, $matching_order_ids );
			$new_order_ids_to_update = array_unique( $new_order_ids_to_update );
		} else {
			$new_order_ids_to_update = $matching_order_ids;
		}

		$encoded_matching_orders_ids = is_array( $new_order_ids_to_update ) ? json_encode( $new_order_ids_to_update ) : '[]';

		update_option( static::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME, $encoded_matching_orders_ids );
	}
}
