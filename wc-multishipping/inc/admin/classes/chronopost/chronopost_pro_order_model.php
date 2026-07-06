<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use Automattic\WooCommerce\Utilities\OrderUtil;

class chronopost_pro_order_model {

	const ORDERS_PAGE_SIZE = 100;
	private const PRO_PRODUCT_CODE_MATRIX = [
		'02' => '9B',
		'01' => '9A',
		'16' => '9C',
		'44' => '9F',
		'17' => '9D',
		'5X' => '5E',
	];

	private const PRO_ORDER_STATE_PROCESSING = 1;
	private const PRO_ORDER_STATE_SHIPPED = 2;
	private const PRO_ORDER_STATE_DELIVERED = 3;

	private const PRO_SHIPPING_STATUS_BY_STATE = [
		self::PRO_ORDER_STATE_PROCESSING => [
			'label'     => 'En préparation',
			'code'      => 'PRO_1',
			'delivered' => false,
		],
		self::PRO_ORDER_STATE_SHIPPED => [
			'label'     => 'Expédié',
			'code'      => 'PRO_2',
			'delivered' => false,
		],
		self::PRO_ORDER_STATE_DELIVERED => [
			'label'     => 'Livré',
			'code'      => 'PRO_3',
			'delivered' => true,
		],
	];

	private const IGNORED_ORDER_STATES = [
		'trash',
		'draft',
		'auto-draft',
		'wc-checkout-draft',
		'wc-cancelled',
		'wc-completed',
		'wc-shipping',
		'wc-refunded',
		'wc-failed',
	];

	public function get_orders( $date_from = null, $date_to = null, $status = null ) {
		$args   = $this->build_order_query_args( $date_from, $date_to, $status );
		$orders = [];
		$seen   = [];

		$flagged_orders = [];

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			try {
				$flagged_orders = $this->fetch_orders_by_meta_query(
					$args,
					[
						[
							'key'   => chronopost_order::IS_CHRONOPOST_META_KEY,
							'value' => chronopost_order::IS_CHRONOPOST_META_VALUE_TRUE,
						],
					]
				);
			} catch ( \Throwable $exception ) {
				wms_logger(
					sprintf(
						'Chronopost PRO: flagged order lookup failed, falling back to shipping-method lookup. %s',
						$exception->getMessage()
					)
				);
			}
		}

		foreach ( $flagged_orders as $order ) {
			if ( ! chronopost_order::sync_chronopost_order_flag( $order ) ) {
				continue;
			}

			$order_id = $order->get_id();
			$seen[ $order_id ] = true;
			$orders[]          = $this->format_order( $order );
		}

		$historical_order_ids = $this->find_chronopost_order_ids_by_shipping_method( $date_from, $date_to, $status );
		foreach ( $historical_order_ids as $order_id ) {
			if ( isset( $seen[ $order_id ] ) ) {
				continue;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order || ! chronopost_order::sync_chronopost_order_flag( $order ) ) {
				continue;
			}

			$seen[ $order_id ] = true;
			$orders[]          = $this->format_order( $order );
		}

		return $orders;
	}

	public function update_tracking( $order_id, $tracking_number ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$existing = $order->get_meta( '_wms_chronopost_shipment_data', true );

		if ( empty( $existing ) ) {
			$existing = [];
		}

		if ( empty( $existing['_wms_outward_parcels'] ) ) {
			$existing['_wms_outward_parcels'] = [
				'_wms_shipping_provider'           => 'chronopost',
				'_wms_shipping_provider_method_id' => $this->get_order_shipping_method_id( $order ),
				'_wms_reservation_number'          => '',
				'_wms_parcels'                     => [],
			];
		}

		$already_exists = false;
		foreach ( $existing['_wms_outward_parcels']['_wms_parcels'] as &$parcel ) {
			if ( isset( $parcel['_wms_parcel_skybill_number'] ) && $parcel['_wms_parcel_skybill_number'] === $tracking_number ) {
				$already_exists = true;
				break;
			}
		}
		unset( $parcel );

		if ( ! $already_exists ) {
			$existing['_wms_outward_parcels']['_wms_parcels'][] = [
				'_wms_parcel_skybill_number' => $tracking_number,
			];
		}

		$order->update_meta_data( '_wms_chronopost_shipment_data', $existing );
		$order->save();

		$this->mark_order_for_tracking_update( $order_id );

		$order->add_order_note(
			sprintf(
				__( 'Chronopost PRO: tracking number %s added.', 'wc-multishipping' ),
				$tracking_number
			),
			false
		);
	}

	public function update_state( $order_id, $state_int ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$status_slug = $this->get_woocommerce_status_slug_from_pro_state( $state_int );

		if ( empty( $status_slug ) ) {
			wms_logger( sprintf(
				__( 'Chronopost PRO: unknown status ID %d for order %d', 'wc-multishipping' ),
				$state_int,
				$order_id
			) );
			return;
		}

		if ( $order->get_status() !== $status_slug ) {
			$order->update_status( $status_slug, '', true );
		}

		$this->update_pro_shipping_status_meta( $order, $state_int );
	}

	public function add_comment( $order_id, $message ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->add_order_note( $message, false );
	}

	public function get_order_stati() {
		return [
			'processing'                     => (string) self::PRO_ORDER_STATE_PROCESSING,
			$this->get_shipped_status_slug() => (string) self::PRO_ORDER_STATE_SHIPPED,
			'completed'                      => (string) self::PRO_ORDER_STATE_DELIVERED,
		];
	}


	private function get_woocommerce_status_slug_from_pro_state( $state_int ) {
		switch ( (int) $state_int ) {
			case self::PRO_ORDER_STATE_PROCESSING:
				return 'processing';
			case self::PRO_ORDER_STATE_SHIPPED:
				return $this->get_shipped_status_slug();
			case self::PRO_ORDER_STATE_DELIVERED:
				return 'completed';
			default:
				return false;
		}
	}

	private function get_shipped_status_slug() {
		$registered_statuses = wc_get_order_statuses();
		if ( isset( $registered_statuses['wc-shipped'] ) ) {
			return 'shipped';
		}

		return str_replace( 'wc-', '', chronopost_order::WC_WMS_TRANSIT );
	}

	private function update_pro_shipping_status_meta( $order, $state_int ) {
		$status = self::PRO_SHIPPING_STATUS_BY_STATE[ (int) $state_int ] ?? null;
		if ( empty( $status ) ) {
			return;
		}

		$order->update_meta_data( chronopost_parcel::LAST_EVENT_LABEL_META_KEY, $status['label'] );
		$order->update_meta_data( chronopost_parcel::LAST_EVENT_CODE_META_KEY, $status['code'] );
		$order->update_meta_data( chronopost_parcel::LAST_EVENT_DATE_META_KEY, time() );
		$order->update_meta_data(
			chronopost_parcel::IS_DELIVERED_META_KEY,
			$status['delivered'] ? chronopost_parcel::IS_DELIVERED_META_VALUE_TRUE : chronopost_parcel::IS_DELIVERED_META_VALUE_FALSE
		);
		$order->save();
	}

	private function mark_order_for_tracking_update( $order_id ) {
		$encoded_order_ids = get_option( chronopost_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME );
		$order_ids         = [];

		if ( ! empty( $encoded_order_ids ) ) {
			$decoded = json_decode( $encoded_order_ids, true );
			if ( is_array( $decoded ) ) {
				$order_ids = $decoded;
			}
		}

		$order_ids[] = (int) $order_id;
		$order_ids   = array_values( array_unique( array_filter( $order_ids ) ) );

		update_option( chronopost_order::ORDER_IDS_TO_UPDATE_NAME_OPTION_NAME, wp_json_encode( $order_ids ) );
	}

	private function build_order_query_args( $date_from, $date_to, $status ) {
		$args = [
			'limit'    => self::ORDERS_PAGE_SIZE,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => $this->resolve_status_filter( $status ),
			'paginate' => true,
		];

		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$args['date_created'] = strtotime( $date_from ) . '...' . strtotime( $date_to );
		} elseif ( ! empty( $date_from ) ) {
			$args['date_created'] = '>' . strtotime( $date_from );
		} elseif ( ! empty( $date_to ) ) {
			$args['date_created'] = '<' . strtotime( $date_to );
		}

		return $args;
	}

	private function fetch_orders_by_meta_query( array $base_args, array $meta_query ) {
		$orders = [];
		$page   = 1;

		do {
			$args               = $base_args;
			$args['paged']      = $page;
			$args['meta_query'] = $meta_query;

			$result = wc_get_orders( $args );
			if ( ! is_object( $result ) || empty( $result->orders ) ) {
				break;
			}

			$orders = array_merge( $orders, $result->orders );
			$page++;
		} while ( $page <= (int) $result->max_num_pages );

		return $orders;
	}

	private function find_chronopost_order_ids_by_shipping_method( $date_from, $date_to, $status ) {
		global $wpdb;

		$method_ids           = array_keys( chronopost_order::AVAILABLE_SHIPPING_METHODS );
		$status_slugs_with_wc = $this->get_status_slugs_with_prefix( $status );

		if ( empty( $method_ids ) || empty( $status_slugs_with_wc ) ) {
			return [];
		}

		$method_placeholders = implode( ', ', array_fill( 0, count( $method_ids ), '%s' ) );
		$status_placeholders = implode( ', ', array_fill( 0, count( $status_slugs_with_wc ), '%s' ) );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order_table = "{$wpdb->prefix}wc_orders";
			$order_id    = 'orders.id';
			$status_col  = 'orders.status';
			$date_col    = 'orders.date_created_gmt';
		} else {
			$order_table = "{$wpdb->posts}";
			$order_id    = 'orders.ID';
			$status_col  = 'orders.post_status';
			$date_col    = 'orders.post_date_gmt';
		}

		$query = "
			SELECT DISTINCT {$order_id}
			FROM {$order_table} AS orders
			INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items
				ON order_items.order_id = {$order_id}
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS item_meta
				ON item_meta.order_item_id = order_items.order_item_id
			WHERE item_meta.meta_key = 'method_id'
				AND item_meta.meta_value IN ({$method_placeholders})
				AND {$status_col} IN ({$status_placeholders})
		";

		$params = array_merge( $method_ids, $status_slugs_with_wc );

		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$query   .= " AND {$date_col} BETWEEN %s AND %s";
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( $date_from ) );
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( $date_to ) );
		} elseif ( ! empty( $date_from ) ) {
			$query   .= " AND {$date_col} >= %s";
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( $date_from ) );
		} elseif ( ! empty( $date_to ) ) {
			$query   .= " AND {$date_col} <= %s";
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( $date_to ) );
		}

		$query .= " ORDER BY {$order_id} DESC";

		return array_map(
			'intval',
			(array) $wpdb->get_col( $wpdb->prepare( $query, $params ) )
		);
	}

	private function get_order_shipping_method_id( $order ) {
		foreach ( $order->get_shipping_methods() as $shipping ) {
			if ( strpos( $shipping->get_method_id(), 'chronopost' ) !== false ) {
				return $shipping->get_method_id();
			}
		}
		return '';
	}

	private function resolve_status_filter( $status_int ) {
		$all_slugs = array_values( array_diff( array_map(
			function ( $s ) { return str_replace( 'wc-', '', $s ); },
			array_keys( wc_get_order_statuses() )
		), array_map(
			function ( $s ) { return str_replace( 'wc-', '', $s ); },
			self::IGNORED_ORDER_STATES
		) ) );

		if ( $status_int === null ) {
			return $all_slugs;
		}

		$stati = $this->get_order_stati();
		$slug  = array_search( (string) $status_int, $stati, true );

		return $slug ? [ str_replace( 'wc-', '', $slug ) ] : $all_slugs;
	}

	private function get_status_slugs_with_prefix( $status_int ) {
		$all_slugs = array_values( array_diff( array_keys( wc_get_order_statuses() ), self::IGNORED_ORDER_STATES ) );

		if ( $status_int === null ) {
			return $all_slugs;
		}

		$stati = $this->get_order_stati();
		$slug  = array_search( (string) $status_int, $stati, true );

		return $slug ? [ 'wc-' . str_replace( 'wc-', '', $slug ) ] : $all_slugs;
	}

	private function format_order( $order ) {
		$shipping_method_id = $this->get_order_shipping_method_id( $order );
		$pickup_info        = $order->get_meta( chronopost_order::PICKUP_INFO_META_KEY, true );
		$shipment_data      = $order->get_meta( '_wms_chronopost_shipment_data', true );
		$insurance_enabled  = $order->get_meta( '_wms_chronopost_add_insurance', true );
		$insurance_amount   = (float) $order->get_meta( '_wms_chronopost_insurance_amount', true );
		$original_shipping  = $order->get_meta( '_original_shipping_address', true );

		$is_relay = in_array( $shipping_method_id, chronopost_order::ID_SHIPPING_METHODS_RELAY, true );

		$items       = [];
		$items_value = 0;
		foreach ( $order->get_items() as $item ) {
			$items[]      = $item->get_name();
			$items_value += (float) $item->get_total() + (float) $item->get_total_tax();
		}

		$weight = 0;
		if ( ! empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'] ) ) {
			foreach ( $shipment_data['_wms_outward_parcels']['_wms_parcels'] as $parcel ) {
				if ( isset( $parcel['_wms_chronopost_parcel_dimensions']['weight'] ) ) {
					$weight += (float) $parcel['_wms_chronopost_parcel_dimensions']['weight'];
				}
			}
		}
		if ( $weight <= 0 ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product && $product->get_weight() ) {
					$weight += (float) wc_get_weight( $product->get_weight(), 'kg' ) * $item->get_quantity();
				}
			}
		}

		$stati          = $this->get_order_stati();
		$status_slug    = $order->get_status();
		$status_int     = $stati[ $status_slug ] ?? '0';

		$date_created = $order->get_date_created();
		$shipping_address = [
			'company'   => $order->get_shipping_company(),
			'country'   => $order->get_shipping_country(),
			'firstname' => $order->get_shipping_first_name(),
			'lastname'  => $order->get_shipping_last_name(),
			'postcode'  => $order->get_shipping_postcode(),
			'city'      => $order->get_shipping_city(),
			'address'   => $order->get_shipping_address_1(),
			'address_2' => $order->get_shipping_address_2(),
		];

		if ( $original_shipping && $is_relay ) {
			$shipping_address = array_merge( $shipping_address, [
				'company'   => $original_shipping['company'] ?? '',
				'country'   => $original_shipping['country'] ?? '',
				'postcode'  => $original_shipping['postcode'] ?? '',
				'city'      => $original_shipping['city'] ?? '',
				'address'   => $original_shipping['address_1'] ?? '',
				'address_2' => $original_shipping['address_2'] ?? '',
			] );
		}

		$product_code = $this->map_pro_product_code( $this->get_order_product_code( $shipping_method_id ) );
		$relay_id     = '';
		if ( $is_relay ) {
			if ( ! empty( $pickup_info['pickup_id'] ) ) {
				$relay_id = $pickup_info['pickup_id'];
			} elseif ( ! empty( $pickup_info['id'] ) ) {
				$relay_id = $pickup_info['id'];
			}
		}

		return [
			'order_id'      => $order->get_id(),
			'status'        => [
				'id'    => $status_int,
				'label' => wc_get_order_status_name( $status_slug ),
			],
			'recipient'     => [
				'type'      => ! empty( $shipping_address['company'] ) ? 'company' : 'individual',
				'company'   => $shipping_address['company'],
				'country'   => $shipping_address['country'],
				'firstname' => $shipping_address['firstname'],
				'lastname'  => $shipping_address['lastname'],
				'email'     => $order->get_billing_email(),
				'phone'     => $this->get_order_phone( $order ),
				'postcode'  => $shipping_address['postcode'],
				'city'      => $shipping_address['city'],
				'address'   => $shipping_address['address'],
				'address_2' => $shipping_address['address_2'],
			],
			'weight'        => round( $weight, 3 ),
			'insurance'     => [
				'has_insurance' => $insurance_enabled === 'yes' || $insurance_enabled === '1',
				'value'         => $insurance_amount > 0 ? (int) ceil( $insurance_amount ) : 0,
			],
			'shipping_infos' => [
				'type'         => $is_relay ? 'relay' : 'home',
				'product_code' => $product_code,
				'relay'        => $relay_id,
			],
			'items'         => $items,
			'items_value'   => round( $items_value, 2 ),
			'date_created'  => $date_created ? $date_created->format( 'Y-m-d H:i:s' ) : '',
		];
	}

	private function get_order_product_code( $shipping_method_id ) {
		if ( empty( $shipping_method_id ) || ! function_exists( 'WC' ) || ! WC() || ! WC()->shipping() ) {
			return 'N/A';
		}

		$shipping_methods = WC()->shipping()->load_shipping_methods();
		if ( empty( $shipping_methods[ $shipping_method_id ] ) ) {
			return 'N/A';
		}

		$shipping_method = $shipping_methods[ $shipping_method_id ];
		if ( ! method_exists( $shipping_method, 'get_product_code' ) ) {
			return 'N/A';
		}

		return (string) $shipping_method->get_product_code();
	}

	private function map_pro_product_code( $product_code ) {
		$product_code = (string) $product_code;

		return self::PRO_PRODUCT_CODE_MATRIX[ $product_code ] ?? $product_code;
	}

	private function get_order_phone( $order ) {
		$shipping_phone = method_exists( $order, 'get_shipping_phone' ) ? $order->get_shipping_phone() : '';
		$phone          = $shipping_phone ?: $order->get_billing_phone();
		$phone          = preg_replace( '/[^0-9+]/', '', (string) $phone );

		return trim( (string) $phone );
	}
}
