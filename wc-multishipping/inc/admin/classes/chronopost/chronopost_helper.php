<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_helper;
use WCMultiShipping\inc\admin\partials\orders\chronopost\chronopost_orders_list_table;
use WCMultiShipping\inc\admin\partials\orders\wms_orders_list_table;

class chronopost_helper extends abstract_helper {
	const SHIPPING_PROVIDER_DISPLAYED_NAME = 'Chronopost';
	const SHIPPING_PROVIDER_ID = 'chronopost';

	public function __construct() {
		new chronopost_settings();
	}

	public static function is_shipping_method_enabled() {
		return 'yes' === get_option( 'wms_chronopost_enable', 'yes' );
	}

	public function display_order_tables_chronopost() {
		$orders_table = new chronopost_orders_list_table();
		$orders_table->helper_class = $this;

		$params = [];
		$orders_table->prepare_items( $params );

		return $orders_table->display_table();
	}

	public function do_order_status_changed_actions( $order_id, $status_from, $status_to, $order ) {
	}

	public static function update_wms_statuses() {
		return;
	}

	public function generate_woocommerce_email( $emails ) {
		$emails[ 'wms_' . static::SHIPPING_PROVIDER_ID . '_tracking' ] = new chronopost_email();

		return $emails;
	}

	public static function get_order_class() {
		return new chronopost_order();
	}

	public static function get_meta_box_class() {
		return new chronopost_meta_box();
	}

	public static function get_label_class() {
		return new chronopost_label();
	}

	public static function get_parcel_class() {
		return new chronopost_parcel();
	}

	public static function get_api_helper_class() {
		return new chronopost_api_helper();
	}

	public static function get_shipping_methods_class() {
		return new chronopost_shipping_methods();
	}

}
