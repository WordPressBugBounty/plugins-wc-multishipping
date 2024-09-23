<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

use WCMultiShipping\inc\admin\partials\settings\wms_partial_settings_button;

abstract class abstract_settings
{
    const CONFIG_FILE = '';

    const SHIPPING_METHOD_ID = '';
    const SHIPPING_METHOD_DISPLAYED_NAME = '';

    abstract public static function get_settings();


    public function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_'.static::SHIPPING_METHOD_ID, [$this, 'settings_tab']);
        add_action('woocommerce_update_options_'.static::SHIPPING_METHOD_ID, [$this, 'update_settings']);
        new wms_partial_settings_button();
    }

    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs[static::SHIPPING_METHOD_ID] = static::SHIPPING_METHOD_DISPLAYED_NAME;

        return $settings_tabs;
    }

    public static function settings_tab()
    {
        woocommerce_admin_fields(static::get_settings());
    }

    public static function update_settings()
    {
        woocommerce_update_options(static::get_settings());
    }

    public function wms_export_log()
    {
        if (!current_user_can('administrator')) exit;
        $file = wms_get_current_log();

        if (!file_exists($file)) {
            wp_send_json(
                [
                    'error'   => true,
                    'message' => __('There is no log', 'wc-multishipping'),
                ]
            );
        };

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');

        header('Content-Disposition: attachment; filename='.$file);
        header('Content-Transfer-Encoding: binary');

        echo file_get_contents($file);
        exit;
    }
}
