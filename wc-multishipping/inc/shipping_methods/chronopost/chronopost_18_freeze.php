<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__.DS.'chronopost_abstract_shipping.php';

class chronopost_18_freeze extends chronopost_abstract_shipping
{

    const ID = 'chronopost_18_freeze';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;

        $this->method_title = __('Chronopost 18 Freeze', 'wc-multishipping');

        $this->method_description = '';

        $this->product_code = '6A';

        $this->return_product_code = '4T';

        parent::__construct($instance_id);
    }
}