<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_label;

class ups_label extends abstract_label
{
    const DOWNLOAD_NAME = 'UPS';

    const SHIPPING_PROVIDER_ID = 'ups';

    var $xml;

    static function get_api_helper()
    {
        return new ups_api_helper();
    }

    public function build_outcome_payload($order)
    {
        if (empty($this->with_access_request())) return false;
        if (empty($this->with_shipment_confirm_request())) return false;


        if (empty($this->with_shipment($order))) return false;
        if (empty($this->with_ship_to($order))) return false;
        if (empty($this->with_ship_from($order))) return false;
        if (empty($this->with_shipper_information())) return false;
        if (empty($this->with_shipping_method($order))) return false;
        if (empty($this->with_billing_information($order))) return false;
        if (empty($this->with_package($order))) return false;
        if (empty($this->with_additional_params($order))) return false;


        return true;
    }

    public function with_access_request()
    {

        $api_key = get_option('wms_ups_api_access_key', '');
        $username = get_option('wms_ups_account_username', '');
        $password = get_option('wms_ups_password', '');

        if (empty($api_key) || empty($username) || empty($password)) {
            wms_enqueue_message(__('Your UPS account credentials are empty. Please set them in the UPS configuration', 'wc-multishipping'), 'error');

            return false;
        }

        $access_request_xml = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");
        $access_request_xml->addChild("AccessLicenseNumber", $api_key);
        $access_request_xml->addChild("UserId", $username);
        $access_request_xml->addChild("Password", $password);

        $this->payload = $access_request_xml->asXML();

        return $this;
    }

    public function with_shipment_confirm_request()
    {
        $this->shipmentConfirmRequestXML = new \SimpleXMLElement ("<ShipmentConfirmRequest ></ShipmentConfirmRequest>");

        $request = $this->shipmentConfirmRequestXML->addChild('Request');
        $request->addChild("RequestAction", "ShipConfirm");
        $request->addChild("RequestOption", "nonvalidate");

        return $this;
    }

    public function with_shipment($order)
    {
        $shipment = $this->shipmentConfirmRequestXML->addChild('Shipment');
        $shipment->addChild("Description", 'WMS order: '.$order->get_id());
        $rateInformation = $shipment->addChild('RateInformation');
        $rateInformation->addChild("NegotiatedRatesIndicator", "");

        return $this;
    }

    public function with_shipper_information()
    {
        $missing_fields = $this->check_required_fields('account');
        if (!empty($missing_fields)) {
            $this->errors[] = __('Some fields are missing in "Account Information" section, please check your UPS configuration.', 'wc-multishipping');

            return false;
        }

        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $shipper = $shipment->addChild('Shipper');

        $shipper->addChild("Name", substr(strtoupper(remove_accents(get_option('wms_ups_shipper_name', ''))), 0, 100).' '.substr(strtoupper(remove_accents(get_option('wms_ups_shipper_name_2', ''))), 0, 100));
        $shipper->addChild("AttentionName", substr(strtoupper(remove_accents(get_option('wms_ups_shipper_name', ''))), 0, 100).' '.substr(strtoupper(remove_accents(get_option('wms_ups_shipper_name_2', ''))), 0, 100));

        $shipper->addChild("CompanyDisplayableName", get_option('wms_ups_shipper_company_name', ''));
        $shipper->addChild("TaxIdentificationNumber", get_option('wms_ups_shipper_vat_number', ''));

        $shipper->addChild("PhoneNumber", get_option('wms_ups_shipper_phone', ''));
        $shipper->addChild("TaxIdentificationNumber", get_option('wms_ups_shipper_vat_number', ''));

        $shipper->addChild("ShipperNumber", get_option('wms_ups_account_number', ''));
        $shipper->addChild("EMailAddress", get_option('wms_ups_shipper_email', ''));

        $shipperAddress = $shipper->addChild('Address');
        $shipperAddress->addChild("AddressLine1", get_option('wms_ups_shipper_address_1', '').' '.get_option('wms_ups_shipper_address_2', ''));
        $shipperAddress->addChild("City", get_option('wms_ups_shipper_city', ''));
        $shipperAddress->addChild("PostalCode", get_option('wms_ups_shipper_zip_code', ''));

        $shipper_country = get_option('wms_ups_shipper_country', '');
        if (!strpos($shipper_country, ':')) $shipperAddress->addChild("CountryCode", get_option('wms_ups_shipper_country', ''));
        else {
            $shipperAddress->addChild("StateProvinceCode", substr($shipper_country, strpos($shipper_country, ':')));
            $shipperAddress->addChild("CountryCode", substr($shipper_country, 0, strpos($shipper_country, ':')));
        }

        return $this;
    }


    public function with_ship_to($order)
    {
        $customer_obj = new \WC_Customer($order->get_customer_id());

        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $shipTo = $shipment->addChild('ShipTo');

        $shipTo->addChild("Name", remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()));
        $shipTo->addChild("AttentionName", remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()));

        if (!empty($order->get_shipping_company())) $shipTo->addChild("CompanyName", remove_accents($order->get_shipping_company()));
        else $shipTo->addChild("CompanyName", remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()));

        $shipTo->addChild("PhoneNumber", $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone());

        $shipTo->addChild("EMailAddress", $order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email());

        $shipToAddress = $shipTo->addChild('Address');
        $shipToAddress->addChild("AddressLine1", remove_accents($order->get_shipping_address_1()));
        $shipToAddress->addChild("City", remove_accents($order->get_shipping_city()));
        if (!empty($order->get_billing_state())) $shipToAddress->addChild("StateProvinceCode", $order->get_billing_state());
        $shipToAddress->addChild("PostalCode", $order->get_shipping_postcode());
        $shipToAddress->addChild("CountryCode", $order->get_shipping_country());

        return $this;
    }

    public function with_ship_from()
    {
        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $shipFrom = $shipment->addChild('ShipFrom');

        $shipFrom->addChild("Name", remove_accents(get_option('wms_ups_shipper_civility ', '')).' '.remove_accents(get_option('wms_ups_shipper_name', '')).' '.remove_accents(get_option('wms_ups_shipper_name_2', '')));
        $shipFrom->addChild("AttentionName", remove_accents(get_option('wms_ups_shipper_civility ', '')).' '.remove_accents(get_option('wms_ups_shipper_name', '')).' '.remove_accents(get_option('wms_ups_shipper_name_2', '')));
        $shipFrom->addChild("CompanyName", remove_accents(get_option('wms_ups_shipper_company_name ', '')));

        $shipFrom->addChild("TaxIdentificationNumber", get_option('wms_ups_shipper_vat_number', ''));

        $shipFrom->addChild("PhoneNumber", get_option('wms_ups_shipper_phone', ''));
        $shipFrom->addChild("EMailAddress", get_option('wms_ups_shipper_email', ''));

        $shipFromAddress = $shipFrom->addChild('Address');
        $shipFromAddress->addChild("AddressLine1", remove_accents(get_option('wms_ups_shipper_address_1', '')).' '.remove_accents(get_option('wms_ups_shipper_address_2', '')));
        $shipFromAddress->addChild("City", remove_accents(get_option('wms_ups_shipper_city', '')));
        $shipFromAddress->addChild("PostalCode", get_option('wms_ups_shipper_zip_code', ''));

        $shipper_country = get_option('wms_ups_shipper_country', '');
        if (!strpos($shipper_country, ':')) {
            $shipFromAddress->addChild("CountryCode", get_option('wms_ups_shipper_country', ''));
        } else {
            $shipFromAddress->addChild("StateProvinceCode", substr($shipper_country, strpos($shipper_country, ':')));
            $shipFromAddress->addChild("CountryCode", substr($shipper_country, 0, strpos($shipper_country, ':')));
        }

        return $this;
    }

    public function with_shipping_method($order)
    {
        $shipping_method_id = ups_order::get_shipping_method_name($order);
        if (empty($shipping_method_id)) return false;

        $all_shipping_methods_class = WC()->shipping()->load_shipping_methods();
        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $service = $shipment->addChild('Service');
        $service->addChild("Code", $all_shipping_methods_class[$shipping_method_id]->get_product_code());
        $service->addChild("Description", "");


        $shipping_method_id = ups_order::get_shipping_method_name($order);
        if ($shipping_method_id == 'ups_access_point_economy') {
            $ShipmentIndicationType = $shipment->addChild('ShipmentIndicationType');
            $ShipmentIndicationType->addChild("Code", "01");
            $AlternateDeliveryAddress = $shipment->addChild('AlternateDeliveryAddress');
            if (!empty($order->get_shipping_company())) $AlternateDeliveryAddress->addChild('Name', remove_accents($order->get_shipping_company()));
            else $AlternateDeliveryAddress->addChild('Name', remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()));
            if (!empty($order->get_shipping_company())) $AlternateDeliveryAddress->addChild('AttentionName', remove_accents($order->get_shipping_company()));
            else $AlternateDeliveryAddress->addChild('AttentionName', remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()));
            $Address = $AlternateDeliveryAddress->addChild('Address');
            $Address->addChild('AddressLine1', remove_accents($order->get_shipping_address_1()));
            $Address->addChild('City', remove_accents($order->get_shipping_city()));
            $Address->addChild('PostalCode', $order->get_shipping_postcode());
            $Address->addChild('CountryCode', $order->get_shipping_country());
        }

        return $this;
    }

    public function with_billing_information()
    {
        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $PaymentInformation = $shipment->addChild('PaymentInformation');

        $prepaid = $PaymentInformation->addChild('Prepaid');
        $billShipper = $prepaid->addChild('BillShipper');
        $billShipper->addChild("AccountNumber", get_option('wms_ups_account_number', ''));

        return $this;
    }


    public function with_package($order)
    {

        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $parcels_dimensions = ups_parcel::get_parcels_dimensions($order);
        if (empty($parcels_dimensions)) return false;
        $woocommerce_weight_unit = get_option('woocommerce_weight_unit');


        foreach ($parcels_dimensions as $one_dimension) {

            $weight = $one_dimension['weight'];

            if ('g' == $woocommerce_weight_unit) {
                $woocommerce_weight_unit = 'kg';
                $weight = $weight / 1000;
            }
            if ('kg' == $woocommerce_weight_unit) $woocommerce_weight_unit = 'kgs';

            $package = $shipment->addChild('Package');
            $package->addChild("Description", 'Customer supplied');

            $packagingType = $package->addChild('PackagingType');
            $packagingType->addChild("Code", "02");
            $packagingType->addChild("Description", "");

            $packageWeight = $package->addChild('PackageWeight');
            $packageWeight->addChild("Weight", $one_dimension['weight']);

            $uom = $packageWeight->addChild('UnitOfMeasurement');
            $uom->addChild("Code", strtoupper($woocommerce_weight_unit));
            $uom->addChild("Description", strtoupper($woocommerce_weight_unit));

            $dimensions = $packageWeight->addChild('Dimensions');
            $dimensions->addChild("Length", $one_dimension['length']);
            $dimensions->addChild("Width", $one_dimension['width']);
            $dimensions->addChild("Height", $one_dimension['height']);

            $packageDimensions = $package->addChild('Dimensions');
            $uomDimensions = $packageDimensions->addChild('UnitOfMeasurement');
            $uomDimensions->addChild("Code", 'CM');
            $uomDimensions->addChild("Description", 'CM');
            $packageDimensions->addChild("Length", $one_dimension['length']);
            $packageDimensions->addChild("Width", $one_dimension['width']);
            $packageDimensions->addChild("Height", $one_dimension['height']);


            $package_weight = $packageWeight->addChild('Dimensions');
            $package_weight->addChild("Code", strtoupper($woocommerce_weight_unit));
            $package_weight->addChild("Description", $woocommerce_weight_unit);
            $package_weight->addChild("Weight", $weight);
        }

        return $this;
    }


    private function with_additional_params($order)
    {
        $shipment = $this->shipmentConfirmRequestXML->Shipment;

        $labelSpecification = $shipment->addChild('LabelSpecification');
        $labelSpecification->addChild("HTTPUserAgent", "");
        $labelPrintMethod = $labelSpecification->addChild('LabelPrintMethod');
        $labelPrintMethod->addChild("Code", "GIF");
        $labelPrintMethod->addChild("Description", "");
        $labelImageFormat = $labelSpecification->addChild('LabelImageFormat');
        $labelImageFormat->addChild("Code", "GIF");
        $labelImageFormat->addChild("Description", "");

        $customer_obj = new \WC_Customer($order->get_customer_id());
        $emailAddress = $order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email();

        $ShipmentServiceOptions = $shipment->addChild('ShipmentServiceOptions');


        $Notification = $ShipmentServiceOptions->addChild('Notification');
        $Notification->addChild('NotificationCode', "6");
        $NotificationEMail = $Notification->addChild('EMail');
        $NotificationEMail->addChild('EMailAddress', $emailAddress);

        $Notification = $ShipmentServiceOptions->addChild('Notification');
        $Notification->addChild('NotificationCode', "7");
        $NotificationEMail = $Notification->addChild('EMail');
        $NotificationEMail->addChild('EMailAddress', $emailAddress);


        $Notification = $ShipmentServiceOptions->addChild('Notification');
        $Notification->addChild('NotificationCode', "8");
        $NotificationEMail = $Notification->addChild('EMail');
        $NotificationEMail->addChild('EMailAddress', $emailAddress);

        $Notification = $ShipmentServiceOptions->addChild('Notification');
        $Notification->addChild('NotificationCode', "012");
        $NotificationEMail = $Notification->addChild('EMail');
        $NotificationEMail->addChild('EMailAddress', $emailAddress);

        $Notification = $ShipmentServiceOptions->addChild('Notification');
        $Notification->addChild('NotificationCode', "013");
        $NotificationEMail = $Notification->addChild('EMail');
        $NotificationEMail->addChild('EMailAddress', $emailAddress);

        $this->payload .= $this->shipmentConfirmRequestXML->asXML();

        return $this;
    }

    private function check_required_fields($field_type)
    {
        if (empty($field_type)) return false;

        $required_fields = [
            'shipper' => [
                'wms_ups_shipper_civility',
                'wms_ups_shipper_name',
                'wms_ups_shipper_name_2',
                'wms_ups_shipper_company_name',
                'wms_ups_shipper_vat_number',
                'wms_ups_shipper_address_1',
                'wms_ups_shipper_zip_code',
                'wms_ups_shipper_city',
                'wms_ups_shipper_country',
            ],
            'account' => [
                'wms_ups_account_number',
                'wms_ups_password',
                'wms_ups_api_access_key',
            ],
        ];


        $missing_fields = [];
        foreach ($required_fields[$field_type] as $one_required_field) {
            if (empty(get_option($one_required_field, ''))) {
                $missing_fields[] = $one_required_field;
            }
        }

        return $missing_fields;
    }
}