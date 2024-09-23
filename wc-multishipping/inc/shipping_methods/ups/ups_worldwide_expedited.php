<?php


namespace WCMultiShipping\inc\shipping_methods\ups;

require_once __DIR__.DS.'ups_abstract_shipping.php';

class ups_worldwide_expedited extends ups_abstract_shipping
{

    const ID = 'ups_worldwide_expedited';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('UPS - Worldwide Expedited', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '08';

        parent::__construct($instance_id);
    }
}
