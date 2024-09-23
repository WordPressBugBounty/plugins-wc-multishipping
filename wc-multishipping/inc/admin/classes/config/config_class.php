<?php


namespace WCMultiShipping\inc\admin\classes\config;


class config_class
{

    public static function display_config_view()
    {
        $wms_api_key = get_option('wms_api_key', '');
        $wms_license_expiration_date = get_option('wms_license_expiration_date', '');
        require_once WMS_ADMIN.'/partials/config/config.php';
    }


}