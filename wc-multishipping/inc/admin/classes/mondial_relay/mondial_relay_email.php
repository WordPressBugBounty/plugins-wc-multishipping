<?php

namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_email;

class mondial_relay_email extends abstract_email
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'Mondial Relay';
    const SHIPPING_PROVIDER_ID = 'mondial_relay';

    public static function get_order_class()
    {
        return new mondial_relay_order();
    }

    public static function get_parcel_class()
    {
        return new mondial_relay_parcel();
    }
}
