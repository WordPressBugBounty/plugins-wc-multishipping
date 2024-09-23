<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_email;

class chronopost_email extends abstract_email
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'Chronopost';
    const SHIPPING_PROVIDER_ID = 'chronopost';

    public static function get_order_class()
    {
        return new chronopost_order();
    }

    public static function get_parcel_class()
    {
        return new chronopost_parcel();
    }
}
