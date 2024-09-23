<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__.DS.'chronopost_abstract_shipping.php';

class chronopost_ambient_13 extends chronopost_abstract_shipping
{

    const ID = 'chronopost_ambient_13';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;

        $this->method_title = __('Chronopost Ambient 13', 'wc-multishipping');

        $this->method_description = '';

        $this->product_code = '5M';

        $this->return_product_code = '4T';

        parent::__construct($instance_id);
    }
}
