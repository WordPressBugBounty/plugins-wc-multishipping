<?php


namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

require_once __DIR__.DS.'mondial_relay_abstract_shipping.php';

class lockers extends mondial_relay_abstract_shipping
{

    const ID = 'mondial_relay_lockers';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('Mondial Relay - Livraison Lockers', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '24R';

        parent::__construct($instance_id);
    }
}
