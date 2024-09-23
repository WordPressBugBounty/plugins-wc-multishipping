<?php


namespace WCMultiShipping\inc\front\pickup\ups;


use WCMultiShipping\inc\admin\classes\ups\ups_api_helper;
use WCMultiShipping\inc\admin\classes\ups\ups_order;
use WCMultiShipping\inc\admin\classes\ups\ups_shipping_methods;
use WCMultiShipping\inc\front\pickup\abstract_classes\abstract_pickup_widget;

class ups_pickup_widget extends abstract_pickup_widget
{
    const PICKUP_LOCATION_SESSION_VAR_NAME = 'wms_selected_ups_pickup_info';

    const SHIPPING_PROVIDER_ID = 'ups';

    const SHIPPING_PROVIDER_NAME = 'UPS';


    public static function is_shipping_method_enabled()
    {
        return 'yes' === get_option('wms_ups_enable', 'yes');
    }

    public function get_pickup_point()
    {
        if (1 !== wp_verify_nonce(wms_get_var('cmd', 'wms_nonce', ''), 'wms_pickup_selection')) wp_die('Invalid nonce');

        $shipping_provider = wms_get_var('cmd', 'shipping_provider', '');
        if (static::SHIPPING_PROVIDER_ID !== $shipping_provider) return;

        $city = wms_get_var('string', 'city', '');
        $zip_code = wms_get_var('cmd', 'zipcode', '');
        $country = wms_get_var('cmd', 'country', 'FR');

        if (empty($city) || empty($zip_code)) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => __('Cannot find city or zip code', 'wc-multishipping'),
                ]
            );
        }

        $api_key = get_option('wms_ups_api_access_key', '');
        $account_number = get_option('wms_ups_account_number', '');
        $password = get_option('wms_ups_password', '');

        if (empty($api_key) || empty($account_number) || empty($password)) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => sprintf(__('Unable to load pickup points: Your %s account credentials are empty. Please set them in the %s configuration', 'wc-multishipping'), self::SHIPPING_PROVIDER_NAME, self::SHIPPING_PROVIDER_NAME),
                ]
            );
        }

        $params = [
            'api_access_key' => $api_key,
            'account_number' => $account_number,
            'password' => $password,
            'country' => $country,
            'city' => $city,
            'postcode' => $zip_code,
        ];

        $ups_api_helper = new ups_api_helper();
        $result = $ups_api_helper->get_pickups($params);


        if (empty($result->SearchResults)) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => sprintf(__('Error: %s', 'wc-multishipping'), wms_display_value($result->Response->Error->ErrorDescription)),
                ]
            );
        }

        if (empty($result->SearchResults->DropLocation)) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => __('No pickup points available in the selected area', 'wc-multishipping'),
                ]
            );
        }


        $pickup_points = [];

        foreach ($result->SearchResults->DropLocation as $one_pickup) {
            $additional_pickup = [
                'id' => wms_display_value($one_pickup->LocationID),
                'name' => wms_display_value($one_pickup->AddressKeyFormat->ConsigneeName),
                'address' => wms_display_value($one_pickup->AddressKeyFormat->AddressLine),
                'city' => wms_display_value($one_pickup->AddressKeyFormat->PoliticalDivision2),
                'zip_code' => wms_display_value($one_pickup->AddressKeyFormat->PostcodePrimaryLow),
                'country' => wms_display_value($one_pickup->AddressKeyFormat->CountryCode),
                'latitude' => wms_display_value(str_replace(',', '.', $one_pickup->Geocode->Latitude)),
                'longitude' => wms_display_value(str_replace(',', '.', $one_pickup->Geocode->Longitude)),
            ];

            foreach ($one_pickup->OperatingHours->StandardHours->DayOfWeek as $one_day) {

                if (isset($one_day->ClosedIndicator)) {
                    $additional_pickup['opening_time'][] = __('Closed', 'wc-multishipping');
                } else if (isset($one_day->Open24HoursIndicator)) {
                    $additional_pickup['opening_time'][] = '24/24';
                } else {
                    if (!is_array($one_day->OpenHours)) {
                        $open_hours_length = (3 == strlen($one_day->OpenHours)) ? 1 : 2;
                        $close_hours_length = (3 == strlen($one_day->CloseHours)) ? 1 : 2;
                        $additional_pickup['opening_time'][] = wms_display_value(substr($one_day->OpenHours, 0, strlen($one_day->OpenHours) - 2).':'.substr($one_day->OpenHours, strlen($one_day->OpenHours) - 2).'-'.substr($one_day->CloseHours, 0, strlen($one_day->CloseHours) - 2).':'.substr($one_day->CloseHours, strlen($one_day->CloseHours) - 2));
                    } else {
                        $additional_pickup['opening_time'][] = wms_display_value(
                            substr($one_day->OpenHours[0], 0, strlen($one_day->OpenHours[0]) - 2).':'.substr($one_day->OpenHours[0], strlen($one_day->OpenHours[0]) - 2).'-'.substr($one_day->OpenHours[1], 0, strlen($one_day->OpenHours[1]) - 2).':'.substr($one_day->OpenHours[1], strlen($one_day->OpenHours[1]) - 2).' '.substr($one_day->CloseHours[0], 0, strlen($one_day->CloseHours[0]) - 2).':'.substr($one_day->CloseHours[0], strlen($one_day->CloseHours[0]) - 2).'-'.substr($one_day->CloseHours[1], 0, strlen($one_day->CloseHours[1]) - 2).':'.substr($one_day->CloseHours[1], strlen($one_day->CloseHours[1]) - 2)
                        );
                    }
                }
            }

            if (!empty($additional_pickup['opening_time'])) {
                array_push($additional_pickup['opening_time'], $additional_pickup['opening_time'][0]);

                unset($additional_pickup['opening_time'][0]);

                $additional_pickup['opening_time'] = array_values($additional_pickup['opening_time']);
            }

            $pickup_points[] = $additional_pickup;
        }

        wp_send_json(
            [
                'error' => false,
                'error_message' => '',
                'data' => $pickup_points,
            ]
        );
    }

    public static function get_order_class()
    {
        return new ups_order();
    }

    public static function get_shipping_methods_class()
    {
        return new ups_shipping_methods();
    }
}
