<?php
/*
Plugin Name: WCMultiShipping — Mondial Relay, Inpost & Chronopost for WooCommerce
Description: Create Chronopost & Mondial relay shipping labels and send them easily.
Version: 3.0.2
Author: Mondial Relay WooCommerce - WCMultiShipping
Author URI: https://www.wcmultishipping.com/fr/mondial-relay-woocommerce/
Requires Plugins: woocommerce
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: wc-multishipping
Domain Path: /languages
*/

namespace WCMultiShipping;

use WCMultiShipping\inc\admin\classes\label_class;
use WCMultiShipping\inc\admin\classes\update_class;
use WCMultiShipping\inc\admin\wms_admin_init;
use WCMultiShipping\inc\front\wms_front_init;

defined( 'ABSPATH' ) or die( 'Sorry you can\'t...' );

if ( ! defined( 'DS' ) )
	define( 'DS', DIRECTORY_SEPARATOR );
include_once __DIR__ . DS . 'inc' . DS . 'helpers' . DS . 'wms_helper_helper.php';

/*
 * Init the plugin
 */

function wms_init( $hook ) {
	if (
		! file_exists( WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . 'woocommerce.php' )
		&& ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
		&& ( is_multisite() && ! in_array(
			'woocommerce/woocommerce.php',
			apply_filters( 'active_plugins', array_keys( get_site_option( 'active_sitewide_plugins' ) ) )
		) )
	)
		return;

	if ( is_admin() || is_network_admin() ) {
		new wms_admin_init();
	} else {
		new wms_front_init();
	}
}


add_action( 'plugins_loaded', __NAMESPACE__ . '\\wms_init', 999 );

add_action( 'init', function () {

	//Used to submit translations to WP
	$translations = [ 
		__( 'Generate outward labels', 'wc-multishipping' ),
		__( 'Download labels', 'wc-multishipping' ),
		__( 'Print labels', 'wc-multishipping' ),
		__( 'Delete labels', 'wc-multishipping' ),
		__( "Display pickup points map via", "wc-multishipping" ),
		__( "Automatically generate label on these order status", "wc-multishipping" ),
		__( "Status to set after label generation", "wc-multishipping" ),
		__( "Send tracking link via email once the label is generated?", "wc-multishipping" ),
		__( 'Click here to ship this order with %s', 'wc-multishipping' )
	];

} );


// Hook: Frontend assets.
add_action( 'enqueue_block_assets', function () {

	if ( ! function_exists( 'register_block_type' ) || ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) ) ) {
		// Gutenberg is not active.
		return;
	}

	// Use the centralized asset manager
	$asset_manager = \WCMultiShipping\inc\helpers\classes\wms_asset_manager::get_instance();
	$asset_manager->register_pickup_scripts();

	$shipping_providers = [ "chronopost", "mondial_relay", "ups" ];
	$providers_config = [];
	$google_maps_is_used = $mondial_relay_map_is_used = $open_street_maps_is_used = false;

	foreach ( $shipping_providers as $one_shipping_provider ) {
		$map_type = get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_map_type', 'openstreetmap' );
		
		if ( "google_maps" == $map_type && $google_maps_is_used == false ) {
			$google_maps_is_used = true;
			$google_maps_api_key = get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_google_maps_api_key' );
			$providers_config[ $one_shipping_provider ] = 'google_maps';
		} elseif ( "mondial_relay_map" == $map_type ) {
			$mondial_relay_map_is_used = true;
			$providers_config[ $one_shipping_provider ] = 'mondial_relay_map';
		} elseif ( "openstreetmap" == $map_type ) {
			$open_street_maps_is_used = true;
			$providers_config[ $one_shipping_provider ] = 'openstreetmap';
		}
	}

	//Load Country listing (displayed in the modals)
	global $woocommerce;
	$countries_obj = new \WC_Countries();
	$countries = $countries_obj->__get( 'countries' );

	global $post;

	if ( ! has_block( "woocommerce/checkout", $post->post_content ) && ! is_checkout() )
		return;

	// Use the asset manager to enqueue all necessary scripts
	$asset_manager->enqueue_pickup_scripts( $providers_config );

	// Include modals
	if ( $google_maps_is_used ) {
		include WMS_SHARED_PARTIALS . 'pickups' . DS . 'google_maps' . DS . 'modal.php';
	}

	if ( $mondial_relay_map_is_used ) {
		include WMS_SHARED_PARTIALS . 'pickups' . DS . 'mondial_relay' . DS . 'modal.php';
	}

	if ( $open_street_maps_is_used ) {
		include WMS_SHARED_PARTIALS . 'pickups' . DS . 'openstreetmap' . DS . 'modal.php';
	}

	// Enqueue CSS
	wp_enqueue_style( 'wms_pickup_CSS', WMS_SHARED_CSS_URL . 'pickups/wooshippping_pickup_widget.min.css', [], \WCMultiShipping\inc\helpers\classes\wms_asset_manager::get_version() );
} );



add_action( 'woocommerce_blocks_loaded', function () {
	require_once __DIR__ . '/wcmultishipping-blocks-integration.php';
	add_action(
		'woocommerce_blocks_checkout_block_registration',
		function ($integration_registry) {
			$integration_registry->register( new \Wcmultishipping_Blocks_Integration() );
		}
	);
} );

//__START__free_
/*
 * Activation hook
 */
register_activation_hook( __FILE__, function() {
	// Set transient to trigger redirect to welcome page
	set_transient( 'wms_activation_redirect', true, 30 );
} );
//__END__free_