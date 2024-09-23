<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_helper;
use WCMultiShipping\inc\admin\partials\orders\ups\ups_orders_list_table;
use WCMultiShipping\inc\admin\partials\orders\wms_orders_list_table;

class ups_helper extends abstract_helper
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'UPS';
    const SHIPPING_PROVIDER_ID = 'ups';

    public function __construct()
    {
        new ups_settings();
    }

    public static function is_shipping_method_enabled()
    {
        return 'yes' === get_option('wms_ups_enable', 'yes');
    }

    public function display_order_tables_ups()
    {
        $orders_table = new ups_orders_list_table();
        $orders_table->helper_class = $this;

        $params = [];
        $orders_table->prepare_items($params);

        return $orders_table->display_table();
    }

    public function do_order_status_changed_actions($order_id, $status_from, $status_to, $order)
    {
        if (empty(array_key_exists(ups_order::get_shipping_method_name($order), ups_order::AVAILABLE_SHIPPING_METHODS))) return;

        $wms_nonce = wms_get_var('cmd', 'wms_nonce', '');
        if (!empty($wms_nonce)) ups_meta_box::save_meta_box_values($order_id);

        $order_statuses = get_option('wms_ups_section_label_generation_status', '');

        if (empty($order_statuses) || $status_from === $status_to) return;

        if (in_array($status_to, $order_statuses) || in_array('wc-'.$status_to, $order_statuses)) {
            ups_order::register_parcels_labels($order, $return = false);
        }
    }

    function update_wms_statuses()
    {
        $order_class = new ups_order();
        $order_class->add_orders_to_update();
        ups_parcel::update_all_status();
    }

    public function generate_woocommerce_email($emails)
    {
        $emails['wms_'.static::SHIPPING_PROVIDER_ID.'_tracking'] = new ups_email();

        return $emails;
    }

    public static function get_order_class()
    {
        return new ups_order();
    }

    public static function get_meta_box_class()
    {
        return new ups_meta_box();
    }

    public static function get_label_class()
    {
        return new ups_label();
    }

    public static function get_parcel_class()
    {
        return new ups_parcel();
    }

    public static function get_api_helper_class()
    {
        return new ups_api_helper();
    }

    public static function get_shipping_methods_class()
    {
        return new ups_shipping_methods();
    }

}
