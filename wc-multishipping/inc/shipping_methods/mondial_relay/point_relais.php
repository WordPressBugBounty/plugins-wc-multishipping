<?php


namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

require_once __DIR__.DS.'mondial_relay_abstract_shipping.php';

class point_relais extends mondial_relay_abstract_shipping
{

    const ID = 'mondial_relay_point_relais';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->method_title = __('Mondial Relay - Livraison Point Relais', 'wc-multishipping');
        $this->method_description = '';

        $this->product_code = '24R';

        parent::__construct($instance_id);
    }
}
