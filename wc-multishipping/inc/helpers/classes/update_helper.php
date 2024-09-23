<?php

namespace WCMultiShipping\inc\helpers\classes;


use WCMultiShipping\inc\admin\classes\update_class;

class update_helper
{
    public function __construct()
    {
        add_filter('site_transient_update_plugins', [$this, 'check_updates'], 10, 1);
    }

    public function check_updates($transient)
    {

        return $transient;
    }
}

new update_helper();
