<?php


namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_order;

class mondial_relay_order extends abstract_order
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = 'Mondial Relay';
    const SHIPPING_PROVIDER_ID = 'mondial_relay';

    const PICKUP_INFO_META_KEY = '_wms_mondial_relay_pickup_info';

    const WC_WMS_TRANSIT = 'wc-wms_mr_transit';
    const WC_WMS_DELIVERED = 'wc-wms_mr_delivered';
    const WC_WMS_ANOMALY = 'wc-wms_mr_anomaly';
    const WC_WMS_READY_TO_SHIP = 'wc-wms_mr_ready';

    const WC_WMS_TRANSIT_LABEL = 'En transit';
    const WC_WMS_DELIVERED_LABEL = 'Livré';
    const WC_WMS_ANOMALY_LABEL = 'Anomalie';
    const WC_WMS_READY_TO_SHIP_LABEL = 'Prêt pour la livraison';

    const AVAILABLE_SHIPPING_METHODS = [
        'mondial_relay_colis_drive' => 'Livraison en Colis Drive',
        'mondial_relay_domicile_1_livreur' => 'Livraison Domicile 1 Livreur',
        'mondial_relay_domicile_2_livreurs' => 'Livraison Domicile 2 Livreurs',
        'mondial_relay_domicile_inf_30' => 'Livraison Domicile <30kg',
        'mondial_relay_point_relais' => 'Livraison Point Relais',
    ];

    const ID_SHIPPING_METHODS_RELAY = [
        'mondial_relay_point_relais',
    ];

    public static function get_integration_helper()
    {
        return new mondial_relay_helper();
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
            $shipment_data = $order->get_meta('_wms_mondial_relay_shipment_data', true);
            if (empty($shipment_data)) continue;

            foreach ($label_type as $one_label_type) {
                if (empty($shipment_data[$one_label_type]['_wms_parcels'])) continue;

                foreach ($shipment_data[$one_label_type]['_wms_parcels'] as $one_key => $one_parcel) {

                    if (in_array($one_parcel['_wms_parcel_skybill_number'], $tracking_numbers)) {

                        unset($shipment_data[$one_label_type]['_wms_parcels'][$one_key]);

                        if ('_wms_outward_parcels' == $one_label_type) unset($shipment_data['_wms_inward_parcels']['_wms_parcels'][$one_key]);
                    }
                    $order->update_meta_data('_wms_mondial_relay_shipment_data', $shipment_data);
                }
            }
        }
        $order->save();

        return true;
    }

    public static function get_label_class()
    {
        return new mondial_relay_label();
    }
}

