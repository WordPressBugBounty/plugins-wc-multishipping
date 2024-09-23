<?php


namespace WCMultiShipping\inc\shipping_methods\ups;

require_once __DIR__.DS.'ups_abstract_shipping.php';

class ups_access_point_economy extends ups_abstract_shipping
{

    const ID = 'ups_access_point_economy';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('UPS - Access Point Economy', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '70';

        parent::__construct($instance_id);
    }
}
