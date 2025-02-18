<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

use WCMultiShipping\inc\admin\classes\config\config_class;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

abstract class abstract_helper {
	const SHIPPING_PROVIDER_DISPLAYED_NAME = '';
	const SHIPPING_PROVIDER_ID = '';

	abstract protected static function get_meta_box_class();

	abstract protected static function get_label_class();

	abstract protected static function get_order_class();

	abstract protected static function get_parcel_class();

	abstract protected static function get_api_helper_class();

	abstract protected static function get_shipping_methods_class();

	abstract protected static function is_shipping_method_enabled();

	abstract public function do_order_status_changed_actions( $order_id, $status_from, $status_to, $order );

	abstract protected function generate_woocommerce_email( $emails );

	public static function register_hooks() {
		$page = new static();

		if ( ! $page::is_shipping_method_enabled() )
			return;

		add_action( 'admin_menu', [ $page, 'add_wms_menu' ], 99 );
		add_filter( 'set-screen-option', [ $page, 'wms_set_option' ], 10, 3 );

		add_action( 'woocommerce_order_status_changed', [ $page, 'do_order_status_changed_actions' ], 10, 4 );

		if ( ! OrderUtil::custom_orders_table_usage_is_enabled() ) {
			add_action( 'save_post', [ $page, 'save_admin_shipping_method_selection' ], 100, 3 );
		} else {
			add_action( 'woocommerce_process_shop_order_meta', [ $page, 'save_admin_shipping_method_selection' ], 100, 3 );
		}

		add_action( 'wp_ajax_wms_generate_label', [ $page, 'ajax_request_generate_label' ], 10, 3 );
		add_action( 'wp_ajax_nopriv_wms_generate_label', [ $page, 'ajax_request_generate_label' ], 10, 3 );

		add_action( 'before_delete_post', [ $page, 'clean_labels' ] );
		add_action( 'woocommerce_delete_order', [ $page, 'clean_labels' ] );

		add_action( 'add_meta_boxes', [ $page, 'add_order_meta_box' ], 10, 2 );

		add_action( 'admin_post_wms_download_label', [ $page, 'generate_label_PDF' ] );

		add_action( 'admin_post_wms_print_label', [ $page, 'print_label_PDF' ] );
		add_action( 'admin_post_wms_delete_label', [ $page, 'delete_label_PDF' ] );
		add_action( 'admin_post_wms_download_labels_zip', [ $page, 'download_labels_zip' ] );

		add_action( 'init', [ $page, 'register_wms_post_statuses' ] );
		add_filter( 'wc_order_statuses', [ $page, 'register_wms_order_statuses' ] );

		add_filter( 'woocommerce_shipping_methods', [ 
			$page,
			'add_wms_shipping_methods',
		] );

		add_action( 'woocommerce_email_classes', [ $page, 'generate_woocommerce_email' ] );

		add_action( 'woocommerce_after_order_itemmeta', [ $page, 'add_admin_shipping_method_selection' ], 10, 2 );

		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $page, 'add_admin_order_page_assets' ], 20, 2 );
	}

	public function add_wms_menu() {

		add_menu_page(
			__( 'WcMultiShipping', 'wc-multishipping' ),
			__( 'WcMultiShipping', 'wc-multishipping' ),
			'manage_woocommerce',
			'wc-multishipping',
			[ $this, 'display_config_view' ],
			'dashicons-location',
			58
		);
		$hook = add_submenu_page( 'woocommerce', static::SHIPPING_PROVIDER_DISPLAYED_NAME, static::SHIPPING_PROVIDER_DISPLAYED_NAME, 'read', 'wc_wms_view_' . static::SHIPPING_PROVIDER_ID, [ 
			$this,
			'display_order_tables_' . static::SHIPPING_PROVIDER_ID,
		] );
		add_action( "load-$hook", [ 
			$this,
			'add_screen_option',
		] );
	}

	public function wms_set_option( $status, $option, $value ) {
		if ( 'orders_per_page' == $option ) {
			return $value;
		}

		return $status;
	}

	public function add_screen_option() {
		$args = [ 
			'label' => __( 'Orders per page', 'wc-multishipping' ),
			'default' => 25,
			'option' => 'orders_per_page',
		];

		add_screen_option( 'per_page', $args );
	}

	public function add_order_meta_box( $post_type, $post ) {
		if ( $post_type !== "woocommerce_page_wc-orders" && 'shop_order' != get_post_type( get_the_ID() ) )
			return;

		$screen = get_current_screen();
		if ( 'add' == $screen->action )
			return;

		$order = method_exists( $post, 'get_id' ) ? wc_get_order( $post->get_id() ) : wc_get_order( $post->ID );
		$order_class = static::get_order_class();

		$shipping_method_name = $order_class::get_shipping_method_name( $order );
		if ( empty( $shipping_method_name ) )
			return;

		if ( empty( array_key_exists( $shipping_method_name, $order_class::AVAILABLE_SHIPPING_METHODS ) ) )
			return;

		add_meta_box(
			'wms_meta_box',
			static::SHIPPING_PROVIDER_DISPLAYED_NAME,
			[ 
				static::get_meta_box_class(),
				'wms_order_meta_box_display',
			],
			wc_get_container()->get( CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order',
			'side'
		);
	}


	public function save_admin_shipping_method_selection( $post_id ) {
		if ( 'shop_order' != get_post_type( $post_id ) && 'shop_order_placehold' != get_post_type( $post_id ) )
			return;

		$order = wc_get_order( $post_id );
		$order_class = static::get_order_class();

		$new_method = wms_get_var( 'string', 'wms_shipping_method_to_select', '' );
		$wms_pickup_info = wms_get_var( 'string', 'wms_pickup_info', '' );
		$wms_pickup_point = wms_get_var( 'cmd', 'wms_pickup_point', '' );
		$wms_order_item_id = wms_get_var( 'int', 'wms_order_item_id', '' );

		if ( ! array_key_exists( $new_method, $order_class::AVAILABLE_SHIPPING_METHODS ) )
			return;

		if ( ! is_admin() || empty( $new_method ) ) {
			return;
		}

		if ( in_array( $new_method, $order_class::ID_SHIPPING_METHODS_RELAY ) ) {
			$relay_info = json_decode( stripslashes( $wms_pickup_info ) );
			if ( empty( $relay_info ) || ! is_object( $relay_info ) )
				return;

			$relay_info->pickup_id = $wms_pickup_point;

			$order->update_meta_data(
				'_wms_' . static::SHIPPING_PROVIDER_ID . '_pickup_info',
				array_map( 'sanitize_text_field', (array) $relay_info )
			);

			$order->set_shipping_address_1( $relay_info->pickup_address );
			$order->set_shipping_postcode( $relay_info->pickup_zipcode );
			$order->set_shipping_city( $relay_info->pickup_city );
			$order->set_shipping_country( $relay_info->pickup_country );
			$order->set_shipping_company( $relay_info->pickup_name );

			$order->save();
		}

		$shipping_item = $order->get_item( $wms_order_item_id );

		if ( ! $shipping_item || ! is_a( $shipping_item, 'WC_Order_Item_Shipping' ) ) {
			error_log( 'Erreur : Élément de livraison introuvable ou invalide.' );
			return;
		}

		$shipping_item->set_props( [ 
			'method_id' => $new_method,
			'method_title' => $order_class::AVAILABLE_SHIPPING_METHODS[ $new_method ],
		] );

		$shipping_item->save();
		$order->save();

	}

	public function clean_labels( $id ) {
		$order = wc_get_order( $id );
		if ( ! $order )
			return;

		$parcel_class = static::get_parcel_class();
		$tracking_number = $parcel_class::get_tracking_numbers_from_order_ids( $id );
		if ( empty( $tracking_number ) )
			return;

		$label_class = static::get_label_class();
		$label_class::delete( $tracking_number );
	}

	public function ajax_request_generate_label() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_generate_label_nonce' ) ) {
			wp_die( 'You can not do that' );
		}

		$post_id = wms_get_var( 'int', 'wms_order_id', '' );
		if ( empty( $post_id ) )
			return;

		$order = wc_get_order( $post_id );
		$order_class = static::get_order_class();

		$shipping_method_name = $order_class::get_shipping_method_name( $order );
		if ( empty( $shipping_method_name ) )
			return;

		if ( empty( array_key_exists( $shipping_method_name, $order_class::AVAILABLE_SHIPPING_METHODS ) ) )
			return;

		$meta_box_class = static::get_meta_box_class();
		$meta_box_class::save_meta_box_values( $post_id );

		$wms_action = wms_get_var( 'cmd', 'wms_action', '' );
		if ( empty( $wms_action ) || ! wms_table_exists() )
			exit;


		if ( 'wms_generate_outward_labels' == $wms_action ) {
			$this->generate_labels( $is_return_order = false );
		} elseif ( 'wms_generate_inward_labels' == $wms_action ) {
			$this->generate_labels( $is_return_order = true );
		}

	}

	public function generate_labels( $is_return_label = false ) {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_generate_label_nonce' ) )
			wp_die( 'You can not do that' );

		$order_id = wms_get_var( 'int', 'wms_order_id', '' );
		if ( empty( $order_id ) ) {
			wms_enqueue_message( __( 'You\'ve been redirected to the listing as there has been an issue while trying to generate the label' ), 'error' );
			return;
		}


		$order = wc_get_order( $order_id );

		$order_class = static::get_order_class();

		if ( empty( array_key_exists( $order_class::get_shipping_method_name( $order ), $order_class::AVAILABLE_SHIPPING_METHODS ) ) ) {
			return;
		}

		$order_class::register_parcels_labels( $order, $is_return_label );
	}


	public function generate_label_PDF() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_download_label' ) )
			wp_die( 'You can not do that' );
		if ( ! current_user_can( 'edit_posts' ) )
			header( 'HTTP/1.0 401 Unauthorized' );

		$shipping_provider = wms_get_var( 'string', 'wms_shipping_provider', '' );
		if ( static::SHIPPING_PROVIDER_ID != $shipping_provider )
			return false;


		$tracking_numbers = wms_get_var( 'string', 'wms_tracking_numbers', '' );
		if ( empty( $tracking_numbers ) )
			wp_die( __( 'Generate label error: A parameter is missing', 'wc-multishipping' ) );

		$label_class = static::get_label_class();
		$label_class::download_label_PDF( $tracking_numbers );
	}

	public function print_label_PDF() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_print_labels' ) )
			wp_die( 'You can not do that' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			header( 'HTTP/1.0 401 Unauthorized' );
		}

		$shipping_provider = wms_get_var( 'string', 'wms_shipping_provider', '' );
		if ( static::SHIPPING_PROVIDER_ID != $shipping_provider )
			return false;

		$tracking_numbers = wms_get_var( 'string', 'wms_tracking_numbers', '' );
		if ( empty( $tracking_numbers ) )
			wp_die( __( 'Print label error: A parameter is missing', 'wc-multishipping' ) );


		$label_class = static::get_label_class();
		$label_class::print_label_PDF( $tracking_numbers );
	}

	public function delete_label_PDF() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_delete_labels' ) )
			wp_die( 'You can not do that' );
		if ( ! current_user_can( 'edit_posts' ) )
			header( 'HTTP/1.0 401 Unauthorized' );

		$shipping_provider = wms_get_var( 'string', 'wms_shipping_provider', '' );
		if ( static::SHIPPING_PROVIDER_ID != $shipping_provider )
			return false;

		$tracking_numbers_param = wms_get_var( 'string', 'wms_tracking_numbers', '' );
		if ( empty( $tracking_numbers_param ) ) {
			wms_enqueue_message( __( 'Unable to delete label => Nothing to delete', 'wc-multishipping' ), 'error' );

			wp_redirect( wp_get_referer() );
		}
		$tracking_numbers = explode( ',', $tracking_numbers_param );
		if ( empty( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to delete label => Nothing to delete', 'wc-multishipping' ), 'error' );

			wp_redirect( wp_get_referer() );
		}

		$order_class = static::get_order_class();
		if ( ! $order_class::delete_label_meta_from_tracking_numbers( $tracking_numbers ) ) {
			wp_redirect( wp_get_referer() );
		}

		foreach ( $tracking_numbers as $one_tracking_number ) {
			$label_class = static::get_label_class();
			$label_class::delete_label_PDF_from_tracking_number( $one_tracking_number );
		}
		wp_redirect( wp_get_referer() );
	}


	public function download_labels_zip() {
		if ( 1 !== wp_verify_nonce( wms_get_var( 'cmd', 'wms_nonce', '' ), 'wms_download_labels_zip' ) )
			wp_die( 'You can not do that' );
		if ( ! current_user_can( 'edit_posts' ) )
			header( 'HTTP/1.0 401 Unauthorized' );

		$shipping_provider = wms_get_var( 'string', 'wms_shipping_provider', '' );
		if ( static::SHIPPING_PROVIDER_ID != $shipping_provider )
			return false;

		$tracking_numbers_param = wms_get_var( 'string', 'wms_tracking_numbers', '' );
		if ( empty( $tracking_numbers_param ) ) {
			wms_enqueue_message( __( 'Unable to download label => No tracking number found', 'wc-multishipping' ), 'error' );

			wp_redirect( admin_url( 'admin.php?page=wc_wms_view' ) );
		}


		$tracking_numbers = explode( ',', $tracking_numbers_param );
		if ( empty( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to download label => Invalid tracking number params', 'wc-multishipping' ), 'error' );

			wp_redirect( admin_url( 'admin.php?page=wc_wms_view' ) );
		}

		$label_class = static::get_label_class();
		$label_class::download_labels_zip( $tracking_numbers );
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


	public function add_wms_shipping_methods( $shipping_methods ) {
		$shipping_methods_class = static::get_shipping_methods_class();

		$provider_shipping_methods = $shipping_methods_class::load_shipping_methods();

		foreach ( $provider_shipping_methods as $one_shipping_method ) {
			$shipping_methods[ $one_shipping_method::ID ] = get_class( $one_shipping_method );
		}

		return $shipping_methods;
	}


	public function add_admin_shipping_method_selection( $itemId, $item ) {

		if ( empty( $item ) || $item->get_type() !== 'shipping' ) {
			return;
		}

		$order_class = static::get_order_class();

		$order = $item->get_order();
		$shipping_method_name = $order_class::get_shipping_method_name( $order );
		if ( ! empty( $shipping_method_name ) ) {
			if ( array_key_exists( $shipping_method_name, $order_class::AVAILABLE_SHIPPING_METHODS ) )
				return;
		}

		global $woocommerce;
		$countries_obj = new \WC_Countries();
		$countries = $countries_obj->__get( 'countries' );

		$map_to_use = get_option( 'wms_' . static::SHIPPING_PROVIDER_ID . '_section_pickup_points_map_type', 'openstreetmap' );
		if ( 'mondial_relay_map' == $map_to_use && static::SHIPPING_PROVIDER_ID == 'mondial_relay' ) {

			$modal_id = 'wms_pickup_open_modal_mondial_relay';
			ob_start();
			include WMS_PARTIALS . 'pickups' . DS . 'mondial_relay' . DS . 'widget.php';
			$select_pickup_button = ob_get_clean();
		} else if ( 'openstreetmap' == $map_to_use ) {

			$modal_id = 'wms_pickup_open_modal_openstreetmap';
			ob_start();
			include WMS_PARTIALS . 'pickups' . DS . 'openstreetmap' . DS . 'widget.php';
			$select_pickup_button = ob_get_clean();
		} else {

			$google_maps_api_key = get_option( 'wms_' . static::SHIPPING_PROVIDER_ID . '_section_pickup_points_google_maps_api_key' );
			if ( ! empty( $google_maps_api_key ) ) {
				$modal_id = 'wms_pickup_open_modal_google_maps';

				if ( ! wp_style_is( 'wms_pickup_CSS', 'enqueued' ) )
					include WMS_PARTIALS . 'pickups' . DS . 'google_maps' . DS . 'modal.php';

				ob_start();
				include WMS_PARTIALS . 'pickups' . DS . 'google_maps' . DS . 'button.php';
				$select_pickup_button = ob_get_clean();
			} else {
				$select_pickup_button = '<div id="wms_google_maps_issue">' . sprintf( __( 'Can\'t display the pick up selection button. No Google Maps Api Key set in your %s plugin configuration.', 'wc-multishipping' ), static::SHIPPING_PROVIDER_DISPLAYED_NAME ) . '</div>';
			}
		}

		include WMS_PARTIALS . 'pickups' . DS . 'admin_pickup_selection.php';

		wp_enqueue_style( 'wms_pickup_CSS', WMS_ADMIN_CSS_URL . 'pickups/wooshippping_pickup_widget.min.css?time=' . time() );
	}

	function add_admin_order_page_assets( $order ) {
	}

	public function display_config_view() {
		config_class::display_config_view();
	}
}
