<?php

namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_helper;
use WCMultiShipping\inc\admin\partials\orders\mondial_relay\mondial_relay_orders_list_table;
use WCMultiShipping\inc\admin\partials\orders\wms_orders_list_table;

class mondial_relay_helper extends abstract_helper {
	const SHIPPING_PROVIDER_DISPLAYED_NAME = 'Mondial Relay';
	const SHIPPING_PROVIDER_ID = 'mondial_relay';

	public function __construct() {
		new mondial_relay_settings();
	}

	public static function is_shipping_method_enabled() {
		return 'yes' === get_option( 'wms_mondial_relay_enable', 'yes' );
	}

	public function display_order_tables_mondial_relay() {
		$orders_table = new mondial_relay_orders_list_table();
		$orders_table->helper_class = $this;

		$params = [];
		$orders_table->prepare_items( $params );

		return $orders_table->display_table();
	}

	public function do_order_status_changed_actions( $order_id, $status_from, $status_to, $order ) {
	}

	public static function update_wms_statuses() {
	}

	public function generate_woocommerce_email( $emails ) {
		$emails[ 'wms_' . static::SHIPPING_PROVIDER_ID . '_tracking' ] = new mondial_relay_email();

		return $emails;
	}

	public static function get_order_class() {
		return new mondial_relay_order();
	}

	public static function get_meta_box_class() {
		return new mondial_relay_meta_box();
	}

	public static function get_label_class() {
		return new mondial_relay_label();
	}

	public static function get_parcel_class() {
		return new mondial_relay_parcel();
	}

	public static function get_api_helper_class() {
		return new mondial_relay_api_helper();
	}

	public static function get_shipping_methods_class() {
		return new mondial_relay_shipping_methods();
	}

}
