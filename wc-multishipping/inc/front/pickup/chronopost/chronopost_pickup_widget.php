<?php


namespace WCMultiShipping\inc\front\pickup\chronopost;


use WCMultiShipping\inc\admin\classes\chronopost\chronopost_api_helper;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_connection_manager;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_order;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_shipping_methods;
use WCMultiShipping\inc\front\pickup\abstract_classes\abstract_pickup_widget;

class chronopost_pickup_widget extends abstract_pickup_widget
{
    const PICKUP_LOCATION_SESSION_VAR_NAME = 'wms_selected_chronopost_pickup_info';

    const SHIPPING_PROVIDER_ID = 'chronopost';

    const SHIPPING_PROVIDER_NAME = 'Chronopost';

    const CHRONOPOST_RELAY_PRODUCT_CODE = 86;

    public static function is_shipping_method_enabled()
    {
        return 'yes' === get_option('wms_chronopost_enable', 'yes');
    }

    public function get_pickup_point()
    {
        if (1 !== wp_verify_nonce(wms_get_var('cmd', 'wms_nonce', ''), 'wms_pickup_selection')) wp_die('Invalid nonce');

        $shipping_provider = wms_get_var('cmd', 'shipping_provider', '');
        if (static::SHIPPING_PROVIDER_ID !== $shipping_provider) return;

        $shipping_method_id = wms_get_var( 'string', 'shipping_method_id', '' );
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
        $connection_manager = chronopost_connection_manager::get_instance();
        $account_number = get_option('wms_chronopost_account_number', '');
        $password = get_option('wms_chronopost_account_password', '');

        if ($connection_manager->is_soap_mode() && (empty($account_number) || empty($password))) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => __('Unable to load pickup points: Your Chronopost account credentials are empty. Please set them in the Chronopost configuration', 'wc-multishipping'),
                ]
            );
        }

        $chronopost_api_helper = new chronopost_api_helper();
        $result = $chronopost_api_helper->search_relay_points(
            $shipping_method_id,
            [
                'postcode' => $zip_code,
                'city' => $city,
                'country' => $country,
            ]
        );
        if (!empty($result->errorCode)) {
            wp_send_json(
                [
                    'error' => true,
                    'error_message' => wms_display_value($result->errorMessage),
                ]
            );
        }


        $pickup_points = [];
        $raw_points = is_array($result->listePointRelais) ? $result->listePointRelais : [$result->listePointRelais];

        foreach ($raw_points as $one_pickup) {
            $additional_pickup = [
                'id' => wms_display_value($one_pickup->identifiant),
                'name' => wms_display_value($one_pickup->nom),
                'address' => wms_display_value($one_pickup->adresse1),
                'city' => wms_display_value($one_pickup->localite),
                'zip_code' => wms_display_value($one_pickup->codePostal),
                'country' => wms_display_value($one_pickup->codePays),
                'latitude' => wms_display_value($one_pickup->coordGeolocalisationLatitude),
                'longitude' => wms_display_value($one_pickup->coordGeolocalisationLongitude),
            ];

            foreach ($one_pickup->listeHoraireOuverture as $one_opening_time) {
                if (is_array($one_opening_time->listeHoraireOuverture)) {
                    $additional_pickup['opening_time'][] = wms_display_value($one_opening_time->listeHoraireOuverture[0]->debut.'-'.$one_opening_time->listeHoraireOuverture[0]->fin.' - '.$one_opening_time->listeHoraireOuverture[1]->debut.'-'.$one_opening_time->listeHoraireOuverture[1]->fin);
                } else {
                    $additional_pickup['opening_time'][] = wms_display_value($one_opening_time->listeHoraireOuverture->debut.' - '.$one_opening_time->listeHoraireOuverture->fin);
                }
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
        return new chronopost_order();
    }

    public static function get_shipping_methods_class()
    {
        return new chronopost_shipping_methods();
    }
}
