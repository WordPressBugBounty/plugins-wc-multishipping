<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define('Wcmultishipping_VERSION', '0.1.0');

class Wcmultishipping_Blocks_Integration implements IntegrationInterface
{

    public function get_name()
    {
        return 'wcmultishipping';
    }

    public function initialize()
    {
        $this->register_pickup_points_block_editor_styles();
        $this->register_main_integration();
    }

    public function register_main_integration()
    {
        $script_path = '/inc/woocommerce_block/index.js';
        $style_path = '/inc/woocommerce_block/style-index.css';

        $script_url = plugins_url($script_path, __FILE__);
        $style_url = plugins_url($style_path, __FILE__);

        $script_asset_path = dirname(__FILE__).'/inc/woocommerce_block/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version'      => $this->get_file_version($script_path),
            );

        wp_enqueue_style(
            'wcmultishipping-blocks-integration',
            $style_url,
            [],
            $this->get_file_version($style_path)
        );

        wp_register_script(
            'wcmultishipping-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
         wp_register_script(
            'wcmultishipping-checkout-pickup-points-block-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
         wp_register_script(
            'wcmultishipping-checkout-pickup-points-block-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'wcmultishipping-blocks-integration',
            'wcmultishipping',
            dirname(__FILE__).'/languages'
        );
    }

    public function get_script_handles()
    {
        return array('wcmultishipping-blocks-integration');
    }

    public function get_editor_script_handles()
    {
        return array('wcmultishipping-blocks-integration');
    }

    public function get_script_data()
    {
        $chronopost_map_to_use = get_option('wms_chronopost_section_pickup_points_map_type', 'openstreetmap');
        $chronopost_modal_id = $chronopost_map_to_use == 'openstreetmap' ? 'wms_pickup_open_modal_openstreetmap' : 'wms_pickup_open_modal_google_maps';

        $MR_map_to_use = get_option('wms_mondial_relay_section_pickup_points_map_type', 'openstreetmap');
        if ($MR_map_to_use == 'openstreetmap')
            $MR_modal_id = 'wms_pickup_open_modal_openstreetmap';
        else if ($MR_map_to_use == 'google_maps')
            $MR_modal_id = 'wms_pickup_open_modal_google_maps';
        else
            $MR_modal_id = 'wms_pickup_open_modal_mondial_relay';

        $UPS_map_to_use = get_option('wms_ups_section_pickup_points_map_type', 'openstreetmap');
        $UPS_modal_id = $UPS_map_to_use == 'openstreetmap' ? 'wms_pickup_open_modal_openstreetmap' : 'wms_pickup_open_modal_google_maps';

        $data = array(
            'wcmultishipping-active'    => true,
            'nonce'                     => wp_create_nonce('wms_pickup_selection'),
            'package-shipping-text'     => __('Your package will be ship to:', 'wc-multishipping'),
            'please-select-pickup-text' => __('Please select a pickup point', 'wc-multishipping'),
            'choose-pickup-text'        => __('Choose a pickup point', 'wc-multishipping'),
            'chronopost_modal_id'       => $chronopost_modal_id,
            'mondial_relay_modal_id'    => $MR_modal_id,
            'ups_modal_id'              => $UPS_modal_id,
        );

        return $data;
    }

    public function register_pickup_points_block_editor_styles()
    {
        $style_path = '/inc/woocommerce_block/style-index.css';

        $style_url = plugins_url($style_path, __FILE__);
        wp_enqueue_style(
            'wcmultishipping-checkout-pickup-points-block',
            $style_url,
            [],
            $this->get_file_version($style_path)
        );
    }
    
    protected function get_file_version($file)
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }

        return Wcmultishipping_VERSION;
    }
}
