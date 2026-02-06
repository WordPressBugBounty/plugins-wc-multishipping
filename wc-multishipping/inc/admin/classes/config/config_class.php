<?php


namespace WCMultiShipping\inc\admin\classes\config;

use WCMultiShipping\inc\admin\classes\customer\wms_customer_registration;

class config_class
{

    public static function display_config_view()
    {
        $wms_api_key = get_option('wms_api_key', '');
        $wms_license_expiration_date = get_option('wms_license_expiration_date', '');
        
        require_once WMS_ADMIN.'/partials/config/config.php';
    }

    public static function enqueue_config_styles($hook)
    {
        if ($hook !== 'toplevel_page_wc-multishipping') {
            return;
        }

        wp_enqueue_style(
            'wms-config-page',
            WMS_ADMIN_CSS_URL . 'config-page.min.css',
            [],
            '1.0.0'
        );
    }
    public static function register_hooks()
    {
        $page = new static();

        add_action('admin_post_wms_save_config', array($page, 'check_wms_api_key'), 10);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_config_styles'));
    }


}