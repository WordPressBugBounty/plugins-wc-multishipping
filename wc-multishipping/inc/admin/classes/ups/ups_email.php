<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_email;

class ups_email extends abstract_email
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'UPS';
    const SHIPPING_PROVIDER_ID = 'ups';

    public static function get_order_class()
    {
        return new ups_order();
    }

    public static function get_parcel_class()
    {
        return new ups_parcel();
    }
}
