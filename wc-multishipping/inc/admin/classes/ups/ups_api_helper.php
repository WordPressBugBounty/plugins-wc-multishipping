<?php


namespace WCMultiShipping\inc\admin\classes\ups;


use SoapClient;

class ups_api_helper
{
    const API_URL = 'https://onlinetools.ups.com/ups.app/xml';

    public function check_credentials($params)
    {
        if (empty($params)) return false;

        $access_request_XML = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");

        $access_request_XML->addChild("AccessLicenseNumber", $params['api_access_key']);
        $access_request_XML->addChild("UserId", $params['account_number']);
        $access_request_XML->addChild("Password", $params['password']);

        $rating_request_XML = new \SimpleXMLElement ("<RatingServiceSelectionRequest></RatingServiceSelectionRequest>");
        $rating_request_XML->addChild('Request', '');

        $xml_request = $access_request_XML->asXML().$rating_request_XML->asXML();

        try {
            $xml = wp_remote_post(
                self::API_URL.'/Rate',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $xml_request,
                ]
            );

            return json_decode(json_encode(simplexml_load_string($xml['body'])));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get_available_shipping_methods($params)
    {
        if (empty($params)) return;

        $pickup_results = $this->get_pickups($params);
        if (empty($pickup_results->Response->ResponseStatusCode) || empty($pickup_results->SearchResults->DropLocation['0'])) return false;

        $access_request_XML = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");
        $rate_request_XML = new \SimpleXMLElement ("<RatingServiceSelectionRequest></RatingServiceSelectionRequest>");

        $access_request_XML->addChild("AccessLicenseNumber", $params['api_access_key']);
        $access_request_XML->addChild("UserId", $params['account_number']);
        $access_request_XML->addChild("Password", $params['password']);

        $request = $rate_request_XML->addChild('Request');
        $request->addChild("RequestAction", "Rate");
        $request->addChild("RequestOption", "Shop");

        $shipment = $rate_request_XML->addChild('Shipment');
        $shipper = $shipment->addChild('Shipper');
        $shipper->addChild("Name", "Name");
        $shipper->addChild("ShipperNumber", "");
        $shipperddress = $shipper->addChild('Address');
        $shipperddress->addChild("AddressLine1", remove_accents(get_option('wms_ups_shipper_address_1', '').' '.get_option('wms_ups_shipper_address_2', '')));
        $shipperddress->addChild("City", remove_accents(get_option('wms_ups_shipper_city', '')));
        $shipperddress->addChild("PostalCode", get_option('wms_ups_shipper_zip_code', ''));
        $shipperddress->addChild("CountryCode", substr(get_option('wms_ups_shipper_country', ''), 0, 2));

        $shipTo = $shipment->addChild('ShipTo');
        $shipTo->addChild("CompanyName", get_option('wms_ups_shipper_company_name', ''));
        $shipToAddress = $shipTo->addChild('Address');
        $shipToAddress->addChild("AddressLine1", remove_accents($params['address']));
        $shipToAddress->addChild("City", remove_accents($params['city']));
        if (!empty($params['state'])) $shipToAddress->addChild("StateProvinceCode", remove_accents($params['state']));
        $shipToAddress->addChild("PostalCode", remove_accents($params['postcode']));
        $shipToAddress->addChild("CountryCode", $params['country']);

        $shipFrom = $shipment->addChild('ShipFrom');
        $shipFrom->addChild("CompanyName", get_option('wms_ups_shipper_company_name', ''));
        $shipFromAddress = $shipFrom->addChild('Address');
        $shipFromAddress->addChild("AddressLine1", remove_accents(get_option('wms_ups_shipper_address_1', '').' '.get_option('wms_ups_shipper_address_2', '')));
        $shipFromAddress->addChild("City", remove_accents(get_option('wms_ups_shipper_city', '')));
        $shipFromAddress->addChild("PostalCode", get_option('wms_ups_shipper_zip_code', ''));
        $shipFromAddress->addChild("CountryCode", substr(get_option('wms_ups_shipper_country', ''), 0, 2));

        $AlternateDeliveryAddress = $shipment->addChild('AlternateDeliveryAddress');

        $shipToAddress = $AlternateDeliveryAddress->addChild('Address');
        $shipToAddress->addChild("AddressLine1", $pickup_results->SearchResults->DropLocation['0']->AddressKeyFormat->AddressLine);
        $shipToAddress->addChild("City", $pickup_results->SearchResults->DropLocation['0']->AddressKeyFormat->PoliticalDivision2);
        $shipToAddress->addChild("PostalCode", $pickup_results->SearchResults->DropLocation['0']->AddressKeyFormat->PostcodePrimaryLow);
        $shipToAddress->addChild("CountryCode", $pickup_results->SearchResults->DropLocation['0']->AddressKeyFormat->CountryCode);


        $package = $shipment->addChild('Package');
        $packageType = $package->addChild('PackagingType');
        $packageType->addChild("Code", "02");
        $packageType->addChild("Description", "UPS Package");

        $packageWeight = $package->addChild('PackageWeight');
        $unitOfMeasurement = $packageWeight->addChild('UnitOfMeasurement');

        $woocommerce_weight_unit = get_option('woocommerce_weight_unit');
        if ('kg' == $woocommerce_weight_unit) $woocommerce_weight_unit = 'KGS';

        $weight = $params['total_weight'];
        if ('g' == $woocommerce_weight_unit) {
            $woocommerce_weight_unit = 'kgs';
            $weight = $weight / 1000;
        }

        $unitOfMeasurement->addChild("Code", strtoupper($woocommerce_weight_unit));
        $packageWeight->addChild("Weight", $weight);

        $xml_request = $access_request_XML->asXML().$rate_request_XML->asXML();

        try {
            $xml = wp_remote_post(
                self::API_URL.'/Rate',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $xml_request,
                ]
            );

            if (is_array($xml) && !empty($xml['body'])) return json_decode(json_encode(simplexml_load_string($xml['body'])));
            else if (!is_array($xml) && !empty($xml->errors)) return $xml->errors["http_request_failed"][0];
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }


    public function get_pickups($params)
    {
        if (empty($params)) return;

        $access_request_XML = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");
        $locator_request_XML = new \SimpleXMLElement ("<LocatorRequest ></LocatorRequest >");

        $access_request_XML->addChild("AccessLicenseNumber", $params['api_access_key']);
        $access_request_XML->addChild("UserId", $params['account_number']);
        $access_request_XML->addChild("Password", $params['password']);

        $request = $locator_request_XML->addChild('Request');
        $request->addChild("RequestAction", "Locator");
        $request->addChild("RequestOption", "64");

        $originAddress = $locator_request_XML->addChild('OriginAddress');

        $addressKeyFormat = $originAddress->addChild('AddressKeyFormat');

        if (!empty($params['city'])) $addressKeyFormat->addChild("PoliticalDivision2", $params['city']);
        if (!empty($params['postcode'])) $addressKeyFormat->addChild("PostcodePrimaryLow", $params['postcode']);
        $addressKeyFormat->addChild("CountryCode", $params['country']);

        $translate = $locator_request_XML->addChild('Translate');
        $translate->addChild("LanguageCode", "ENG");

        $unitOfMeasurement = $locator_request_XML->addChild('UnitOfMeasurement');
        $unitOfMeasurement->addChild("Code", "KM");


        $xml_request = $access_request_XML->asXML().$locator_request_XML->asXML();

        try {
            $xml = wp_remote_post(
                self::API_URL.'/Locator',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $xml_request,
                ]
            );

            if (is_array($xml) && !empty($xml['body'])) return json_decode(json_encode(simplexml_load_string($xml['body'])));
            else if (!is_array($xml) && !empty($xml->errors)) return $xml->errors["http_request_failed"][0];
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function register_parcels($xml_request)
    {
        if (empty($xml_request)) return;

        try {
            $xml = wp_remote_post(
                self::API_URL.'/ShipConfirm',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $xml_request,
                ]
            );

            if (is_array($xml) && !empty($xml['body'])) return json_decode(json_encode(simplexml_load_string($xml['body'])));
            else if (!is_array($xml) && !empty($xml->errors)) return $xml->errors["http_request_failed"][0];
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }


    public function get_labels_from_api($shipment_digest)
    {
        $accessRequestXML = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");
        $accessRequestXML->addChild("AccessLicenseNumber", get_option('wms_ups_api_access_key', ''));
        $accessRequestXML->addChild("UserId", get_option('wms_ups_account_username', ''));
        $accessRequestXML->addChild("Password", get_option('wms_ups_password', ''));

        $shipmentAcceptRequestXML = new \SimpleXMLElement ("<ShipmentAcceptRequest ></ShipmentAcceptRequest >");
        $request = $shipmentAcceptRequestXML->addChild('Request');
        $request->addChild("RequestAction", "01");

        $shipmentAcceptRequestXML->addChild("ShipmentDigest", $shipment_digest);

        $xml_request = $accessRequestXML->asXML().$shipmentAcceptRequestXML->asXML();

        try {
            $xml = wp_remote_post(
                self::API_URL.'/ShipAccept',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $xml_request,
                ]
            );

            if (is_array($xml) && !empty($xml['body'])) return json_decode(json_encode(simplexml_load_string($xml['body'])));
            else if (!is_array($xml) && !empty($xml->errors)) return $xml->errors["http_request_failed"][0];
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get_label_content_from_api($tracking_number)
    {
        $accessRequestXML = new \SimpleXMLElement ("<AccessRequest></AccessRequest>");
        $accessRequestXML->addChild("AccessLicenseNumber", get_option('wms_ups_api_access_key', ''));
        $accessRequestXML->addChild("UserId", get_option('wms_ups_account_username', ''));
        $accessRequestXML->addChild("Password", get_option('wms_ups_password', ''));

        $labelRecoveryRequestXML = new \SimpleXMLElement ("<LabelRecoveryRequest ></LabelRecoveryRequest >");
        $request = $labelRecoveryRequestXML->addChild('Request');
        $request->addChild("RequestAction", "LabelRecovery");

        $labelSpecification = $labelRecoveryRequestXML->addChild('LabelSpecification');
        $labelSpecification->addChild("HTTPUserAgent");
        $labelImageFormat = $labelSpecification->addChild('LabelImageFormat');
        $labelImageFormat->addChild("Code", "GIF");

        $labelDelivery = $labelRecoveryRequestXML->addChild('LabelDelivery');
        $labelDelivery->addChild("LabelLinkIndicator");
        $labelDelivery->addChild("ResendEMailIndicator");

        $labelRecoveryRequestXML->addChild("TrackingNumber", $tracking_number);

        $requestXML = $accessRequestXML->asXML().$labelRecoveryRequestXML->asXML();

        try {
            $xml = wp_remote_post(
                self::API_URL.'/LabelRecovery',
                [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body'    => $requestXML,
                ]
            );

            return json_decode(json_encode(simplexml_load_string($xml['body'])));
        } catch (\Exception $e) {
            return false;
        }
    }
}
