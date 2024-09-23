<?php


namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

require_once __DIR__.DS.'mondial_relay_abstract_shipping.php';

class domicile_2_livreurs extends mondial_relay_abstract_shipping
{

    const ID = 'mondial_relay_domicile_2_livreurs';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('Mondial Relay - Livraison à domicile à 2 livreurs', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = 'LDS';

        parent::__construct($instance_id);
    }
}
