<?php


namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

require_once __DIR__.DS.'mondial_relay_abstract_shipping.php';

class domicile_inf_30 extends mondial_relay_abstract_shipping
{

    const ID = 'mondial_relay_domicile_inf_30';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('Mondial Relay - Livraison à domicile < 30kg', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = 'HOM';

        parent::__construct($instance_id);
    }
}
