<?php


namespace WCMultiShipping\inc\front\pickup\abstract_classes;


abstract class abstract_pickup_widget {
	const PICKUP_LOCATION_SESSION_VAR_NAME = '';

	const SHIPPING_PROVIDER_ID = '';

	abstract protected static function get_order_class();

	abstract protected function get_pickup_point();

	abstract protected static function is_shipping_method_enabled();

	public static function register_hooks() {

		$page = new static();

		if ( ! $page::is_shipping_method_enabled() )
			return;

		add_filter( 'woocommerce_shipping_methods', [ $page, 'add_wms_shipping_methods' ] );

		add_action( 'woocommerce_after_shipping_rate', [ $page, 'add_widget_pickup_point' ], 10, 2 );

		add_action( 'wp_ajax_wms_get_pickup_point', [ $page, 'get_pickup_point' ] );
		add_action( 'wp_ajax_nopriv_wms_get_pickup_point', [ $page, 'get_pickup_point' ] );

		add_action( 'wp_ajax_wms_select_pickup_point', [ $page, 'select_pickup_point' ] );
		add_action( 'wp_ajax_nopriv_wms_select_pickup_point', [ $page, 'select_pickup_point' ] );

		add_filter( 'woocommerce_order_button_html', [ $page, 'prevent_place_order_button' ], 10, 2 );

		add_action( 'woocommerce_checkout_order_created', [ $page, 'apply_pickup_address' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $page, 'apply_pickup_address' ], 20, 1 );

		add_action( 'woocommerce_after_checkout_validation', [ $page, 'prevent_checkout_process' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $page, 'prevent_checkout_process' ], 10, 2 );

		add_action( 'woocommerce_order_status_processing', [ $page, 'save_pickup_info' ], 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', [ $page, 'save_pickup_info' ], 10, 1 );
		add_action( 'woocommerce_checkout_order_created', [ $page, 'apply_pickup_address' ], 10, 1 );

		add_action( 'woocommerce_store_api_checkout_order_processed', [ $page, 'save_pickup_info' ], 30, 1 );

		add_action( 'init', [ $page, 'register_wms_post_statuses' ] );
		add_filter( 'wc_order_statuses', [ $page, 'register_wms_order_statuses' ] );
	}

	public function add_wms_shipping_methods( $shipping_methods ) {
		$shipping_methods_class = static::get_shipping_methods_class();

		$provider_shipping_methods = $shipping_methods_class::load_shipping_methods();

		foreach ( $provider_shipping_methods as $one_shipping_method ) {
			$shipping_methods[ $one_shipping_method::ID ] = get_class( $one_shipping_method );
		}

		return $shipping_methods;
	}


	public function select_pickup_point() {

		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_pickup_selection' ) )
			wp_die( 'Invalid nonce' );

		$pickup_id = wms_get_var( 'cmd', 'pickup_id', '' );
		$pickup_name = wms_get_var( 'string', 'pickup_name', '' );
		$pickup_address = wms_get_var( 'string', 'pickup_address', '' );
		$pickup_city = wms_get_var( 'string', 'pickup_city', '' );
		$pickup_zip_code = wms_get_var( 'cmd', 'pickup_zipcode', '' );
		$pickup_country = wms_get_var( 'cmd', 'pickup_country', 'FR' );
		$pickup_provider = wms_get_var( 'cmd', 'pickup_provider', '' );


		if ( strlen( 4 == $pickup_zip_code ) )
			$pickup_country = "0" . strval( $pickup_zip_code );

		$pickup_info = [ 
			'pickup_id' => $pickup_id,
			'pickup_name' => $pickup_name,
			'pickup_address' => $pickup_address,
			'pickup_city' => $pickup_city,
			'pickup_zipcode' => $pickup_zip_code,
			'pickup_country' => $pickup_country,
			'pickup_provider' => $pickup_provider
		];

		if ( empty( $pickup_id ) || empty( $pickup_name ) || empty( $pickup_address ) || empty( $pickup_city ) || empty( $pickup_zip_code ) || empty( $pickup_country ) ) {
			wp_send_json( [ 
				'error' => true,
				'error_message' => __( 'A parameter is missing', 'wc-multishipping' ),
			] );
		}

		WC()->session->set( self::PICKUP_LOCATION_SESSION_VAR_NAME, $pickup_info );

		wp_send_json( [ 
			'error' => false,
			'error_message' => '',
		] );
	}

	public static function add_widget_pickup_point( $method, $index ) {

		if ( ! is_checkout() )
			return;

		$order_class = static::get_order_class();

		$method_id = $method->get_id();
		$method_id = substr( $method_id, 0, strpos( $method_id, ':' ) );
		$available_relay_shipping_methods = $order_class::ID_SHIPPING_METHODS_RELAY;
		if ( ! in_array( $method_id, $available_relay_shipping_methods ) )
			return;


		$wc_session = WC()->session;
		$chosen_shipping_methods = substr( $wc_session->chosen_shipping_methods[ $index ], 0, strpos( $wc_session->chosen_shipping_methods[ $index ], ':' ) );

		if ( ! in_array( $chosen_shipping_methods, $available_relay_shipping_methods ) || $chosen_shipping_methods != $method_id ) {
			return;
		}

		wp_enqueue_script( 'backbone-modal', WMS_PLUGINS_URL . '/woocommerce/assets/js/admin/backbone-modal.js', [ 'jquery', 'wp-util', 'backbone' ] );

		global $woocommerce;
		$countries_obj = new \WC_Countries();
		$countries = $countries_obj->__get( 'countries' );

		$pickup_info = WC()->session->get( self::PICKUP_LOCATION_SESSION_VAR_NAME );

		$map_to_use = get_option( 'wms_' . static::SHIPPING_PROVIDER_ID . '_section_pickup_points_map_type', 'openstreetmap' );

		if ( 'mondial_relay_map' == $map_to_use && static::SHIPPING_PROVIDER_ID == 'mondial_relay' ) {

			$modal_id = 'wms_pickup_open_modal_mondial_relay';
			include WMS_FRONT_PARTIALS . 'pickups' . DS . 'mondial_relay' . DS . 'widget.php';
		} else if ( 'openstreetmap' == $map_to_use ) {

			$modal_id = 'wms_pickup_open_modal_openstreetmap';
			include WMS_FRONT_PARTIALS . 'pickups' . DS . 'openstreetmap' . DS . 'widget.php';
		} else {

			$google_maps_api_key = get_option( 'wms_' . static::SHIPPING_PROVIDER_ID . '_section_pickup_points_google_maps_api_key' );
			if ( ! empty( $google_maps_api_key ) ) {

				$modal_id = 'wms_pickup_open_modal_google_maps';
				include WMS_FRONT_PARTIALS . 'pickups' . DS . 'google_maps' . DS . 'widget.php';
			} else {
				if ( current_user_can( 'administrator' ) ) {
					echo '<div id="wms_google_maps_issue">' . sprintf( __( 'Can\'t display the pick up selection button. No Google Maps Api Key set in your %s plugin configuration.', 'wc-multishipping' ), static::SHIPPING_PROVIDER_NAME ) . '</div>';
					echo '<div id="wms_google_maps_access_config"><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . static::SHIPPING_PROVIDER_ID ) . '" >' . __( 'Access configuration', 'wc-multishipping' ) . '</a></div>';
				} else {
					echo '<div id="wms_google_maps_issue">' . sprintf( __( 'Can\'t display the pick up selection button. No Google Maps Api Key set in the %s plugin configuration. Please contact the website admin.', 'wc-multishipping' ), static::SHIPPING_PROVIDER_NAME ) . '</div>';
				}

				return;
			}
		}
	}

	public function save_pickup_info( $order_id ) {

		if ( ! is_int( $order_id ) )
			$order_id = $order_id->get_id();

		$order_class = static::get_order_class();

		$method_id = $order_class::get_shipping_method_name( wc_get_order( $order_id ) );
		$available_relay_shipping_methods = $order_class::ID_SHIPPING_METHODS_RELAY;
		if ( ! in_array( $method_id, $available_relay_shipping_methods ) )
			return;

		$session = WC()->session;
		if ( ! $session )
			return;

		$pickup_info = $session->get( self::PICKUP_LOCATION_SESSION_VAR_NAME );
		if ( empty( $pickup_info ) )
			return;

		array_walk(
			$pickup_info, function ($one_info) {
				esc_sql( wms_display_value( $one_info ) );
			}
		);

		$order = wc_get_order( $order_id );
		$order->update_meta_data( $order_class::PICKUP_INFO_META_KEY, $pickup_info );
		$order->save();

		WC()->session->set( self::PICKUP_LOCATION_SESSION_VAR_NAME, null );
	}

	public function prevent_place_order_button( $order_button ) {
		$order_class = static::get_order_class();

		if ( ! WC()->cart->needs_shipping() )
			return $order_button;

		$selected_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $selected_shipping_method ) || ! is_array( $selected_shipping_method ) )
			return $order_button;

		$method_id = substr( reset( $selected_shipping_method ), 0, strpos( reset( $selected_shipping_method ), ':' ) );
		if ( empty( $selected_shipping_method ) || ! in_array( $method_id, $order_class::ID_SHIPPING_METHODS_RELAY ) ) {
			return $order_button;
		}

		$pickup_info = WC()->session->get( self::PICKUP_LOCATION_SESSION_VAR_NAME );
		if ( ! empty( $pickup_info ) )
			return $order_button;

		$textButton = esc_html__( 'Please select a pick-up point', 'wc-multishipping' );

		return '<button type="submit" disabled class="button alt" name="woocommerce_checkout_place_order" id="place_order">' . wms_display_value( $textButton ) . '</button>';
	}

	public function prevent_checkout_process( $fields = [], $errors = null ) {

		if ( ! WC()->cart->needs_shipping() )
			return;


		$selected_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $selected_shipping_method ) || ! is_array( $selected_shipping_method ) )
			return;


		$selected_shipping_method_value = reset( $selected_shipping_method );
		$selected_shipping_method_name = explode( ":", $selected_shipping_method_value )[0];

		$order_class = static::get_order_class();
		if ( empty( $selected_shipping_method ) || ! in_array( $selected_shipping_method_name, $order_class::ID_SHIPPING_METHODS_RELAY ) )
			return;


		$pickup_info = WC()->session->get( self::PICKUP_LOCATION_SESSION_VAR_NAME );

		if ( empty( $pickup_info ) || strpos( $selected_shipping_method_name, $pickup_info['pickup_provider'] ) === false ) {
			if ( $errors )
				$errors->add( 'validation', esc_html__( 'Please select a pick-up point', 'wc-multishipping' ) );
			else
				throw new \Exception( esc_html__( 'Please select a pick-up point', 'wc-multishipping' ) );
		}
	}

	public function apply_pickup_address( $order ) {

		$order_class = static::get_order_class();
		if ( ! $order_class::is_wms_shipping_method( $order ) )
			return;


		$pickup_data = WC()->session->get( self::PICKUP_LOCATION_SESSION_VAR_NAME );

		$order->set_shipping_company( $pickup_data['pickup_name'] );
		$order->set_shipping_address_1( $pickup_data['pickup_address'] );
		$order->set_shipping_address_2( '' );
		$order->set_shipping_postcode( $pickup_data['pickup_zipcode'] );
		$order->set_shipping_city( $pickup_data['pickup_city'] );
		$order->set_shipping_country( $pickup_data['pickup_country'] );

		$order->save();
	}

	public function register_wms_post_statuses() {
		$order_class = static::get_order_class();

		register_post_status( $order_class::WC_WMS_TRANSIT, [ 
			'label' => __( $order_class::WC_WMS_TRANSIT_LABEL, 'wc-multishipping' ),
			'public' => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list' => true,
			'exclude_from_search' => false,
			'label_count' => _n_noop(
				'In-Transit <span class="count">(%s)</span>',
				'In-Transit <span class="count">(%s)</span>',
				'wc-multishipping'
			),
		] );
		register_post_status( $order_class::WC_WMS_DELIVERED, [ 
			'label' => __( $order_class::WC_WMS_DELIVERED_LABEL, 'wc-multishipping' ),
			'public' => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list' => true,
			'exclude_from_search' => false,
			'label_count' => _n_noop(
				'Delivered <span class="count">(%s)</span>',
				'Delivered <span class="count">(%s)</span>',
				'wc-multishipping'
			),
		] );
		register_post_status( $order_class::WC_WMS_ANOMALY, [ 
			'label' => __( $order_class::WC_WMS_ANOMALY_LABEL, 'wc-multishipping' ),
			'public' => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list' => true,
			'exclude_from_search' => false,
			'label_count' => _n_noop(
				'Anomaly <span class="count">(%s)</span>',
				'Anomaly <span class="count">(%s)</span>',
				'wc-multishipping'
			),
		] );
		register_post_status( $order_class::WC_WMS_READY_TO_SHIP, [ 
			'label' => __( $order_class::WC_WMS_READY_TO_SHIP_LABEL, 'wc-multishipping' ),
			'public' => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list' => true,
			'exclude_from_search' => false,
			'label_count' => _n_noop(
				'Ready to ship <span class="count">(%s)</span>',
				'Ready to ship <span class="count">(%s)</span>',
				'wc-multishipping'
			),
		] );
	}

	public function register_wms_order_statuses( $woo_statuses ) {
		$order_class = static::get_order_class();

		$new_statuses = [];
		$new_statuses[ $order_class::WC_WMS_TRANSIT ] = __( $order_class::WC_WMS_TRANSIT_LABEL, 'wc-multishipping' );
		$new_statuses[ $order_class::WC_WMS_DELIVERED ] = __( $order_class::WC_WMS_DELIVERED_LABEL, 'wc-multishipping' );
		$new_statuses[ $order_class::WC_WMS_ANOMALY ] = __( $order_class::WC_WMS_ANOMALY_LABEL, 'wc-multishipping' );
		$new_statuses[ $order_class::WC_WMS_READY_TO_SHIP ] = __( $order_class::WC_WMS_READY_TO_SHIP_LABEL, 'wc-multishipping' );

		return array_merge( $woo_statuses, $new_statuses );
	}
}
