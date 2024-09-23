<?php


namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

require_once __DIR__.DS.'mondial_relay_abstract_shipping.php';

class domicile_1_livreur extends mondial_relay_abstract_shipping
{

    const ID = 'mondial_relay_domicile_1_livreur';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('Mondial Relay - Domicile à 1 livreur', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = 'LD1';

        parent::__construct($instance_id);
    }
}
