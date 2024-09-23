<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_settings;
use WCMultiShipping\inc\admin\partials\settings\wms_partial_settings_button;

class ups_settings extends abstract_settings
{
    const SHIPPING_METHOD_ID = 'ups';
    const SHIPPING_METHOD_DISPLAYED_NAME = 'UPS';

    public function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_'.self::SHIPPING_METHOD_ID, [$this, 'settings_tab']);
        add_action('woocommerce_update_options_'.self::SHIPPING_METHOD_ID, [$this, 'update_settings']);
        new wms_partial_settings_button();

        add_action('wp_ajax_wms_ups_test_credentials', [$this, 'wms_ups_test_credentials_ajax']);
        add_action('wp_ajax_wms_ups_log_export', [$this, 'wms_export_log']);
    }

    public static function settings_tab()
    {
        wp_enqueue_script('wms_ups_settings', WMS_ADMIN_JS_URL.'ups/ups_woocommerce_settings.min.js?t='.time(), ['jquery']);
        wp_enqueue_style('wms_ups_settings', WMS_ADMIN_CSS_URL.'ups/ups_woocommerce_settings.min.css?t='.time());
        woocommerce_admin_fields(self::get_settings());
    }

    public static function get_settings()
    {
        $first_status = ['' => __('Do not change status', 'wc-multishipping')];
        $all_status = array_merge($first_status, wc_get_order_statuses());

        $wc_status = array_filter(
            $all_status,
            function ($one_value) {
                return false === strpos($one_value, 'Colissimo');
            }
        );

        $value = get_option('wms_ups_enable', 'yes');

        $config_fields = [
            [
                "id"    => "wms_ups_section_global_configuration",
                "type"  => "title",
                "title" => __("Global configuration", "wc-multishipping"),
            ],
            [
                "id"    => "wms_ups_enable",
                "type"  => "checkbox",
                "title" => __("Enable this shipping method?", "wc-multishipping"),
                "class" => "",
                "value" => $value,
            ],
            [
                "id"   => "wms_ups_section_global_configuration",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_account_information",
                "type"  => "title",
                "title" => __("UPS Account Information", "wc-multishipping"),
            ],
            [
                "id"      => "wms_ups_account_username",
                "type"    => "text",
                "title"   => __("UPS Account Username", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_password",
                "type"    => "text",
                "title"   => __("UPS Password", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_account_number",
                "type"    => "text",
                "title"   => __("UPS Account Number", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_api_access_key",
                "type"    => "text",
                "title"   => __("API Access Key", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_account_test_credentials",
                "type"    => "button",
                "title"   => __("Access Test", "wc-multishipping"),
                "class"   => "button-secondary",
                "default" => "",
            ],
            [
                "id"   => "wms_ups_section_account_information",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_pickup_points",
                "type"  => "title",
                "title" => __("Pickup Points Map", "wc-multishipping"),
            ],
            [
                "id"                => "wms_ups_section_pickup_points_map_type",
                "type"              => "select",
                "title"             => __("Display pickup points map via  (Pro Version only)", "wc-multishipping"),
                "class"             => "",
                "default"           => "openstreetmap",
                "options"           => [
                    "openstreetmap" => "OpenStreetMap",
                    "google_maps"   => "Google Maps",
                ],
                "custom_attributes" => [
                    "disabled" => "disabled",
                ],
            ],
            [
                "id"      => "wms_ups_section_pickup_points_google_maps_api_key",
                "type"    => "text",
                "title"   => __("Google Maps API Key (No need to fill this one if you use Mondial Relay widget)", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"   => "wms_ups_section_pickup_points",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_label",
                "type"  => "title",
                "title" => __("Label", "wc-multishipping"),
            ],
            [
                "id"                => "wms_ups_section_label_generation_status",
                "type"              => "multiselect",
                "title"             => __("Automatically generate label on these order status (Pro Version only)", "wc-multishipping"),
                "class"             => "",
                "default"           => "",
                "options"           => $wc_status,
                "custom_attributes" => [
                    "disabled" => "disabled",
                ],
            ],
            [
                "id"                => "wms_ups_section_label_status_post_generation",
                "type"              => "select",
                "title"             => __("Status to set after label generation (Pro version only)", "wc-multishipping"),
                "class"             => "",
                "default"           => "",
                "options"           => $wc_status,
                "custom_attributes" => [
                    "disabled" => "disabled",
                ],
            ],
            [
                "id"                => "wms_ups_section_label_send_email",
                "type"              => "checkbox",
                "title"             => __("Send tracking link via email once the label is generated? (Pro version only)", "wc-multishipping"),
                "class"             => "",
                "custom_attributes" => [
                    "disabled" => "disabled",
                ],
            ],
            [
                "id"   => "wms_ups_section_label",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_sender_information",
                "type"  => "title",
                "title" => __("Your Sender Address", "wc-multishipping"),
            ],
            [
                "id"      => "wms_ups_shipper_civility",
                "type"    => "select",
                "title"   => __("Civility", "wc-multishipping"),
                "class"   => "",
                "default" => "MLLE",
                "options" => [
                    "MLLE" => "MLLE",
                    "MR"   => "MR",
                    "MME"  => "MME",
                ],
            ],
            [
                "id"      => "wms_ups_shipper_name",
                "type"    => "text",
                "title"   => __("First Name", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_name_2",
                "type"    => "text",
                "title"   => __("Last Name", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_company_name",
                "type"    => "text",
                "title"   => __("Company Name", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_vat_number",
                "type"    => "text",
                "title"   => __("VAT Number", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_address_1",
                "type"    => "text",
                "title"   => __("Address", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_address_2",
                "type"    => "text",
                "title"   => __("Address 2", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_zip_code",
                "type"    => "text",
                "title"   => __("Zip Code", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_city",
                "type"    => "text",
                "title"   => __("City", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_country",
                "type"    => "single_select_country",
                "title"   => __("Country", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_email",
                "type"    => "text",
                "title"   => __("Email", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_phone",
                "type"    => "text",
                "title"   => __("Phone", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"      => "wms_ups_shipper_mobile_phone",
                "type"    => "text",
                "title"   => __("Mobile Phone", "wc-multishipping"),
                "class"   => "",
                "default" => "",
            ],
            [
                "id"   => "wms_ups_section_sender_information",
                "type" => "sectionend",
            ],
            [
                "id"   => "wms_ups_section_parcel",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_log",
                "type"  => "title",
                "title" => __("Error Logs", "wc-multishipping"),
            ],
            [
                "id"      => "wms_ups_log_export",
                "type"    => "button",
                "title"   => __("Export error logs", "wc-multishipping"),
                "class"   => "button-secondary",
                "default" => "",
            ],
            [
                "id"   => "wms_ups_section_log",
                "type" => "sectionend",
            ],
            [
                "id"    => "wms_ups_section_debug",
                "type"  => "title",
                "title" => __("Debug mode", "wc-multishipping"),
            ],
            [
                "id"      => "wms_ups_debug_mode",
                "title"   => __("Enable debug mode", "wc-multishipping"),
                "default" => "0",
                "type"    => "select",
                "options" => ["No", "Yes"],
            ],
            [
                "id"   => "wms_ups_section_debug",
                "type" => "sectionend",
            ],
        ];

        add_filter('woocommerce_create_account_default_checked', '__return_true');


        return apply_filters('wc_settings_'.static::SHIPPING_METHOD_ID.'_settings', $config_fields);
    }


    public function wms_ups_test_credentials_ajax()
    {
        if (!current_user_can('administrator')) exit;

        $api_key = wms_get_var('cmd', 'api_key', '');
        $account_number = wms_get_var('cmd', 'account_number', '');
        $password = wms_get_var('cmd', 'password', '');

        if (empty($api_key) || empty($account_number) || empty($password)) {
            $response = [
                'message' => __('Credentials not found', 'wc-multishipping'),
                'error'   => true,
            ];

            wp_send_json($response);
        }


        $params = [
            'api_access_key' => $api_key,
            'account_number' => $account_number,
            'password'       => $password,
        ];

        $ups_api_helper = new ups_api_helper();
        $data = $ups_api_helper->check_credentials($params);

        if ($data->Response->Error->ErrorCode !== "250003") {
            $response = [
                'message' => __('These account information are valid', 'wc-multishipping'),
                'error'   => false,
            ];
        } else {
            $response = [
                'message' => sprintf(__('Error with UPS API: %s', 'wc-multishipping'), $data->Response->Error->ErrorDescription),
                'error'   => true,
            ];
        }

        echo wp_send_json($response);
    }
}
