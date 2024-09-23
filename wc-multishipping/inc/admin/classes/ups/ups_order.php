<?php


namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_order;

class ups_order extends abstract_order
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'UPS';
    const SHIPPING_PROVIDER_ID = 'ups';

    const PICKUP_INFO_META_KEY = '_wms_ups_pickup_info';

    const WC_WMS_TRANSIT = 'wc-wms_ups_transit';
    const WC_WMS_DELIVERED = 'wc-wms_ups_delivered';
    const WC_WMS_ANOMALY = 'wc-wms_ups_anomaly';
    const WC_WMS_READY_TO_SHIP = 'wc-wms_ups_ready';

    const WC_WMS_TRANSIT_LABEL = 'En transit';
    const WC_WMS_DELIVERED_LABEL = 'Livré';
    const WC_WMS_ANOMALY_LABEL = 'Anomalie';
    const WC_WMS_READY_TO_SHIP_LABEL = 'Prêt pour la livraison';

    const AVAILABLE_SHIPPING_METHODS = [
        'ups_access_point_economy' => 'UPS - Access Point Economy',
        'ups_express' => 'UPS - Express',
        'ups_express_critical' => 'UPS - Express Critical',
        'ups_express_saver' => 'UPS - Express Saver',
        'ups_standard' => 'UPS - Standard',
        'ups_worldwide_expedited' => 'UPS - Worldwide Expedited',
        'ups_worldwide_express_freight' => 'UPS - Worldwide Express Freight',
        'ups_worldwide_express_plus' => 'UPS - Worldwide Express Plus',

    ];

    const ID_SHIPPING_METHODS_RELAY = [
        'ups_access_point_economy',
        'ups_worldwide_saver',
    ];

    public static function get_integration_helper()
    {
        return new ups_helper();
    }

    public static function delete_label_meta_from_tracking_numbers($tracking_numbers)
    {

        if (empty($tracking_numbers)) {
            wms_enqueue_message(__('Unable to delete => Nothing to delete', 'wc-multishipping'));

            return false;
        }

        $integration_helper = static::get_integration_helper();
        $label_class = $integration_helper->get_label_class();

        $label_entries = $label_class::get_info_from_tracking_numbers($tracking_numbers);
        if (empty($label_entries) || !is_array($label_entries)) {
            return false;
        }

        $label_type = ['_wms_outward_parcels', '_wms_inward_parcels'];
        foreach ($label_entries as $one_label_entry) {

            if (empty($one_label_entry->order_id)) continue;
            $order_id = $one_label_entry->order_id;

            $order = wc_get_order($order_id);
            $shipment_data = $order->get_meta('_wms_ups_shipment_data', true);
            if (empty($shipment_data)) continue;

            foreach ($label_type as $one_label_type) {
                if (empty($shipment_data[$one_label_type]['_wms_parcels'])) continue;

                foreach ($shipment_data[$one_label_type]['_wms_parcels'] as $one_key => $one_parcel) {

                    if (in_array($one_parcel['_wms_parcel_skybill_number'], $tracking_numbers)) {

                        unset($shipment_data[$one_label_type]['_wms_parcels'][$one_key]);

                        if ('_wms_outward_parcels' == $one_label_type) unset($shipment_data['_wms_inward_parcels']['_wms_parcels'][$one_key]);
                    }
                    $order->update_meta_data('_wms_ups_shipment_data', $shipment_data);
                }
            }
        }
        $order->save();

        return true;
    }

    public static function get_label_class()
    {
        return new ups_label();
    }
}

