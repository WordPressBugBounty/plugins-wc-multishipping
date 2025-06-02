<?php
/*
Plugin Name:Chronopost & Mondial relay pour WooCommerce - WCMultiShipping
Description: Create Chronopost & Mondial relay shipping labels and send them easily.
Version: 2.5.7
Author: Mondial Relay WooCommerce - WCMultiShipping
Author URI: https://www.wcmultishipping.com/fr/mondial-relay-woocommerce/
License: GPLv2
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


	$shipping_providers = [ "chronopost", "mondial_relay", "ups" ];
	$google_maps_is_used = $mondial_relay_map_is_used = $open_street_maps_is_used = false;

	foreach ( $shipping_providers as $one_shipping_provider ) {
		if ( "google_maps" == get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_map_type', 'openstreetmap' ) && $google_maps_is_used == false ) {
			$google_maps_is_used = true;
			$google_maps_api_key = get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_google_maps_api_key' );
		}
		if ( "mondial_relay_map" == get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_map_type', 'openstreetmap' ) )
			$mondial_relay_map_is_used = true;
		if ( "openstreetmap" == get_option( 'wms_' . $one_shipping_provider . '_section_pickup_points_map_type', 'openstreetmap' ) )
			$open_street_maps_is_used = true;
	}

	//Load Country listing (displayed in the modals)
	global $woocommerce;
	$countries_obj = new \WC_Countries();
	$countries = $countries_obj->__get( 'countries' );

	global $post;

	if ( ! has_block( "woocommerce/checkout", $post->post_content ) && ! is_checkout() )
		return;

	// Initialize WMS global object with shared variables
	$wms_shared_data = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'maps' => array(
			'markers' => array(),
			'instance' => null,
			'google' => null
		),
		'ui' => array(
			'modal' => null,
			'loader' => null,
			'listingContainer' => null
		)
	);

	// Enqueue wp-i18n script
	wp_enqueue_script( 'wp-i18n' );

	// Enqueue the global configuration script
	wp_enqueue_script( 'wms_globals', WMS_FRONT_JS_URL . 'pickups' . DS . 'wms-globals.js', [ 'wp-i18n', 'jquery' ], '1.0', true );

	// Localize the script with our data
	wp_localize_script( 'wms_globals', 'WMS', $wms_shared_data );

	//Includes the modals

	if ( $google_maps_is_used ) {
		include WMS_FRONT_PARTIALS . 'pickups' . DS . 'google_maps' . DS . 'modal.php';
		wp_enqueue_script( 'wms_pickup_modal_google_maps', WMS_FRONT_JS_URL . 'pickups/google_maps/google_maps_pickup_widget.js?time=' . time(), [ 'jquery', 'wms_globals' ], '', true );
		wp_set_script_translations( 'wms_pickup_modal_google_maps', 'wc-multishipping' );
		wp_enqueue_script( 'google', 'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&v=quarterly', [], '', true );
	}

	if ( $mondial_relay_map_is_used ) {
		include WMS_FRONT_PARTIALS . 'pickups' . DS . 'mondial_relay' . DS . 'modal.php';
		wp_enqueue_script( 'mondialrelay-leaflet-maps', '//unpkg.com/leaflet/dist/leaflet.js', [], '', true );
		wp_enqueue_script( 'mondialrelay-parcelshoppicker', 'https://widget.mondialrelay.com/parcelshop-picker/jquery.plugin.mondialrelay.parcelshoppicker.js', [], '', true );
		wp_enqueue_script( 'wms_pickup_modal_mondial_relay', WMS_FRONT_JS_URL . 'pickups/mondial_relay/mondial_relay_pickup_widget.js?time=' . time(), [ 'jquery', 'wms_globals' ], '', true );
	}

	if ( $open_street_maps_is_used ) {
		include WMS_FRONT_PARTIALS . 'pickups' . DS . 'openstreetmap' . DS . 'modal.php';
		wp_enqueue_script( 'openstreetmap-leaflet-maps', '//unpkg.com/leaflet/dist/leaflet.js', [], '', true );
		wp_enqueue_script( 'wms_pickup_modal_openstreetmap', WMS_FRONT_JS_URL . 'pickups/openstreetmap/openstreetmap_pickup_widget.js?time=' . time(), [ 'jquery', 'wms_globals' ], '1.0', true );
		wp_set_script_translations( 'wms_pickup_modal_openstreetmap', 'wc-multishipping' );
	}

	//Adding the modal scripts
	wp_enqueue_style( 'wms_pickup_CSS', WMS_FRONT_CSS_URL . 'pickups/wooshippping_pickup_widget.min.css?time=' . time() );

	wp_enqueue_script( 'wms_pickup_modal_woocommerce_block', WMS_FRONT_JS_URL . 'pickups/woocommerce_blocks/wms_pickup_selection_button.js?time=' . time(), [ 'jquery', 'wms_globals' ], '1.0' );

	wp_enqueue_script( 'backbone-modal', WMS_PLUGINS_URL . '/woocommerce/assets/js/admin/backbone-modal.js', [ 'jquery', 'wp-util', 'backbone' ] );
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