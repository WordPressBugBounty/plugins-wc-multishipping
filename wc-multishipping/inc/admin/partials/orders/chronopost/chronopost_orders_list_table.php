<?php

namespace WCMultiShipping\inc\admin\partials\orders\chronopost;


use WCMultiShipping\inc\admin\classes\chronopost\chronopost_connection_manager;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_parcel;
use WCMultiShipping\inc\admin\partials\orders\abstract_classes\wms_orders_list_table;

class chronopost_orders_list_table extends wms_orders_list_table
{

    const SHIPPING_PROVIDER_NAME = 'Chronopost';
    const CHRONOPOST_PRO_SHIPPING_URL = 'https://www.chronopost.fr/professionnel/#/expedier-colis';

    const BULK_ACTION_GENERATE_OUTWARD = 'bulk-label_generate_outward';
    const BULK_ACTION_GENERATE_INWARD = 'bulk-label_generate_inward';
    const BULK_ACTION_DOWNLOAD = 'bulk-label_download';
    const BULK_ACTION_PRINT = 'bulk-label_print';
    const BULK_ACTION_DELETE = 'bulk-label_delete';

    const CHECKBOX_IDS = 'bulk-wms_cb_id';

    public $helper_class;
    private $chronopost_order_list_actions_displayed = false;

    protected function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    protected function is_chronopost_pro_external_label_mode()
    {
        return 'jwt' === chronopost_connection_manager::get_default_connection_type();
    }

    protected function get_orders_intro_text()
    {
        if ($this->is_chronopost_pro_external_label_mode()) {
            return '';
        }

        return parent::get_orders_intro_text();
    }

    protected function has_external_label_management()
    {
        return $this->is_chronopost_pro_external_label_mode();
    }

    protected function display_order_list_actions()
    {
        if ($this->chronopost_order_list_actions_displayed) {
            return;
        }

        $this->chronopost_order_list_actions_displayed = true;
        parent::display_order_list_actions();

        if ($this->is_chronopost_pro_external_label_mode()) {
            $this->display_chronopost_pro_label_context();
        }
    }

    protected function should_display_upgrade_action()
    {
        if ($this->is_chronopost_pro_external_label_mode()) {
            return false;
        }

        return parent::should_display_upgrade_action();
    }

    protected function should_display_chronopost_pro_upgrade_action()
    {
        return parent::should_display_upgrade_action();
    }

    protected function display_chronopost_pro_label_context()
    {
        ?>
        <div style="flex: 0 0 100%; min-width: 100%; width: 100%; box-sizing: border-box; margin: 0 0 10px; padding: 11px 14px; border-left: 4px solid #72aee6; background: #fff;">
            <p style="margin: 0 0 8px;">
                <strong><?php esc_html_e('Chronopost PRO workflow', 'wc-multishipping'); ?></strong><br>
                <?php esc_html_e('Label creation is handled from your Chronopost account, not from WooCommerce.', 'wc-multishipping'); ?>
            </p>
            <?php
            ?>
            <?php if ($this->should_display_chronopost_pro_upgrade_action()) : ?>
                <p style="margin: 0 0 10px;">
                    <?php esc_html_e('WcMultiShipping Free can display Chronopost orders, but importing and synchronising orders from your shop with the Chronopost PRO workflow requires WcMultiShipping PRO.', 'wc-multishipping'); ?>
                </p>
                <a href="https://www.wcmultishipping.com/fr/tarifs?utm_source=wms_plugin&utm_campaign=go_pro&utm_medium=wms_chronopost_pro_orders" target="_blank" class="button button-primary" rel="noopener noreferrer">
                    <?php esc_html_e('Get Pro version', 'wc-multishipping'); ?>
                </a>
            <?php endif; ?>
            <?php
            ?>
        </div>
        <?php
    }

	protected function display_external_label_management_notice()
	{
		if ($this->should_display_chronopost_pro_upgrade_action()) {
			$disabled_tooltip = __('WcMultishipping Pro version is needed to handle shipping labels directly from your WordPress website. Click on the button below to get it.', 'wc-multishipping');
			$disabled_help_id = 'wms-chronopost-label-action-help';
			?>
			<div style="display: inline-block;">
				<span
					class="button button-primary disabled"
					aria-disabled="true"
					aria-describedby="<?php echo esc_attr($disabled_help_id); ?>"
					aria-label="<?php echo esc_attr($this->get_external_label_management_button_label().' - '.$disabled_tooltip); ?>"
					tabindex="0"
					title="<?php echo esc_attr($disabled_tooltip); ?>"
					style="cursor: help; opacity: .55;"
				>
					<?php echo esc_html($this->get_external_label_management_button_label()); ?>
				</span>
			</div>
			<p id="<?php echo esc_attr($disabled_help_id); ?>" style="order: 99; flex: 0 0 100%; margin: -2px 0 0; color: #646970; font-size: 12px; line-height: 1.35;">
				<?php echo esc_html($disabled_tooltip); ?>
			</p>
			<?php

			return;
		}

		parent::display_external_label_management_notice();
	}

    protected function get_external_label_management_notice_title()
    {
        return __('Chronopost PRO label workflow is active', 'wc-multishipping');
    }

    protected function get_external_label_management_notice_text()
    {
        return __('With Chronopost PRO, shipping labels are generated from the Chronopost professional space. Use Chronopost to import your WooCommerce orders, then create, print, or download labels there.', 'wc-multishipping');
    }

    protected function get_external_label_management_url()
    {
        return self::CHRONOPOST_PRO_SHIPPING_URL;
    }

    protected function get_external_label_management_button_label()
    {
        return __('Generate shipping labels', 'wc-multishipping');
    }

    protected function get_chronopost_pro_shipping_status($wc_order, $order_id)
    {
        $last_event_label = $wc_order->get_meta(chronopost_parcel::LAST_EVENT_LABEL_META_KEY, true);
        if (!empty($last_event_label)) {
            return esc_html($last_event_label);
        }

        $tracking_numbers = chronopost_parcel::get_tracking_numbers_from_order_ids([$order_id]);
        if (empty($tracking_numbers) || !is_array($tracking_numbers)) {
            return '';
        }

        $tracking_links = [];
        foreach (array_unique($tracking_numbers) as $tracking_number) {
            if (empty($tracking_number)) {
                continue;
            }

            $tracking_links[] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url(chronopost_parcel::get_tracking_url($tracking_number)),
                esc_html($tracking_number)
            );
        }

        if (empty($tracking_links)) {
            return '';
        }

        return esc_html__('Tracking received:', 'wc-multishipping') . '<br>' . implode('<br>', $tracking_links);
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
            'wms_shipping_status' => $this->is_chronopost_pro_external_label_mode()
                ? __('Chronopost Shipping status', 'wc-multishipping')
                : __('Actions', 'wc-multishipping'),
            'wms_woo_status'      => __('Order status', 'wc-multishipping'),
        ];

        if ($this->is_chronopost_pro_external_label_mode()) {
            unset($columns['cb']);
        }

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
        if ($this->is_chronopost_pro_external_label_mode()) {
            return [];
        }

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
        if ($this->is_chronopost_pro_external_label_mode()) {
            return;
        }

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

        if (!$this->has_valid_pro_license()) {
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

        if ($this->is_chronopost_pro_external_label_mode()) {
            unset($sortable_columns['wms_shipping_status']);
        }

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
            $shipping_status = $this->is_chronopost_pro_external_label_mode()
                ? $this->get_chronopost_pro_shipping_status($wc_order, $one_order_id)
                : $labels;

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
                'wms_shipping_status' => $shipping_status,
            ];
        }

        return $data;
    }

}
