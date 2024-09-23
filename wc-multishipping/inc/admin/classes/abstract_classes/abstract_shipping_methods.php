<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;


abstract class abstract_shipping_methods {

	const SHIPPING_PROVIDER_ID = '';

	public static function load_shipping_methods() {
		$shipping_method_classes = [];

		if ( is_file( WMS_SHIPPING_METHODS . static::SHIPPING_PROVIDER_ID ) )
			return false;

		$provider_shipping_methods = array_diff( scandir( WMS_SHIPPING_METHODS . static::SHIPPING_PROVIDER_ID ), [ '.', '..', '.DS_Store', 'index.html' ] );

		foreach ( $provider_shipping_methods as $one_provider_shipping_method ) {
			if ( is_file( WMS_SHIPPING_METHODS . static::SHIPPING_PROVIDER_ID . DS . $one_provider_shipping_method ) ) {

				if ( strpos( $one_provider_shipping_method, 'abstract' ) === false ) {
					$class_name = "WCMultiShipping\inc\shipping_methods\\" . static::SHIPPING_PROVIDER_ID . "\\" . str_replace( '.php', '', $one_provider_shipping_method );
					$shipping_method_classes[] = new $class_name();
				}
			}
		}

		return $shipping_method_classes;
	}

	public static function get_all_countries_capabilities_info() {
		$chronopost_capabilities = json_decode( file_get_contents( WMS_INCLUDES . 'resources' . DS . 'chronopost_shipping_methods_per_country_fr.json' ), true );
		$mondial_relay_capabilities = json_decode( file_get_contents( WMS_INCLUDES . 'resources' . DS . 'mondial_relay_shipping_methods_per_country_fr.json' ), true );

		return array_merge_recursive(
			$chronopost_capabilities,
			$mondial_relay_capabilities
		);
	}

	public static function get_one_country_capabilities_info( $country_code = '', $info = '' ) {
		if ( empty( $country_code ) )
			return [];


		foreach ( self::get_all_countries_capabilities_info() as $zone_id => $one_zone ) {

			foreach ( $one_zone['countries'] as $country_ref => $one_country_capabilities ) {
				$country_capabilities[ $country_ref ] = array_merge(
					[ 'zone' => $zone_id ],
					$one_country_capabilities
				);
			}
		}

		$product_info = ! is_null( $country_capabilities ) && ! empty( $country_capabilities[ $country_code ] ) ? $country_capabilities[ $country_code ] : [];

		return isset( $product_info[ $info ] ) ? $product_info[ $info ] : false;
	}

}