<?php


namespace WCMultiShipping\inc\shipping_methods\ups;

require_once __DIR__.DS.'ups_abstract_shipping.php';

class ups_worldwide_express_plus extends ups_abstract_shipping
{

    const ID = 'ups_worldwide_express_plus';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('UPS - Worldwide Express Plus', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '54';

        parent::__construct($instance_id);
    }
}
