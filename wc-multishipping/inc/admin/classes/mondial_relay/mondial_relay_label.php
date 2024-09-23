<?php

namespace WCMultiShipping\inc\admin\classes\mondial_relay;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_label;

class mondial_relay_label extends abstract_label
{
    const DOWNLOAD_NAME = 'Mondial_relay';

    const SHIPPING_PROVIDER_ID = 'mondial_relay';

    protected $fields = array(
        'Enseigne' => array(
            'required' => true,
            'regex' => '#^[0-9A-Z]{2}[0-9A-Z ]{6}$#',
        ),
        'ModeCol' => array(
            'required' => true,
            'regex' => '#^(CCC|CDR|CDS|REL)$#',
        ),
        'ModeLiv' => array(
            'required' => true,
            'regex' => '#^(LCC|LD1|LDS|24R|ESP|DRI|HOM)$#',
        ),
        'NDossier' => array(
            'regex' => '#^(|[0-9A-Z_ -]{0,15})$#',
        ),
        'NClient' => array(
            'regex' => '#^(|[0-9A-Z]{0,9})$#',
        ),
        'Expe_Langage' => array(
            'required' => true,
            'regex' => '#^[A-Z]{2}$#',
        ),
        'Expe_Ad1' => array(
            'required' => true,
            'regex' => '#^[0-9A-Z_\-\'., /]{2,32}$#',
        ),
        'Expe_Ad2' => array(
            'regex' => '#^[0-9A-Z_\-\'., /]{0,32}$#',
        ),
        'Expe_Ad3' => array(
            'required' => true,
            'regex' => '#^[0-9A-Z_\-\'., /]{0,32}$#',
        ),
        'Expe_Ad4' => array(
            'regex' => '#^[0-9A-Z]{2}[0-9A-Z ]{6}$#',
        ),
        'Expe_Ville' => array(
            'required' => true,
            'regex' => '#^[A-Z_\-\' 0-9]{2,26}$#',
        ),
        'Expe_CP' => array(
            'required' => true,
        ),
        'Expe_Pays' => array(
            'required' => true,
            'regex' => '#^[A-Z]{2}$#',
        ),
        'Expe_Tel1' => array(
            'required' => true,
            'regex' => '#^((00|\+)[1-9]{2}|0)[0-9][0-9]{7,9}$#',
        ),
        'Expe_Tel2' => array(
            'regex' => '#^((00|\+)[1-9]{2}|0)[0-9][0-9]{7,9}$#',
        ),
        'Expe_Mail' => array(
            'regex' => '#^[\w\-\.\@_]{0,70}$#',
        ),
        'Dest_Langage' => array(
            'required' => true,
            'regex' => '#^FR|ES|NL$#',
        ),
        'Dest_Ad1' => array(
            'required' => true,
            'regex' => '#^[0-9A-Z_\-\'., /]{2,32}$#',
        ),
        'Dest_Ad2' => array(
            'regex' => '#^[0-9A-Z_\-\'., /]{2,32}$#',
        ),
        'Dest_Ad3' => array(
            'required' => true,
            'regex' => '#^[0-9A-Z_\-\'., /]{2,32}$#',
        ),
        'Dest_Ad4' => array(
            'regex' => '#^[0-9A-Z_\-\'., /]{0,32}$#',
        ),
        'Dest_Ville' => array(
            'required' => true,
            'regex' => '#^[A-Z_\-\' 0-9]{2,26}$#',
        ),
        'Dest_CP' => array(
            'required' => true,
        ),
        'Dest_Pays' => array(
            'required' => true,
            'regex' => '#^[A-Z]{2}$#',
        ),
        'Dest_Tel1' => array(
            'regex' => '#^((00|\+)[1-9]{2}|0)[0-9][0-9]{7,9}$#',
        ),
        'Dest_Tel2' => array(
            'regex' => '#^((00|\+)[1-9]{2}|0)[0-9][0-9]{7,9}$#',
        ),
        'Dest_Mail' => array(
            'regex' => '#^[\w\-\.\@_]{0,70}$#',
        ),
        'Poids' => array(
            'required' => true,
            'regex' => '#^1[5-9]$|^[2-9][0-9]$|^[0-9]{3,7}$#',
        ),
        'Longueur' => array(
            'regex' => '#^[0-9]{0,3}$#',
        ),
        'Taille' => array(
            'regex' => '#^(XS|S|M|L|XL|XXL|3XL)$#',
        ),
        'NbColis' => array(
            'required' => true,
            'regex' => '#^[0-9]{1,2}$#',
        ),
        'CRT_Valeur' => array(
            'required' => true,
            'regex' => '#^[0-9]{1,7}$#',
        ),
        'CRT_Devise' => array(
            'regex' => '#^(|EUR)$#',
        ),
        'Exp_Valeur' => array(
            'regex' => '#^[0-9]{0,7}$#',
        ),
        'Exp_Devise' => array(
            'regex' => '#^(|EUR)$#',
        ),
        'COL_Rel_Pays' => array(
            'regex' => '#^[A-Z]{2}$#',
        ),
        'COL_Rel' => array(
            'regex' => '#^(|[0-9]{6})$#',
        ),
        'LIV_Rel_Pays' => array(
            'regex' => '#^[A-Z]{2}$#',
        ),
        'LIV_Rel' => array(
            'regex' => '#^(|[0-9]{6})$#',
        ),
        'TAvisage' => array(
            'regex' => '#^(|O|N)$#',
        ),
        'TReprise' => array(
            'regex' => '#^(|O|N)$#',
        ),
        'Montage' => array(
            'regex' => '#^(|[0-9]{1,3})$#',
        ),
        'TRDV' => array(
            'regex' => '#^(|O|N)$#',
        ),
        'Assurance' => array(
            'regex' => '#^(|[0-9A-Z]{1})$#',
        ),
        'Instructions' => array(
            'regex' => '#^[0-9A-Z_\-\'., /]{0,31}#',
        ),
        'Security' => array(
            'regex' => '#^[0-9A-Z]{32}$#',
        ),
        'Texte' => array(
            'regex' => '#^([^<>&\']{3,30})(\(cr\)[^<>&\']{0,30}){0,9}$#',
        ),
    );


    static function get_api_helper()
    {
        return new mondial_relay_api_helper();
    }

    public function build_outcome_payload($order)
    {
        if (empty($this->with_account())) return false;
        if (empty($this->with_shipping_method($order))) return false;
        if (empty($this->with_shipper($order, false))) return false;
        if (empty($this->with_recipient($order, false))) return false;
        if (empty($this->with_packages($order))) return false;
        if (empty($this->with_additional_params($order, false))) return false;
        if (empty($this->with_security())) return false;


        return true;
    }

    public function build_tracking_payload($order)
    {
        if (empty($this->with_account())) return false;
        if (empty($this->with_tracking_number($order))) return false;
        if (empty($this->with_language())) return false;

        if (empty($this->with_security())) return false;


        return true;
    }

    public function with_account()
    {
        $missing_fields = $this->check_required_fields('account');
        if (!empty($missing_fields)) {
            $this->errors[] = sprintf(
                __('Some fields are missing in "Account Information" section, please check your %s', 'wc-multishipping'),
                '<a href="'.get_site_url().'/wp-admin/admin.php?page=wc-settings&tab=mondial_relay">'.__('Mondial Relay configuration page', 'wc-multishipping').'</a>'
            );

            return false;
        }
        $this->payload['Enseigne'] = get_option('wms_mondial_relay_customer_code', '');

        return $this;
    }

    public function with_shipping_method($order)
    {
        $shipping_method_id = mondial_relay_order::get_shipping_method_name($order);
        if (empty($shipping_method_id)) return false;

        $all_shipping_methods_class = WC()->shipping()->load_shipping_methods();

        $this->payload['ModeCol'] = 'CCC';
        $this->payload['ModeLiv'] = $all_shipping_methods_class[$shipping_method_id]->get_product_code();
        $this->payload['NDossier'] = $order->get_id();
        $this->payload['NClient'] = $order->get_user_id();

        return $this;
    }

    public function with_shipper($order, $is_return_order = false)
    {
        $missing_fields = $this->check_required_fields('shipper');
        if (!empty($missing_fields)) {
            $this->errors[] = sprintf(
                __('Some fields are missing in "Shipping Address" section, please check your %s', 'wc-multishipping'),
                '<a href="'.get_site_url().'/wp-admin/admin.php?page=wc-settings&tab=mondial_relay">'.__('Mondial Relay configuration page', 'wc-multishipping').'</a>'
            );


            return false;
        }

        if (!$is_return_order) {

            $expe_Ad1 = strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_civility ', ''))).' '.substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_name', ''))), 0, 100).' '.substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_name_2', ''))), 0, 100);

            $this->payload['Expe_Langage'] = "FR";
            $this->payload['Expe_Ad1'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(strtoupper(remove_accents($expe_Ad1)), 0, 32));
            $this->payload['Expe_Ad2'] = '';
            $this->payload['Expe_Ad3'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_address_1', ''))), 0, 32));
            $this->payload['Expe_Ad4'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_address_2', ''))), 0, 32));
            $this->payload['Expe_Ville'] = substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_city', ''))), 0, 26);
            $this->payload['Expe_CP'] = substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_zip_code', ''))), 0, 10);
            $this->payload['Expe_Pays'] = substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_country', ''))), 0, 2);
            $this->payload['Expe_Tel1'] = preg_replace('/\s|(\+33|0033)(\d)/', '0$2', preg_replace('/[^0-9\+\-]/', '', substr(get_option('wms_mondial_relay_shipper_phone', ''), 0, 13)));
            $this->payload['Expe_Tel2'] = preg_replace('/\s|(\+33|0033)(\d)/', '0$2', preg_replace('/[^0-9\+\-]/', '', substr(get_option('wms_mondial_relay_shipper_phone', ''), 0, 13)));
            $this->payload['Expe_Mail'] = substr(strtoupper(remove_accents(get_option('wms_mondial_relay_shipper_email', ''))), 0, 70);
        }

        return $this;
    }

    public function with_recipient($order, $is_return_order = 'false')
    {

        if (!$is_return_order) {
            $customer_obj = new \WC_Customer($order->get_customer_id());

            $expe_Ad1 = get_option('wms_mondial_relay_shipper_civility ', '').' '.substr(get_option('wms_mondial_relay_shipper_name', ''), 0, 100).' '.substr(get_option('wms_mondial_relay_shipper_name_2', ''), 0, 100);

            $this->payload['Dest_Langage'] = 'FR';//substr(remove_accents($order->get_shipping_country()), 0, 2);
            $this->payload['Dest_Ad1'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(remove_accents($order->get_shipping_first_name().' '.$order->get_shipping_last_name()), 0, 32));
            $this->payload['Dest_Ad2'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(remove_accents($order->get_shipping_company()), 0, 32));
            $this->payload['Dest_Ad3'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(remove_accents($order->get_shipping_address_1()), 0, 32));

            $order_shipping_method = $order->get_shipping_methods();
            $shipping_method = reset($order_shipping_method);
            if (!in_array($shipping_method->get_method_id(), mondial_relay_order::ID_SHIPPING_METHODS_RELAY)) {
                $this->payload['Dest_Ad4'] = preg_replace("#[^0-9A-Za-z_\-'., \/]#i", '', substr(remove_accents($order->get_shipping_address_2()), 0, 32));
            } else $this->payload['Dest_Ad4'] = '';

            $this->payload['Dest_Ville'] = substr(remove_accents($order->get_shipping_city()), 0, 50);
            $this->payload['Dest_CP'] = substr(((strlen($order->get_shipping_postcode()) == 4 && "FR" == $order->get_shipping_country()) ? "0".$order->get_shipping_postcode() : $order->get_shipping_postcode()), 0, 9);
            $this->payload['Dest_Pays'] = substr(remove_accents($order->get_shipping_country()), 0, 2);
            $this->payload['Dest_Tel1'] = preg_replace('/\s|(\+33|0033)(\d)/', '0$2', preg_replace('/[^0-9\+\-]/', '', substr($order->get_billing_phone(), 0, 13)));
            $this->payload['Dest_Tel2'] = preg_replace('/\s|(\+33|0033)(\d)/', '0$2', preg_replace('/[^0-9\+\-]/', '', substr($order->get_billing_phone(), 0, 13)));
            $this->payload['Dest_Mail'] = substr($order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email(), 0, 70);
        }

        return $this;
    }

    public function with_packages($order, $is_return_order = false)
    {
        $number_of_parcel = mondial_relay_parcel::get_number_of_parcels($order);
        if (empty($number_of_parcel)) return false;

        if (!mondial_relay_parcel::check_parcel_dimensions($order)) return false;

        $parcels_dimensions = mondial_relay_parcel::get_parcels_dimensions($order);
        if (empty($parcels_dimensions)) return false;

        $total_weight = $total_length = 0;

        foreach ($parcels_dimensions as $one_parcel_dimension) {
            $total_weight += $one_parcel_dimension['weight'];
            $total_length += intval($one_parcel_dimension['length']) + intval($one_parcel_dimension['height']) + intval($one_parcel_dimension['width']);
        }

        $shipping_method_id = mondial_relay_order::get_shipping_method_name($order);
        if (empty($shipping_method_id)) return false;

        $order_shipping_method = $order->get_shipping_methods();
        $shipping_method = reset($order_shipping_method);
        $method_settings = get_option('woocommerce_'.$shipping_method_id.'_'.$shipping_method->get_instance_id().'_settings');
        if (!empty($method_settings['packaging_weight'])) $total_weight += intval($method_settings['packaging_weight']);

        $woocommerce_weight_unit = get_option('woocommerce_weight_unit');
        if ($woocommerce_weight_unit == 'kg' && !empty($total_weight)) $total_weight = $total_weight * 1000;


        $this->payload['Poids'] = $total_weight;
        $this->payload['Longueur'] = ceil($total_length);
        $this->payload['Taille'] = '';
        $this->payload['NbColis'] = $number_of_parcel;

        return $this;
    }

    private function with_additional_params($order)
    {
        $pickup_info = $order->get_meta('_wms_mondial_relay_pickup_info', true);

        $this->payload['CRT_Valeur'] = '0';
        $this->payload['CRT_Devise'] = 'EUR';
        $this->payload['Exp_Valeur'] = round(intval(mondial_relay_parcel::get_shipping_value($order)) / 100);
        $this->payload['Exp_Devise'] = 'EUR';
        $this->payload['COL_Rel_Pays'] = '';
        $this->payload['COL_Rel'] = '';
        $this->payload['LIV_Rel_Pays'] = substr(remove_accents($order->get_shipping_country()), 0, 2);
        if (!empty($pickup_info['pickup_id'])) $this->payload['LIV_Rel'] = $pickup_info['pickup_id'];
        $this->payload['TAvisage'] = '';
        $this->payload['TReprise'] = '';
        $this->payload['Montage'] = mondial_relay_parcel::get_installation_duration($order);
        $this->payload['TRDV'] = '';
        $this->payload['Assurance'] = mondial_relay_parcel::get_insurance($order);
        $this->payload['Instructions'] = '';

        return $this;
    }

    private function check_required_fields($field_type)
    {
        if (empty($field_type)) return false;

        $required_fields = [
            'shipper' => [
                'wms_mondial_relay_shipper_civility',
                'wms_mondial_relay_shipper_name',
                'wms_mondial_relay_shipper_name_2',
                'wms_mondial_relay_shipper_address_1',
                'wms_mondial_relay_shipper_zip_code',
                'wms_mondial_relay_shipper_city',
                'wms_mondial_relay_shipper_country',
            ],
            'account' => [
                'wms_mondial_relay_customer_code',
                'wms_mondial_relay_private_key',
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

    protected function with_field_validation()
    {

        foreach ($this->fields as $field_label => $one_field) {
            if (!isset($this->payload[$field_label]) || $this->payload[$field_label] === '') {
                if (!empty($field['required'])) {
                    wms_enqueue_message(sprintf(__('Field %s is required.', 'wc-multishipping'), $field_label), 'error');

                    return false;
                }
                continue;
            }

            if (isset($one_field['regex']) && !preg_match($one_field['regex'], $this->payload[$field_label])) {
                if (!empty($field['required'])) {
                    wms_enqueue_message(sprintf(__('Field %s format is invalid.', 'wc-multishipping'), $field_label), 'error');

                    return false;
                }
                unset($this->payload[$field_label]);
            }
        }

        return true;
    }

    protected function with_tracking_number($order)
    {
        $shipment_data = $order->get_meta('_wms_mondial_relay_shipment_data', true);
        if (empty($shipment_data['_wms_outward_parcels']['_wms_reservation_number'])) return false;

        $this->payload['Expedition'] = $shipment_data['_wms_outward_parcels']['_wms_reservation_number'];

        return true;
    }

    protected function with_language()
    {
        $this->payload['Langue'] = substr(get_locale(), -2);

        return true;
    }


    protected function with_security()
    {
        $code = implode("", $this->payload);
        $code .= get_option('wms_mondial_relay_private_key', '');

        $this->payload["Security"] = strtoupper(md5($code));


        return true;
    }
}