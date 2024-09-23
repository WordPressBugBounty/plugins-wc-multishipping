<?php


namespace WCMultiShipping\inc\shipping_methods\ups;

require_once __DIR__.DS.'ups_abstract_shipping.php';

class ups_worldwide_express extends ups_abstract_shipping
{

    const ID = 'ups_worldwide_express';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('UPS - Worldwide Express', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '07';

        parent::__construct($instance_id);
    }
}
