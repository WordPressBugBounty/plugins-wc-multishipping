<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;


abstract class abstract_parcel
{
    const INWARD_PARCEL_NUMBER_META_KEY = '_wms_inward_parcel_number';
    const OUTWARD_PARCEL_NUMBER_META_KEY = '_wms_outward_parcel_number';

    const IS_DELIVERED_META_KEY = '_wms_is_delivered';

    const IS_DELIVERED_CODE = ['DI1', 'DI2'];
    const IS_DELIVERED_META_VALUE_TRUE = '1';
    const IS_DELIVERED_META_VALUE_FALSE = '0';

    const UPDATE_STATUS_PERIOD = '';

    const LAST_EVENT_CODE_META_KEY = '';
    const LAST_EVENT_DATE_META_KEY = '';
    const LAST_EVENT_LABEL_META_KEY = '_wms_last_event_label';


    const TRACKING_URL = '';

    abstract static public function get_integration_helper();

    abstract static public function register_parcels_from_order($order, $is_return_order = false);

    abstract static public function get_tracking_numbers_from_order_ids($order_ids);

    abstract public static function get_number_of_parcels($order);

    abstract static public function get_total_weight($order_items, $round = false);

    abstract static public function update_all_status();

    abstract public static function check_parcel_dimensions($order);

    abstract public static function store_parcels_information($order, $api_result, $return = false);

    abstract public static function store_parcel_labels_from_order($order, $is_return_order = false);

    abstract public static function get_formated_tracking_number_from_orders($order_IDs = []);

}
