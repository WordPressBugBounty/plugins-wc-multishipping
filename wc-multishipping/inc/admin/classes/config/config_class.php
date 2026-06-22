<?php


namespace WCMultiShipping\inc\admin\classes\config;

use WCMultiShipping\inc\admin\classes\customer\wms_customer_registration;
use WCMultiShipping\inc\admin\classes\telemetry\wms_telemetry;

class config_class
{

    public static function display_config_view()
    {
        $view_data = wms_onboarding::get_view_data();

        require_once WMS_ADMIN.'/partials/config/config.php';
    }

    public static function enqueue_config_assets($hook)
    {
        if ($hook !== 'toplevel_page_wc-multishipping') {
            return;
        }

        $style_path = WMS_ADMIN . 'assets' . DS . 'css' . DS . 'config-page.min.css';
        $style_version = is_file($style_path) ? (string) filemtime($style_path) : WMS_VERSION;

        wp_enqueue_style(
            'wms-config-page',
            WMS_ADMIN_CSS_URL . 'config-page.min.css',
            [],
            $style_version
        );

        $script_path = WMS_ADMIN . 'assets' . DS . 'js' . DS . 'config-page.js';
        $script_version = is_file($script_path) ? (string) filemtime($script_path) : WMS_VERSION;
        $connection = wms_onboarding::get_connection_view_data();

        wp_enqueue_script(
            'wms-config-page',
            WMS_PLUGIN_URL . 'inc/admin/assets/js/config-page.js',
            [ 'wp-i18n' ],
            $script_version,
            true
        );

        wp_localize_script(
            'wms-config-page',
            'wmsConfigPage',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'pro_nonce' => wp_create_nonce('wms_chronopost_pro_nonce'),
                'pro_portal_uri' => $connection['chronopost']['portal_uri'],
                'pro_portal_payload' => $connection['chronopost']['portal_payload'],
                'i18n_connecting' => __('Opening Chronopost Pro…', 'wc-multishipping'),
                'i18n_revoking' => __('Disconnecting…', 'wc-multishipping'),
                'i18n_popup_blocked' => __('Unable to open the Chronopost Pro window. Please allow pop-ups for this site and try again.', 'wc-multishipping'),
                'i18n_connect_error' => __('Error connecting to Chronopost Pro:', 'wc-multishipping'),
                'i18n_connect_failed' => __('Unable to prepare the Chronopost Pro connection on this site.', 'wc-multishipping'),
                'i18n_missing_credentials' => __('Please complete the required credentials before testing this connection.', 'wc-multishipping'),
                'i18n_disconnect_confirm' => __('Disconnect Chronopost PRO? This will delete the stored keys and switch back to SOAP mode.', 'wc-multishipping'),
                'i18n_test_failed' => __('The test request failed. Please try again.', 'wc-multishipping'),
            ]
        );
    }

    public static function register_hooks()
    {
        $page = new static();

        if ( method_exists( $page, 'check_wms_api_key' ) ) {
            add_action('admin_post_wms_save_config', array($page, 'check_wms_api_key'), 10);
        }
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_config_assets'));
        wms_onboarding::register_admin_hooks();
    }


}
