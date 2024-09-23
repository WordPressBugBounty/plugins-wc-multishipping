<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_parcel;

class ups_parcel extends abstract_parcel
{
    const INWARD_PARCEL_NUMBER_META_KEY = '_wms_inward_parcel_number';
    const OUTWARD_PARCEL_NUMBER_META_KEY = '_wms_outward_parcel_number';

    const IS_DELIVERED_META_VALUE_TRUE = '1';
    const IS_DELIVERED_META_VALUE_FALSE = '0';

    const LAST_EVENT_CODE_META_KEY = '_wms_last_event_code';
    const LAST_EVENT_DATE_META_KEY = '_wms_last_event_date';
    const LAST_EVENT_LABEL_META_KEY = '_wms_last_event_label';

    const IS_DELIVERED_META_KEY = '_wms_is_delivered';

    const TRACKING_URL = 'https://www.ups.com/WebTracking/processInputRequest?tracknum={tracking_number}&loc=en_US&requester=ST/trackdetails';

    public static function get_integration_helper()
    {
        return new ups_helper();
    }


    public static function update_all_status()
    {
        return;
    }

    public static function register_parcels_from_order($order, $is_return_order = false)
    {
        if (empty($order)) {
            wms_enqueue_message(__('Error while registering parcels for order => Order is empty.'), 'error');

            return false;
        }

        if (!self::check_parcel_dimensions($order)) return false;


        $ups_label_class = new ups_label();
        $ups_api_helper = new ups_api_helper();

        $result = !$is_return_order ? $ups_label_class->build_outcome_payload($order) : $ups_label_class->build_income_payload($order);

        if (empty($result)) {
            if (!empty($ups_label_class->errors)) wms_enqueue_message($ups_label_class->errors, 'error');

            return false;
        }

        $api_result = $ups_api_helper->register_parcels($ups_label_class->payload);
        if (empty($api_result->ShipmentDigest)) {
            if (!empty($api_result->Response->Error->ErrorDescription)) wms_enqueue_message(sprintf(__('Error while registering parcels for order => %s', 'wc-multishipping'), $api_result->Response->Error->ErrorDescription), 'error');

            return false;
        }

        if (!self::store_parcels_information($order, $api_result, $is_return_order)) return false;


        return true;
    }

    public static function get_number_of_parcels($order)
    {
        return $order->get_meta('_wms_ups_parcels_number', true) ? : 1;
    }

    public static function get_total_weight($order_items, $round = false)
    {
        if (empty($order_items)) {
            wms_enqueue_message(__('Error while getting dimensions => No order items.'), 'error');

            return false;
        }

        $weight = 0;

        foreach ($order_items as $item_id => $values) {

            if (!$values->get_product_id() && !$values->product_id) continue;
            $variation_id = $values->get_variation_id();
            $product_id = !empty($variation_id) ? $values->get_variation_id() : (isset($values->product_id) ? $values->product_id : $values->get_product_id());
            $product = wc_get_product($product_id);

            $items_weight = wc_get_weight($product->get_weight(), get_option('woocommerce_weight_unit'));

            if ($round) {
                $items_weight = round($items_weight);
            }
            $weight += $values->get_quantity() * $items_weight;
        }

        return $weight;
    }

    public static function check_parcel_dimensions($order)
    {
        return true;
    }

    public static function store_parcels_information($order, $api_result, $return = false)
    {
        if (empty($order) || empty($api_result) || empty($api_result->ShipmentDigest) || empty($api_result->ShipmentIdentificationNumber)) {
            wms_enqueue_message(__('Error while storing information => Missing parameter.'), 'error');

            return false;
        }
        $labels_type = (!$return ? '_wms_outward_parcels' : '_wms_inward_parcels');

        $shipping_method_id = ups_order::get_shipping_method_name($order);
        if (empty($shipping_method_id)) return false;

        if (!self::check_parcel_dimensions($order)) return false;
        $parcels_dimensions = self::get_parcels_dimensions($order);

        $label_class = new ups_label();
        $package_information = $label_class->generate_labels_from_api($api_result->ShipmentDigest);

        if (empty($package_information->Response->ResponseStatusCode) || empty($package_information->ShipmentResults->ShipmentIdentificationNumber)) {
            wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => %s', 'wc-multishipping'), $order->get_id(), $package_information->Response->Error->ErrorDescription), 'error');

            return false;
        }

        if (!is_array($package_information->ShipmentResults->PackageResults)) $package_information->ShipmentResults->PackageResults = [$package_information->ShipmentResults->PackageResults];

        $new_shipment_data = [
            '_wms_shipping_provider' => 'ups',
            $labels_type => [
                '_wms_shipping_provider_method_id' => $shipping_method_id,
                '_wms_reservation_number' => $api_result->ShipmentIdentificationNumber,
                '_wms_shipment_digest' => $api_result->ShipmentDigest,
            ],
        ];

        foreach ($package_information->ShipmentResults->PackageResults as $parcel_number => $one_parcel) {
            if (empty($one_parcel->TrackingNumber)) {
                wms_logger('Error in function: store_outward_parcels_information ');
                wms_logger(sprintf('---- Details : Skybill Number is empty => %d'), wms_display_value($one_parcel->skybillNumber));

                return false;
            }

            $new_parcel = [
                '_wms_parcel_skybill_number' => $one_parcel->TrackingNumber,
                '_wms_parcel' => (array)$one_parcel,
                '_wms_parcel_dimensions' => $parcels_dimensions[0],
            ];
            $new_shipment_data[$labels_type]['_wms_parcels'][] = $new_parcel;
        }

        $old_shipment_data = $order->get_meta('_wms_ups_shipment_data', true);
        $shipment_data_to_save = (is_array($old_shipment_data) ? array_merge($old_shipment_data, $new_shipment_data) : $new_shipment_data);

        $order->update_meta_data('_wms_ups_shipment_data', $shipment_data_to_save);
        $order->save();

        return true;
    }

    public static function store_parcel_labels_from_order($order, $is_return_order = false)
    {
        $shipment_data = $order->get_meta('_wms_ups_shipment_data', true);
        $label_type = (!$is_return_order ? '_wms_outward_parcels' : '_wms_inward_parcels');
        if (empty($shipment_data) || empty($shipment_data[$label_type]['_wms_parcels'])) {
            wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => Shipment information are missing.', 'wc-multishipping'), $order->get_id()), 'error');

            return false;
        }


        $reservation_number = $shipment_data[$label_type]['_wms_reservation_number'];

        $ups_api_helper = new ups_api_helper();

        $labels_content_from_api = $ups_api_helper->get_label_content_from_api($reservation_number);


        if (empty($labels_content_from_api->Response->ResponseStatusCode)) {
            wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => %s', 'wc-multishipping'), $order->get_id(), $labels_content_from_api->Response->Error->ErrorDescription), 'error');

            return false;
        }

        if (!is_array($labels_content_from_api->LabelResults)) $labels_content_from_api->LabelResults = [$labels_content_from_api->LabelResults];


        if (!class_exists('PDF_lib')) include(WMS_LIB.'PDF_lib.class.php');

        $tracking_number = '';
        $label_pdf_pages = [];
        foreach ($labels_content_from_api->LabelResults as $one_key => $one_label) {
            ob_start();
            $label_file_name = 'parcel_labels.gif';
            $label_content_file = fopen(sys_get_temp_dir().DS.$label_file_name, 'w');
            fwrite($label_content_file, base64_decode($one_label->LabelImage->GraphicImage));
            fclose($label_content_file);
            $label_pdf_pages[] = @\PDF_lib::gif_to_pdf(sys_get_temp_dir().DS.$label_file_name, \PDF_lib::DESTINATION__STRING);
        }


        if (empty($label_pdf_pages)) {
            wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => Can\'t split pages.', 'wc-multishipping'), $order->get_id()), 'error');

            return false;
        }

        foreach ($shipment_data[$label_type]['_wms_parcels'] as $parcel_number => $one_parcel) {

            if ($is_return_order) {
                if (empty($shipment_data['_wms_outward_parcels']['_wms_parcels'][$parcel_number]['_wms_parcel_skybill_number'])) {
                    wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => Can\'t find outward order to attach inward label to.', 'wc-multishipping'), $order->get_id()), 'error');
                    break;
                }
                $tracking_number = $shipment_data['_wms_outward_parcels']['_wms_parcels'][$parcel_number]['_wms_parcel_skybill_number'];
            }

            if (empty($one_parcel['_wms_parcel_skybill_number'])) {
                wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => Can\'t find inward skybill number.', 'wc-multishipping'), $order->get_id()), 'error');
                continue;
            }

            $ups_label_class = new ups_label();
            $sql_result = $ups_label_class->save_label_in_db(
                $order->get_id(),
                $label_pdf_pages[$parcel_number],
                $ups_label_class::LABEL_FORMAT_PDF,
                $one_parcel['_wms_parcel_skybill_number'],
                $reservation_number,
                $is_return_order,
                $tracking_number
            );
            if (empty($sql_result)) {
                wms_enqueue_message(sprintf(__('Error while storing parcel labels for order %s => SQL issue.', 'wc-multishipping'), $order->get_id()), 'error');

                return false;
            }
        }

        return true;
    }

    public static function get_formated_tracking_number_from_orders($order_IDs = [])
    {
        if (empty($order_IDs)) return false;
        if (!is_array($order_IDs)) $order_IDs = [$order_IDs];

        $label_types = ['_wms_outward_parcels', '_wms_inward_parcels'];

        $all_parcel_labels = [];

        foreach ($order_IDs as $one_order_id) {
            $order = wc_get_order($one_order_id);
            $shipment_data = $order->get_meta('_wms_ups_shipment_data', true);
            if (empty($shipment_data)) continue;


            foreach ($label_types as $one_label_type) {

                if (empty($shipment_data[$one_label_type]) || empty($shipment_data[$one_label_type]['_wms_parcels']) || !is_array($shipment_data[$one_label_type]['_wms_parcels'])) continue;


                foreach ($shipment_data[$one_label_type]['_wms_parcels'] as $one_parcel_number => $one_parcel) {
                    if (empty($one_parcel['_wms_parcel_skybill_number'])) continue;


                    $one_parcel_information = new \stdClass();
                    $one_parcel_information->order_id = $one_order_id;
                    $one_parcel_information->tracking_number = $one_parcel['_wms_parcel_skybill_number'];

                    $all_parcel_labels[$one_order_id][$one_parcel_number][$one_label_type] = $one_parcel_information;
                }
            }
        }

        return $all_parcel_labels;
    }

    public static function get_tracking_numbers_from_order_ids($order_ids)
    {
        if (empty($order_ids)) {
            wms_enqueue_message(__('Error while getting tracking numbers: No order(s) selected'), 'error');

            return false;
        }

        $label_type = ['_wms_outward_parcels', '_wms_inward_parcels'];
        if (!is_array($order_ids)) $order_ids = [$order_ids];

        $tracking_numbers = [];
        foreach ($order_ids as $one_order_id) {
            $order = wc_get_order($one_order_id);
            $shipment_data = $order->get_meta('_wms_ups_shipment_data', true);
            if (empty($shipment_data) || empty($shipment_data['_wms_outward_parcels']) && empty($shipment_data['_wms_inward_parcels'])) continue;

            foreach ($label_type as $one_label_type) {

                if (empty($shipment_data[$one_label_type])) continue;

                foreach ($shipment_data[$one_label_type]['_wms_parcels'] as $one_shipment) {
                    if (empty($one_shipment['_wms_parcel_skybill_number'])) {
                        wms_enqueue_message(sprintf(__('Error while getting tracking numbers from order %s => No skybill number found for this order.', 'wc-multishipping'), $one_order_id), 'error');
                        break;
                    }
                    $tracking_numbers[] = $one_shipment['_wms_parcel_skybill_number'];
                }
            }
        }

        return $tracking_numbers;
    }

    public static function get_parcels_dimensions($order)
    {
        if (empty($order)) {
            wms_enqueue_message(__('Error while getting dimensions => Order is empty.'), 'error');

            return false;
        }

        $parcels_number = $order->get_meta('_wms_ups_parcels_number', true) ? : 0;

        $parcels_dimensions = $order->get_meta('_wms_ups_parcels_dimensions', true);
        if (empty($parcels_dimensions)) $parcels_dimensions = [];

        if (empty($parcels_dimensions) || !is_array($parcels_dimensions)) {
            for ($i = 0; $i <= $parcels_number; $i++) {

                $parcels_dimensions[$i] = [
                    'weight' => self::get_total_weight($order->get_items()),
                    'length' => 1,
                    'height' => 1,
                    'width' => 1,
                ];
            }
        }

        return $parcels_dimensions;
    }

    public static function get_insurance($order)
    {
        $order_insurance_enable = $order->get_meta('_wms_ups_insurance', true);
        if ('' === $order_insurance_enable) $order_insurance_enable = get_option('wms_ups_section_parcel_insurance', '0');

        return $order_insurance_enable;
    }

    public static function get_installation_duration($order)
    {
        $installation_duration = $order->get_meta('_wms_ups_installation_duration', true);

        if ('' === $installation_duration) $installation_duration = get_option('wms_ups_section_parcel_installation_duration', '0');

        return $installation_duration;
    }

    public static function get_shipping_value($order)
    {
        $shipping_value = $order->get_meta('_wms_ups_shipping_value', true);

        if ('' === $shipping_value) $shipping_value = get_option('wms_ups_section_parcel_shipping_value', '0');

        return $shipping_value;
    }

    private static function get_status_from_code($code)
    {
        if (empty($code)) return false;

        $status_from_code = [
            '80' => ups_order::WC_WMS_READY_TO_SHIP,
            '81' => ups_order::WC_WMS_TRANSIT,
            '82' => ups_order::WC_WMS_DELIVERED,
            '83' => ups_order::WC_WMS_ANOMALY,
        ];

        if (empty($status_from_code[$code])) return false;

        return $status_from_code[$code];
    }

    public static function get_tracking_url($tracking_number)
    {
        $customer_code = get_option('wms_ups_customer_code', '');
        $brand_code = get_option('wms_ups_brand_code', '');

        return str_replace('{tracking_number}', $tracking_number, static::TRACKING_URL);
    }
}
