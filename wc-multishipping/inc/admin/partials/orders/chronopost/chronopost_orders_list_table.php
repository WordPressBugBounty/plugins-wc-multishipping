<?php

namespace WCMultiShipping\inc\admin\partials\orders\chronopost;


use WCMultiShipping\inc\admin\partials\orders\abstract_classes\wms_orders_list_table;

class chronopost_orders_list_table extends wms_orders_list_table
{

    const SHIPPING_PROVIDER_NAME = 'Chronopost';

    const BULK_ACTION_GENERATE_OUTWARD = 'bulk-label_generate_outward';
    const BULK_ACTION_GENERATE_INWARD = 'bulk-label_generate_inward';
    const BULK_ACTION_DOWNLOAD = 'bulk-label_download';
    const BULK_ACTION_PRINT = 'bulk-label_print';
    const BULK_ACTION_DELETE = 'bulk-label_delete';

    const CHECKBOX_IDS = 'bulk-wms_cb_id';

    public $helper_class;

    protected function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    public function get_columns()
    {
        $columns = [
            'cb'                  => '<input type="checkbox" />',
            'wms_id'              => __('ID', 'wc-multishipping'),
            'wms_date'            => __('Date', 'wc-multishipping'),
            'wms_customer'        => __('Customer', 'wc-multishipping'),
            'wms_address'         => __('Address', 'wc-multishipping'),
            'wms_country'         => __('Country', 'wc-multishipping'),
            'wms_shipping_method' => __('Shipping method', 'wc-multishipping'),
            'wms_woo_status'      => __('Order status', 'wc-multishipping'),
            'wms_shipping_status' => __('Actions', 'wc-multishipping'),
        ];

        return array_map(
            function ($v) {
                return <<<END_HTML
<span style="font-weight:bold;">$v</span>
END_HTML;
            },
            $columns
        );
    }

    public function get_bulk_actions()
    {
        $actions = [
            self::BULK_ACTION_GENERATE_OUTWARD => __('Generate outward labels (Pro version only)', 'wc-multishipping'),
            self::BULK_ACTION_GENERATE_INWARD  => __('Generate inward labels (Pro version only)', 'wc-multishipping'),
            self::BULK_ACTION_DOWNLOAD         => __('Download labels (Pro version only)', 'wc-multishipping'),
            self::BULK_ACTION_PRINT            => __('Print labels (Pro version only)', 'wc-multishipping'),
            self::BULK_ACTION_DELETE           => __('Delete labels (Pro version only)', 'wc-multishipping'),


        ];

        return $actions;
    }

    public function process_bulk_action()
    {
        $wp_nonce = wms_get_var('cmd', '_wpnonce', '');
        $action = 'bulk-'.$this->_args['plural'];
        if (empty($wp_nonce) || !wp_verify_nonce($wp_nonce, $action)) return;

        $action = $this->current_action();
        $ids = wms_get_var('array', self::CHECKBOX_IDS, []);
        if (empty($ids)) return;

        if (!wms_table_exists()) {
            wms_enqueue_message(
                __('WcMultishipping Pro version is needed to handle shipping labels directly from your WordPress website. Click on the button below to get it.', 'wc-multishipping'),
                'error'
            );

            return;
        }

        switch ($action) {
            case self::BULK_ACTION_GENERATE_OUTWARD:
                $this->bulk_generate_outward_labels($ids);
                break;

            case self::BULK_ACTION_GENERATE_INWARD:
                $this->bulk_generate_inward_labels($ids);
                break;

            case self::BULK_ACTION_DOWNLOAD:
                $this->bulk_download_labels($ids);
                break;

            case self::BULK_ACTION_PRINT:
                $this->bulk_print_labels($ids);
                break;

            case self::BULK_ACTION_DELETE:
                $this->bulk_delete_label($ids);
                break;
            default:
                return;
        }
    }

    public function get_sortable_columns()
    {
        $sortable_columns = [
            'wms_id'              => ['wms_id', true],
            'wms_date'            => ['wms_date', false],
            'wms_customer'        => ['wms_customer', false],
            'wms_address'         => ['wms_address', false],
            'wms_country'         => ['wms_country', false],
            'wms_shipping_method' => ['wms_shipping_method', false],
            'wms_woo_status'      => ['wms_woo_status', false],
            'wms_shipping_status' => ['wms_shipping_status', false],
        ];

        return $sortable_columns;
    }


    protected function get_listing_filters()
    {

        $search = wms_get_var('string', 's', '');
        $shipping_method_filter_value = wms_get_var('string', 'shipping_methods', '');
        $shipping_country_filter_value = wms_get_var('string', 'shipping_country', '');
        $woo_status_filter_value = wms_get_var('string', 'woo_status', '');


        return [
            'search'           => !empty($search) ? $search : '',
            'shipping_methods' => !empty($shipping_method_filter_value) ? $shipping_method_filter_value : '',
            'shipping_country' => !empty($shipping_country_filter_value) ? $shipping_country_filter_value : '',
            'woo_status'       => !empty($woo_status_filter_value) ? $woo_status_filter_value : '',
        ];
    }

    protected function get_data($current_page = 0, $per_page = 0, $args = [], $filters = [])
    {
        $data = [];

        $helper_class = $this->helper_class;
        $order_class = $helper_class->get_order_class();

        $wms_orders = $order_class->get_orders($current_page, $per_page, $args, $filters);
        if (empty($wms_orders) || !is_array($wms_orders)) return $data;

        $tracking_numbers = $this->get_formated_tracking_numbers($wms_orders);

        foreach ($wms_orders as $one_order_id) {
            $wc_order = wc_get_order($one_order_id);
            $address = $wc_order->get_shipping_address_1();
            $address .= !empty($wc_order->get_shipping_address_2()) ? '<br>'.$wc_order->get_shipping_address_2() : '';
            $address .= '<br>'.$wc_order->get_shipping_postcode().' '.$wc_order->get_shipping_city();

            $labels = !empty($tracking_numbers[$one_order_id]) ? $tracking_numbers[$one_order_id] : '';

            $data[] = [
                'wms_data_id'         => $one_order_id,
                'cb'                  => '<input type="checkbox" />',
                'wms_id'              => $this->get_order_edit_link($one_order_id),
                'wms_date'            => $wc_order->get_date_created()->date('m-d-Y'),
                'wms_customer'        => $wc_order->get_shipping_first_name().' '.$wc_order->get_shipping_last_name(),
                'wms_address'         => $address,
                'wms_country'         => $wc_order->get_shipping_country(),
                'wms_shipping_method' => $wc_order->get_shipping_method(),
                'wms_woo_status'      => wc_get_order_status_name($wc_order->get_status()),
                'wms_shipping_status' => $labels,
            ];
        }

        return $data;
    }

}


