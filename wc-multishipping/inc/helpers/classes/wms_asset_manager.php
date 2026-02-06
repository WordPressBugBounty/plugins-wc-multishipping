<?php

namespace WCMultiShipping\inc\helpers\classes;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_asset_manager {

	private static $instance = null;
	private static $scripts_registered = [];
	private static $version = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		self::$version = defined( 'WMS_VERSION' ) ? WMS_VERSION : '1.0.0';
	}

	public function register_script( $handle, $src, $deps = [], $in_footer = true ) {
		if ( isset( self::$scripts_registered[ $handle ] ) ) {
			return; // Already registered
		}

		wp_register_script( $handle, $src, $deps, self::$version, $in_footer );
		self::$scripts_registered[ $handle ] = true;
	}

	public function enqueue_script( $handle, $src = '', $deps = [], $in_footer = true ) {
		if ( ! empty( $src ) && ! isset( self::$scripts_registered[ $handle ] ) ) {
			$this->register_script( $handle, $src, $deps, $in_footer );
		}
		wp_enqueue_script( $handle );
	}

	public function register_pickup_scripts() {
		$this->register_script(
			'wms_globals',
			WMS_SHARED_JS_URL . 'pickups/wms-globals.js',
			[ 'wp-i18n', 'jquery' ],
			true
		);

		$this->register_script(
			'backbone-modal',
			WMS_PLUGINS_URL . '/woocommerce/assets/js/admin/backbone-modal.js',
			[ 'jquery', 'wp-util', 'backbone' ],
			true
		);

		$this->register_script(
			'wms-leaflet',
			'//unpkg.com/leaflet/dist/leaflet.js',
			[],
			true
		);

		$this->register_script(
			'wms_pickup_modal_openstreetmap',
			WMS_SHARED_JS_URL . 'pickups/openstreetmap/openstreetmap_pickup_widget.js',
			[ 'jquery', 'wp-i18n', 'wms-leaflet', 'wms_globals', 'backbone-modal' ],
			true
		);

		$this->register_script(
			'wms-mondialrelay-picker',
			'https://widget.mondialrelay.com/parcelshop-picker/jquery.plugin.mondialrelay.parcelshoppicker.js',
			[ 'jquery', 'wms-leaflet' ],
			true
		);

		$this->register_script(
			'wms_pickup_modal_mondial_relay',
			WMS_SHARED_JS_URL . 'pickups/mondial_relay/mondial_relay_pickup_widget.js',
			[ 'jquery', 'wp-i18n', 'wms-leaflet', 'wms-mondialrelay-picker', 'wms_globals', 'backbone-modal' ],
			true
		);

		$this->register_script(
			'wms_pickup_modal_google_maps',
			WMS_SHARED_JS_URL . 'pickups/google_maps/google_maps_pickup_widget.js',
			[ 'jquery', 'wp-i18n', 'wms_globals', 'backbone-modal', 'google-maps-api' ],
			true
		);

		$this->register_script(
			'wms_pickup_modal_woocommerce_block',
			WMS_SHARED_JS_URL . 'pickups/woocommerce_blocks/wms_pickup_selection_button.js',
			[ 'jquery', 'wms_globals' ],
			true
		);

		$this->register_script(
			'wms_pickup_reset',
			WMS_SHARED_JS_URL . 'pickups/woocommerce_blocks/wms_pickup_reset.js',
			[ 'jquery', 'wms_globals', 'wms_pickup_modal_woocommerce_block' ],
			true
		);
	}

	public function enqueue_pickup_scripts( $providers = [] ) {
		$this->enqueue_script( 'wms_globals' );
		$this->enqueue_script( 'backbone-modal' );

		$wms_shared_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'maps' => [
				'markers' => [],
				'instance' => null,
				'google' => null
			],
			'ui' => [
				'modal' => null,
				'loader' => null,
				'listingContainer' => null
			],
			'strings' => [
				'please_select_pickup' => __( 'Please select a pickup point', 'wc-multishipping' ),
			]
		];
		wp_localize_script( 'wms_globals', 'wms_data', $wms_shared_data );

		foreach ( $providers as $provider => $map_type ) {
			switch ( $map_type ) {
				case 'openstreetmap':
					$this->enqueue_script( 'wms-leaflet' );
					$this->enqueue_script( 'wms_pickup_modal_openstreetmap' );
					wp_set_script_translations( 'wms_pickup_modal_openstreetmap', 'wc-multishipping' );
					break;

				case 'mondial_relay_map':
					$this->enqueue_script( 'wms-leaflet' );
					$this->enqueue_script( 'wms-mondialrelay-picker' );
					$this->enqueue_script( 'wms_pickup_modal_mondial_relay' );
					wp_set_script_translations( 'wms_pickup_modal_mondial_relay', 'wc-multishipping' );
					break;

				case 'google_maps':
					$google_maps_api_key = get_option( 'wms_' . $provider . '_section_pickup_points_google_maps_api_key' );
					if ( ! empty( $google_maps_api_key ) ) {
						$this->register_script(
							'google-maps-api',
							'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&v=quarterly',
							[],
							true
						);
						$this->enqueue_script( 'google-maps-api' );
						$this->enqueue_script( 'wms_pickup_modal_google_maps' );
						wp_set_script_translations( 'wms_pickup_modal_google_maps', 'wc-multishipping' );
					}
					break;
			}
		}

		$this->enqueue_script( 'wms_pickup_modal_woocommerce_block' );
		$this->enqueue_script( 'wms_pickup_reset' );
		wp_set_script_translations( 'wms_pickup_reset', 'wc-multishipping' );
	}

	public static function get_version() {
		return self::$version;
	}
}
