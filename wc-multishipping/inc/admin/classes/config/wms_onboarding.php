<?php

namespace WCMultiShipping\inc\admin\classes\config;

use WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_connection_manager;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_settings;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_shipping_methods;
use WCMultiShipping\inc\admin\classes\customer\wms_customer_registration;
use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_shipping_methods;
use WCMultiShipping\inc\admin\classes\telemetry\wms_telemetry;
use WCMultiShipping\inc\front\pickup\chronopost\chronopost_pickup_widget;
use WCMultiShipping\inc\front\pickup\mondial_relay\mondial_relay_pickup_widget;

defined( 'ABSPATH' ) || exit;

class wms_onboarding {
	const OPTION_STATE = 'wms_onboarding_state';
	const OPTION_DRAFT = 'wms_onboarding_draft';
	const WIZARD_VERSION = '1.0.0';

	const STEP_ACTIVATION = 'activation';
	const STEP_CARRIERS = 'carriers';
	const STEP_CONNECTION = 'connection';
	const STEP_ADDRESSES = 'addresses';
	const STEP_RATES = 'rates';
	const STEP_PREVIEW = 'preview';

	public static function register_admin_hooks() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_fresh_install_to_wizard' ] );
		add_action( 'admin_post_wms_save_onboarding_step', [ __CLASS__, 'handle_save_step' ] );
		add_action( 'admin_post_wms_onboarding_dismiss', [ __CLASS__, 'handle_dismiss' ] );
		add_action( 'admin_post_wms_onboarding_complete', [ __CLASS__, 'handle_complete' ] );
		add_action( 'admin_post_wms_onboarding_preview', [ __CLASS__, 'handle_preview_redirect' ] );
	}

	public static function register_front_hooks() {
		add_action( 'template_redirect', [ __CLASS__, 'handle_preview_request' ] );
	}

	public static function get_view_data() {
		$draft         = self::get_draft();
		$show_wizard   = self::should_show_wizard();
		$current_step  = self::get_current_step( $show_wizard );
		$zone_choices  = self::get_zone_choices();
		$zone_id       = self::sanitize_zone_id( $draft['zone_id'], $zone_choices );
		$services      = self::get_service_definitions();
		$selected_ids  = self::get_selected_services( $draft );
		$selected      = [];

		if ( $show_wizard ) {
			self::mark_presented( $current_step );
		}

		foreach ( $selected_ids as $service_id ) {
			if ( isset( $services[ $service_id ] ) ) {
				$selected[ $service_id ] = self::get_service_view( $service_id, $zone_id );
			}
		}

		$preview_products = self::get_preview_product_choices();
		$preview_product_id = self::sanitize_preview_product_id( $draft['preview_product_id'], $preview_products );
		$store_defaults = self::get_store_defaults();
		$step_statuses = self::get_step_statuses( $draft, $zone_id, $selected_ids );

		return [
			'registration' => self::get_registration_view_data(),
			'show_wizard' => $show_wizard,
			'current_step' => $current_step,
			'steps' => self::get_step_view_models( $current_step, $step_statuses ),
			'state' => self::get_state(),
			'draft' => $draft,
			'zone_id' => $zone_id,
			'zone_choices' => $zone_choices,
			'services' => $services,
			'selected_service_ids' => $selected_ids,
			'selected_services' => $selected,
			'selected_carriers' => self::get_selected_carriers( $draft ),
			'preview_products' => $preview_products,
			'preview_product_id' => $preview_product_id,
			'store_defaults' => $store_defaults,
			'addresses' => self::get_address_values( $store_defaults ),
			'connection' => self::get_connection_view_data(),
			'step_statuses' => $step_statuses,
			'has_existing_setup' => self::has_existing_setup(),
			'links' => self::get_links( $zone_id ),
			'dashboard' => self::get_dashboard_view_data( $draft, $zone_id, $selected_ids, $selected, $preview_products, $preview_product_id, $step_statuses ),
			'field_definitions' => [
				'sender' => self::get_sender_fields(),
				'chronopost_shipper' => self::get_chronopost_shipper_fields(),
				'chronopost_customer' => self::get_chronopost_customer_fields(),
				'mondial_relay_shipper' => self::get_mondial_relay_shipper_fields(),
			],
		];
	}

	public static function handle_save_step() {
		self::authorize_post();

		$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );

		switch ( $step ) {
			case self::STEP_CARRIERS:
				self::save_carrier_step();
				break;
			case self::STEP_CONNECTION:
				self::save_connection_step();
				break;
			case self::STEP_ADDRESSES:
				self::save_address_step();
				break;
			case self::STEP_RATES:
				self::save_rates_step();
				break;
		}

		self::redirect_to_wizard( self::STEP_CARRIERS );
	}

	public static function handle_dismiss() {
		self::authorize_post();

		$state = self::get_state();
		$state['wizard_version'] = self::WIZARD_VERSION;
		$state['dismissed_at'] = current_time( 'mysql' );
		$state['last_step'] = sanitize_key( wp_unslash( $_POST['current_step'] ?? self::STEP_CARRIERS ) );

		update_option( self::OPTION_STATE, $state, false );
		wms_telemetry::track( 'plugin_onboarding_dismissed', [
			'step' => $state['last_step'],
			'result' => 'success',
		] );
		wms_enqueue_message( __( 'The onboarding has been hidden. You can relaunch it anytime from the dashboard.', 'wc-multishipping' ), 'success' );

		self::redirect_to_dashboard();
	}

	public static function handle_complete() {
		self::authorize_post();

		$draft = self::get_draft();
		$preview_product_id = absint( wp_unslash( $_POST['preview_product_id'] ?? 0 ) );

		if ( empty( $preview_product_id ) ) {
			$preview_product_id = absint( $draft['preview_product_id'] ?? 0 );
		}

		if (
			empty( $draft['checkout_preview_opened_at'] )
			|| (int) ( $draft['checkout_preview_product_id'] ?? 0 ) !== $preview_product_id
		) {
			wms_enqueue_message( __( 'Please open the checkout preview before finishing onboarding.', 'wc-multishipping' ), 'error' );
			self::redirect_to_wizard( self::STEP_PREVIEW );
		}

		$draft['preview_product_id'] = $preview_product_id;
		self::save_draft( $draft );

		$state = self::get_state();
		$state['wizard_version'] = self::WIZARD_VERSION;
		$state['completed_at'] = current_time( 'mysql' );
		$state['last_step'] = self::STEP_PREVIEW;

		update_option( self::OPTION_STATE, $state, false );
		wms_telemetry::track( 'plugin_onboarding_step_completed', [
			'step' => self::STEP_PREVIEW,
			'result' => 'success',
		] );
		wms_telemetry::track( 'plugin_onboarding_completed', [
			'step' => self::STEP_PREVIEW,
			'result' => 'success',
		] );
		wms_enqueue_message( __( 'The onboarding is complete. You can reopen it whenever you need from the WCMultiShipping dashboard.', 'wc-multishipping' ), 'success' );

		self::redirect_to_dashboard();
	}

	public static function handle_preview_redirect() {
		self::authorize_post();

		$draft = self::get_draft();
		$preview_products = self::get_preview_product_choices();
		$product_id = self::sanitize_preview_product_id( absint( wp_unslash( $_POST['preview_product_id'] ?? 0 ) ), $preview_products );

		if ( empty( $product_id ) ) {
			wms_enqueue_message( __( 'No compatible preview product was found. Add a shippable product or variation with a weight, then try again.', 'wc-multishipping' ), 'error' );
			self::redirect_to_wizard( self::STEP_PREVIEW );
		}

		$draft['preview_product_id'] = $product_id;
		$draft['checkout_preview_product_id'] = $product_id;
		$draft['checkout_preview_opened_at'] = current_time( 'mysql' );
		self::save_draft( $draft );
		wms_telemetry::track( 'shipping_rate_preview_run', [
			'step' => self::STEP_PREVIEW,
			'result' => 'started',
		] );

		$preview_url = add_query_arg(
			[
				'wms_wizard_preview' => 1,
				'product_id' => $product_id,
				'zone_id' => $draft['zone_id'],
			],
			home_url( '/' )
		);

		wp_safe_redirect( $preview_url );
		exit;
	}

	public static function handle_preview_request() {
		if ( ! isset( $_GET['wms_wizard_preview'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product_id = absint( wp_unslash( $_GET['product_id'] ?? 0 ) );
		$zone_id    = absint( wp_unslash( $_GET['zone_id'] ?? 0 ) );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product || ! $product->exists() ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$draft           = self::get_draft();
		$zone_choices    = self::get_zone_choices();
		$sanitized_zone_id = self::sanitize_zone_id( $zone_id, $zone_choices );
		$preview_address = self::get_preview_address_for_zone( $zone_id );

		WC()->cart->empty_cart();
		$cart_product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
		$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
		$variation = $product->is_type( 'variation' ) ? $product->get_variation_attributes() : [];
		WC()->cart->add_to_cart( $cart_product_id, 1, $variation_id, $variation );
		self::clear_preview_pickup_state();

		if ( WC()->customer ) {
			WC()->customer->set_shipping_country( $preview_address['country'] );
			WC()->customer->set_shipping_state( $preview_address['state'] );
			WC()->customer->set_shipping_postcode( $preview_address['postcode'] );
			WC()->customer->set_shipping_city( $preview_address['city'] );
			WC()->customer->set_shipping_address( $preview_address['address_1'] );
			WC()->customer->set_shipping_address_2( $preview_address['address_2'] );
			WC()->customer->set_billing_country( $preview_address['country'] );
			WC()->customer->set_billing_state( $preview_address['state'] );
			WC()->customer->set_billing_postcode( $preview_address['postcode'] );
			WC()->customer->set_billing_city( $preview_address['city'] );
			WC()->customer->set_billing_address( $preview_address['address_1'] );
			WC()->customer->set_billing_address_2( $preview_address['address_2'] );
			WC()->customer->save();
		}

		WC()->cart->calculate_totals();
		self::prime_preview_shipping_method( $draft, $sanitized_zone_id );
		$has_shipping_rates = self::cart_has_shipping_rates();
		wms_telemetry::track( 'shipping_rate_preview_result', [
			'step' => self::STEP_PREVIEW,
			'result' => $has_shipping_rates ? 'success' : 'no_rates',
		] );

		if ( ! $has_shipping_rates ) {
			wms_telemetry::track( 'shipping_rate_error_seen', [
				'step' => self::STEP_PREVIEW,
				'result' => 'error',
				'error_category' => 'no_rates',
			] );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	public static function maybe_redirect_fresh_install_to_wizard() {
		if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		$view = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );

		if ( 'wc-multishipping' !== $page || '' !== $view ) {
			return;
		}

		if ( ! self::should_show_wizard() ) {
			return;
		}

		self::redirect_to_wizard( self::is_registration_required() ? self::STEP_ACTIVATION : self::STEP_CARRIERS );
	}

	public static function should_show_wizard() {
		$requested_view = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );

		if ( 'wizard' === $requested_view ) {
			return true;
		}

		if ( self::is_registration_required() ) {
			return true;
		}

		if ( 'dashboard' === $requested_view ) {
			return false;
		}

		if ( ! self::is_fresh_install_for_onboarding() ) {
			return false;
		}

		$state = self::get_state();

		return empty( $state['wizard_version'] ) || $state['wizard_version'] !== self::WIZARD_VERSION || empty( $state['presented_at'] );
	}

	public static function get_current_step( $show_wizard ) {
		$steps = array_keys( self::get_steps() );
		$query_step = sanitize_key( wp_unslash( $_GET['step'] ?? '' ) );

		if ( self::is_registration_required() ) {
			return self::STEP_ACTIVATION;
		}

		if ( in_array( $query_step, $steps, true ) ) {
			return $query_step;
		}

		if ( ! $show_wizard ) {
			return self::STEP_PREVIEW;
		}

		$state = self::get_state();
		if ( in_array( $state['last_step'], $steps, true ) ) {
			return $state['last_step'];
		}

		return self::STEP_CARRIERS;
	}

	public static function get_registration_view_data() {
		return [
			'installation_registered' => (bool) get_option( 'wms_customer_installation_registered', false ),
			'email_required' => isset( $_GET['email_required'] ) && '1' === wp_unslash( $_GET['email_required'] ),
			'mode' => method_exists( config_class::class, 'check_wms_api_key' ) ? 'license' : 'email',
			'wms_api_key' => get_option( 'wms_api_key', '' ),
			'wms_license_expiration_date' => (int) get_option( 'wms_license_expiration_date', 0 ),
			'customer_email' => get_option( 'wms_customer_email', wp_get_current_user()->user_email ),
			'telemetry_enabled' => wms_telemetry::is_enabled(),
			'telemetry_preference_saved' => wms_telemetry::has_saved_preference(),
		];
	}

	public static function is_registration_required() {
		return self::is_email_registration_mode() && ! (bool) get_option( 'wms_customer_installation_registered', false );
	}

	public static function get_state() {
		$state = get_option( self::OPTION_STATE, [] );

		if ( ! is_array( $state ) ) {
			$state = [];
		}

		return wp_parse_args(
			$state,
			[
				'wizard_version' => '',
				'presented_at' => '',
				'dismissed_at' => '',
				'completed_at' => '',
				'last_step' => self::STEP_CARRIERS,
				'fresh_install_detected_at' => '',
				'existing_setup_detected_at' => '',
				'auto_eligible' => '',
			]
		);
	}

	public static function get_draft() {
		$draft = get_option( self::OPTION_DRAFT, [] );

		if ( ! is_array( $draft ) || empty( $draft['wizard_version'] ) || $draft['wizard_version'] !== self::WIZARD_VERSION ) {
			$draft = self::build_default_draft();
			self::save_draft( $draft );
		}

		return wp_parse_args(
			$draft,
			[
				'wizard_version' => self::WIZARD_VERSION,
				'selected_carriers' => [],
				'selected_services' => [],
				'zone_id' => self::get_recommended_zone_id(),
				'preview_product_id' => 0,
				'checkout_preview_product_id' => 0,
				'checkout_preview_opened_at' => '',
			]
		);
	}

	public static function save_draft( $draft ) {
		$draft['wizard_version'] = self::WIZARD_VERSION;
		update_option( self::OPTION_DRAFT, $draft, false );
	}

	public static function get_service_definitions() {
		return [
			'chronopost_13' => [
				'carrier' => 'chronopost',
				'title' => __( 'Chronopost Home 13h', 'wc-multishipping' ),
				'description' => __( 'Express home delivery before 1 PM.', 'wc-multishipping' ),
				'default_title' => __( 'Chronopost 13h', 'wc-multishipping' ),
			],
			'chronopost_relais' => [
				'carrier' => 'chronopost',
				'title' => __( 'Chronopost Pickup Relay', 'wc-multishipping' ),
				'description' => __( 'Pickup-point delivery with Chronopost relay selection at checkout.', 'wc-multishipping' ),
				'default_title' => __( 'Chronopost Relay', 'wc-multishipping' ),
			],
			'mondial_relay_point_relais' => [
				'carrier' => 'mondial_relay',
				'title' => __( 'Mondial Relay Point Relais', 'wc-multishipping' ),
				'description' => __( 'Classic relay-point delivery with Mondial Relay pickup selection.', 'wc-multishipping' ),
				'default_title' => __( 'Mondial Relay Point Relais', 'wc-multishipping' ),
			],
			'mondial_relay_lockers' => [
				'carrier' => 'mondial_relay',
				'title' => __( 'Mondial Relay Lockers', 'wc-multishipping' ),
				'description' => __( 'Locker delivery when you want to offer parcel lockers in checkout.', 'wc-multishipping' ),
				'default_title' => __( 'Mondial Relay Lockers', 'wc-multishipping' ),
			],
		];
	}

	public static function get_steps() {
		$steps = [];

		if ( self::is_email_registration_mode() ) {
			$steps[ self::STEP_ACTIVATION ] = [
				'label' => __( 'Activation', 'wc-multishipping' ),
				'kicker' => __( 'Step 1', 'wc-multishipping' ),
				'title' => __( 'Confirm your installation', 'wc-multishipping' ),
				'description' => __( 'Add the support email used for this shop before configuring your carriers.', 'wc-multishipping' ),
			];
		}

		$step_offset = count( $steps );

		return $steps + [
			self::STEP_CARRIERS => [
				'label' => __( 'Carriers', 'wc-multishipping' ),
				'kicker' => sprintf( __( 'Step %d', 'wc-multishipping' ), $step_offset + 1 ),
				'title' => __( 'Choose your carriers', 'wc-multishipping' ),
				'description' => __( 'Pick the carriers you want to configure. The default delivery methods will be prepared in the next steps.', 'wc-multishipping' ),
			],
			self::STEP_CONNECTION => [
				'label' => __( 'Connection', 'wc-multishipping' ),
				'kicker' => sprintf( __( 'Step %d', 'wc-multishipping' ), $step_offset + 2 ),
				'title' => __( 'Connect your carrier accounts', 'wc-multishipping' ),
				'description' => __( 'Save your account details, test them, or connect Chronopost PRO directly from here.', 'wc-multishipping' ),
			],
			self::STEP_ADDRESSES => [
				'label' => __( 'Addresses', 'wc-multishipping' ),
				'kicker' => sprintf( __( 'Step %d', 'wc-multishipping' ), $step_offset + 3 ),
				'title' => __( 'Fill sender and billing details', 'wc-multishipping' ),
				'description' => __( 'Use your store address as a starting point, then adjust the sender and billing details required by each carrier.', 'wc-multishipping' ),
			],
			self::STEP_RATES => [
				'label' => __( 'Rates', 'wc-multishipping' ),
				'kicker' => sprintf( __( 'Step %d', 'wc-multishipping' ), $step_offset + 4 ),
				'title' => __( 'Show pickup methods at checkout', 'wc-multishipping' ),
				'description' => __( 'Choose the quickest way to make your delivery methods visible at checkout.', 'wc-multishipping' ),
			],
			self::STEP_PREVIEW => [
				'label' => __( 'Preview', 'wc-multishipping' ),
				'kicker' => sprintf( __( 'Step %d', 'wc-multishipping' ), $step_offset + 5 ),
				'title' => __( 'Checkout preview', 'wc-multishipping' ),
				'description' => '',
			],
		];
	}

	public static function get_zone_choices() {
		$choices = [];
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone ) {
			$choices[ (int) $zone['id'] ] = $zone['zone_name'];
		}

		$choices[0] = __( 'Everywhere / locations not covered by other zones', 'wc-multishipping' );

		return $choices;
	}

	public static function get_store_defaults() {
		$current_user = wp_get_current_user();

		return [
			'civility' => 'E',
			'first_name' => $current_user->first_name ?: get_bloginfo( 'name' ),
			'last_name' => $current_user->last_name ?: '',
			'contact_name' => get_bloginfo( 'name' ),
			'address_1' => get_option( 'woocommerce_store_address', '' ),
			'address_2' => get_option( 'woocommerce_store_address_2', '' ),
			'zip_code' => get_option( 'woocommerce_store_postcode', '' ),
			'city' => get_option( 'woocommerce_store_city', '' ),
			'country' => wc_get_base_location()['country'] ?? 'FR',
			'state' => wc_get_base_location()['state'] ?? '',
			'email' => get_option( 'admin_email', $current_user->user_email ),
			'phone' => get_option( 'woocommerce_store_phone', '' ),
			'mobile_phone' => get_option( 'woocommerce_store_phone', '' ),
		];
	}

	public static function get_links( $zone_id ) {
		return [
			'dashboard' => admin_url( 'admin.php?page=wc-multishipping&view=dashboard' ),
			'wizard' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=' . self::STEP_CARRIERS ),
			'shipping' => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			'zone' => $zone_id > 0 ? admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone_id ) : admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			'chronopost' => admin_url( 'admin.php?page=wc-settings&tab=chronopost' ),
			'mondial_relay' => admin_url( 'admin.php?page=wc-settings&tab=mondial_relay' ),
			'support' => 'https://www.wcmultishipping.com/support/',
		];
	}

	public static function get_connection_view_data() {
		$connection_type = chronopost_connection_manager::get_default_connection_type();
		$auth_jwt = new chronopost_auth_jwt();
		$refresh_expired = $auth_jwt->is_refresh_token_expired();
		$is_connected = 'jwt' === $connection_type && $auth_jwt->is_enrollment_complete() && ! $refresh_expired;
		$portal_payload = chronopost_settings::get_pro_portal_payload( $auth_jwt, $refresh_expired );

		return [
			'chronopost' => [
				'connection_type' => $connection_type,
				'is_connected' => $is_connected,
				'refresh_expired' => $refresh_expired,
				'has_keys' => $auth_jwt->has_keys(),
				'portal_uri' => chronopost_settings::get_chronopost_pro_portal_uri(),
				'portal_payload' => is_wp_error( $portal_payload ) ? [] : $portal_payload,
				'portal_error' => is_wp_error( $portal_payload ) ? $portal_payload->get_error_message() : '',
				'account_number' => get_option( 'wms_chronopost_account_number', '' ),
				'account_name' => get_option( 'wms_chronopost_account_name', '' ),
				'account_password' => get_option( 'wms_chronopost_account_password', '' ),
				'has_saved_password' => '' !== trim( (string) get_option( 'wms_chronopost_account_password', '' ) ),
			],
			'mondial_relay' => [
				'customer_code' => get_option( 'wms_mondial_relay_customer_code', '' ),
				'private_key' => get_option( 'wms_mondial_relay_private_key', '' ),
				'has_saved_private_key' => '' !== trim( (string) get_option( 'wms_mondial_relay_private_key', '' ) ),
				'brand_code' => get_option( 'wms_mondial_relay_brand_code', '' ),
			],
		];
	}

	public static function get_address_values( $store_defaults ) {
		return [
			'wms_sender_name' => get_option( 'wms_chronopost_shipper_name', get_option( 'wms_mondial_relay_shipper_name', $store_defaults['first_name'] ) ),
			'wms_sender_name_2' => get_option( 'wms_chronopost_shipper_name_2', get_option( 'wms_mondial_relay_shipper_name_2', $store_defaults['last_name'] ) ),
			'wms_sender_contact_name' => get_option( 'wms_chronopost_shipper_contact_name', $store_defaults['contact_name'] ),
			'wms_sender_address_1' => get_option( 'wms_chronopost_shipper_address_1', get_option( 'wms_mondial_relay_shipper_address_1', $store_defaults['address_1'] ) ),
			'wms_sender_address_2' => get_option( 'wms_chronopost_shipper_address_2', get_option( 'wms_mondial_relay_shipper_address_2', $store_defaults['address_2'] ) ),
			'wms_sender_zip_code' => get_option( 'wms_chronopost_shipper_zip_code', get_option( 'wms_mondial_relay_shipper_zip_code', $store_defaults['zip_code'] ) ),
			'wms_sender_city' => get_option( 'wms_chronopost_shipper_city', get_option( 'wms_mondial_relay_shipper_city', $store_defaults['city'] ) ),
			'wms_sender_country' => get_option( 'wms_chronopost_shipper_country', get_option( 'wms_mondial_relay_shipper_country', $store_defaults['country'] ) ),
			'wms_sender_email' => get_option( 'wms_chronopost_shipper_email', get_option( 'wms_mondial_relay_shipper_email', $store_defaults['email'] ) ),
			'wms_sender_phone' => get_option( 'wms_chronopost_shipper_phone', get_option( 'wms_mondial_relay_shipper_phone', $store_defaults['phone'] ) ),
			'wms_sender_mobile_phone' => get_option( 'wms_chronopost_shipper_mobile_phone', get_option( 'wms_mondial_relay_shipper_mobile_phone', $store_defaults['mobile_phone'] ) ),
			'wms_chronopost_shipper_civility' => get_option( 'wms_chronopost_shipper_civility', 'E' ),
			'wms_chronopost_shipper_name' => get_option( 'wms_chronopost_shipper_name', $store_defaults['first_name'] ),
			'wms_chronopost_shipper_name_2' => get_option( 'wms_chronopost_shipper_name_2', $store_defaults['last_name'] ),
			'wms_chronopost_shipper_address_1' => get_option( 'wms_chronopost_shipper_address_1', $store_defaults['address_1'] ),
			'wms_chronopost_shipper_address_2' => get_option( 'wms_chronopost_shipper_address_2', $store_defaults['address_2'] ),
			'wms_chronopost_shipper_zip_code' => get_option( 'wms_chronopost_shipper_zip_code', $store_defaults['zip_code'] ),
			'wms_chronopost_shipper_city' => get_option( 'wms_chronopost_shipper_city', $store_defaults['city'] ),
			'wms_chronopost_shipper_country' => get_option( 'wms_chronopost_shipper_country', $store_defaults['country'] ),
			'wms_chronopost_shipper_contact_name' => get_option( 'wms_chronopost_shipper_contact_name', $store_defaults['contact_name'] ),
			'wms_chronopost_shipper_email' => get_option( 'wms_chronopost_shipper_email', $store_defaults['email'] ),
			'wms_chronopost_shipper_phone' => get_option( 'wms_chronopost_shipper_phone', $store_defaults['phone'] ),
			'wms_chronopost_shipper_mobile_phone' => get_option( 'wms_chronopost_shipper_mobile_phone', $store_defaults['mobile_phone'] ),
			'wms_chronopost_customer_civility' => get_option( 'wms_chronopost_customer_civility', 'E' ),
			'wms_chronopost_customer_name' => get_option( 'wms_chronopost_customer_name', $store_defaults['first_name'] ),
			'wms_chronopost_customer_name_2' => get_option( 'wms_chronopost_customer_name_2', $store_defaults['last_name'] ),
			'wms_chronopost_customer_address_1' => get_option( 'wms_chronopost_customer_address_1', $store_defaults['address_1'] ),
			'wms_chronopost_customer_address_2' => get_option( 'wms_chronopost_customer_address_2', $store_defaults['address_2'] ),
			'wms_chronopost_customer_zip_code' => get_option( 'wms_chronopost_customer_zip_code', $store_defaults['zip_code'] ),
			'wms_chronopost_customer_city' => get_option( 'wms_chronopost_customer_city', $store_defaults['city'] ),
			'wms_chronopost_customer_country' => get_option( 'wms_chronopost_customer_country', $store_defaults['country'] ),
			'wms_chronopost_customer_contact_name' => get_option( 'wms_chronopost_customer_contact_name', $store_defaults['contact_name'] ),
			'wms_chronopost_customer_email' => get_option( 'wms_chronopost_customer_email', $store_defaults['email'] ),
			'wms_chronopost_customer_phone' => get_option( 'wms_chronopost_customer_phone', $store_defaults['phone'] ),
			'wms_chronopost_customer_mobile_phone' => get_option( 'wms_chronopost_customer_mobile_phone', $store_defaults['mobile_phone'] ),
			'wms_mondial_relay_shipper_civility' => get_option( 'wms_mondial_relay_shipper_civility', 'MR' ),
			'wms_mondial_relay_shipper_name' => get_option( 'wms_mondial_relay_shipper_name', $store_defaults['first_name'] ),
			'wms_mondial_relay_shipper_name_2' => get_option( 'wms_mondial_relay_shipper_name_2', $store_defaults['last_name'] ),
			'wms_mondial_relay_shipper_address_1' => get_option( 'wms_mondial_relay_shipper_address_1', $store_defaults['address_1'] ),
			'wms_mondial_relay_shipper_address_2' => get_option( 'wms_mondial_relay_shipper_address_2', $store_defaults['address_2'] ),
			'wms_mondial_relay_shipper_zip_code' => get_option( 'wms_mondial_relay_shipper_zip_code', $store_defaults['zip_code'] ),
			'wms_mondial_relay_shipper_city' => get_option( 'wms_mondial_relay_shipper_city', $store_defaults['city'] ),
			'wms_mondial_relay_shipper_country' => get_option( 'wms_mondial_relay_shipper_country', $store_defaults['country'] ),
			'wms_mondial_relay_shipper_email' => get_option( 'wms_mondial_relay_shipper_email', $store_defaults['email'] ),
			'wms_mondial_relay_shipper_phone' => get_option( 'wms_mondial_relay_shipper_phone', $store_defaults['phone'] ),
			'wms_mondial_relay_shipper_mobile_phone' => get_option( 'wms_mondial_relay_shipper_mobile_phone', $store_defaults['mobile_phone'] ),
		];
	}

	public static function get_dashboard_view_data( $draft, $zone_id, $selected_ids, $selected, $preview_products, $preview_product_id, $step_statuses ) {
		$selected_carriers = self::get_selected_carriers( $draft );
		if ( empty( $selected_carriers ) ) {
			$selected_carriers = self::get_configured_connection_carriers();
		}

		$configured_count  = 0;

		foreach ( $selected_ids as $service_id ) {
			if ( self::is_service_configured( $service_id, $zone_id ) || self::is_service_configured_anywhere( $service_id ) ) {
				$configured_count++;
			}
		}

		return [
			'selected_carriers' => $selected_carriers,
			'configured_count' => $configured_count,
			'selected_count' => count( $selected_ids ),
			'zone_label' => self::get_zone_choices()[ $zone_id ] ?? __( 'Shipping zone not selected yet', 'wc-multishipping' ),
			'preview_product_id' => $preview_product_id,
			'preview_product_label' => self::get_preview_product_label( $preview_product_id, $preview_products ),
			'step_statuses' => $step_statuses,
			'services' => $selected,
		];
	}

	private static function authorize_post() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage WCMultiShipping settings.', 'wc-multishipping' ) );
		}

		check_admin_referer( 'wms_onboarding_action', 'wms_onboarding_nonce' );
	}

	private static function save_carrier_step() {
		$services = self::get_service_definitions();
		$selected_services = array_map( 'sanitize_key', (array) wp_unslash( $_POST['selected_services'] ?? [] ) );
		$selected_services = array_values( array_intersect( $selected_services, array_keys( $services ) ) );

		if ( empty( $selected_services ) ) {
			wms_enqueue_message( __( 'Select at least one service to continue.', 'wc-multishipping' ), 'error' );
			self::redirect_to_wizard( self::STEP_CARRIERS );
		}

		$selected_carriers = [];
		foreach ( $selected_services as $service_id ) {
			$selected_carriers[] = $services[ $service_id ]['carrier'];
		}

		$draft = self::get_draft();
		$draft['selected_services'] = $selected_services;
		$draft['selected_carriers'] = array_values( array_unique( $selected_carriers ) );
		self::save_draft( $draft );

		if ( in_array( 'chronopost', $draft['selected_carriers'], true ) && 'yes' !== get_option( 'wms_chronopost_enable', 'yes' ) ) {
			update_option( 'wms_chronopost_enable', 'yes' );
		}

		if ( in_array( 'mondial_relay', $draft['selected_carriers'], true ) && 'yes' !== get_option( 'wms_mondial_relay_enable', 'yes' ) ) {
			update_option( 'wms_mondial_relay_enable', 'yes' );
		}

		self::update_last_step( self::STEP_CONNECTION );
		wms_telemetry::track( 'plugin_onboarding_step_completed', [
			'step' => self::STEP_CARRIERS,
			'result' => 'success',
			'configured_carriers_count' => count( $draft['selected_carriers'] ),
		] );

		foreach ( $draft['selected_carriers'] as $carrier ) {
			wms_telemetry::track( 'carrier_config_saved', [
				'carrier' => $carrier,
				'result' => 'selected',
				'configured_carriers_count' => count( $draft['selected_carriers'] ),
			] );
		}
		wms_enqueue_message( __( 'Your onboarding scope has been saved.', 'wc-multishipping' ), 'success' );
		self::redirect_to_wizard( self::STEP_CONNECTION );
	}

	private static function save_connection_step() {
		$draft = self::get_draft();
		$selected_carriers = self::get_selected_carriers( $draft );

		$active_carrier = sanitize_key( wp_unslash( $_POST['connection_carrier'] ?? '' ) );
		if ( ! in_array( $active_carrier, $selected_carriers, true ) ) {
			$active_carrier = $selected_carriers[0] ?? '';
		}

		if ( 'chronopost' === $active_carrier ) {
			$connection_type = sanitize_key( wp_unslash( $_POST['wms_chronopost_connection_type'] ?? 'soap' ) );
			if ( ! in_array( $connection_type, [ 'soap', 'jwt' ], true ) ) {
				$connection_type = 'soap';
			}

			update_option( 'wms_chronopost_connection_type', $connection_type );

			if ( 'soap' === $connection_type ) {
				$account_number   = sanitize_text_field( wp_unslash( $_POST['wms_chronopost_account_number'] ?? '' ) );
				$account_name     = sanitize_text_field( wp_unslash( $_POST['wms_chronopost_account_name'] ?? '' ) );
				$account_password = sanitize_text_field( wp_unslash( $_POST['wms_chronopost_account_password'] ?? '' ) );
				$stored_password  = (string) get_option( 'wms_chronopost_account_password', '' );

				if ( '' === $account_password && '' !== trim( $stored_password ) ) {
					$account_password = $stored_password;
				}

				if ( '' === $account_number || '' === $account_password ) {
					wms_enqueue_message( __( 'Chronopost SOAP requires both an account number and a password.', 'wc-multishipping' ), 'error' );
					self::redirect_to_wizard( self::STEP_CONNECTION, [ 'carrier' => $active_carrier ] );
				}

				update_option( 'wms_chronopost_account_number', $account_number );
				update_option( 'wms_chronopost_account_name', $account_name );
				update_option( 'wms_chronopost_account_password', $account_password );
			} else {
				$auth_jwt = new chronopost_auth_jwt();
				if ( ! $auth_jwt->is_enrollment_complete() || $auth_jwt->is_refresh_token_expired() ) {
					wms_enqueue_message( __( 'Chronopost PRO is selected. Use the connect button to complete the link before continuing.', 'wc-multishipping' ), 'error' );
					self::redirect_to_wizard( self::STEP_CONNECTION, [ 'carrier' => $active_carrier ] );
				}
			}
		}

		if ( 'mondial_relay' === $active_carrier ) {
			$customer_code = sanitize_text_field( wp_unslash( $_POST['wms_mondial_relay_customer_code'] ?? '' ) );
			$private_key   = sanitize_text_field( wp_unslash( $_POST['wms_mondial_relay_private_key'] ?? '' ) );
			$brand_code    = sanitize_text_field( wp_unslash( $_POST['wms_mondial_relay_brand_code'] ?? '' ) );
			$stored_private_key = (string) get_option( 'wms_mondial_relay_private_key', '' );

			if ( '' === $private_key && '' !== trim( $stored_private_key ) ) {
				$private_key = $stored_private_key;
			}

			if ( '' === $customer_code || '' === $private_key ) {
				wms_enqueue_message( __( 'Mondial Relay requires a customer code and a private key.', 'wc-multishipping' ), 'error' );
				self::redirect_to_wizard( self::STEP_CONNECTION, [ 'carrier' => $active_carrier ] );
			}

			update_option( 'wms_mondial_relay_customer_code', $customer_code );
			update_option( 'wms_mondial_relay_private_key', $private_key );
			update_option( 'wms_mondial_relay_brand_code', $brand_code );
		}

		wms_telemetry::track( 'carrier_config_saved', [
			'carrier' => $active_carrier,
			'result' => 'success',
			'configured_carriers_count' => count( $selected_carriers ),
		] );

		$current_index = array_search( $active_carrier, $selected_carriers, true );
		if ( false !== $current_index && isset( $selected_carriers[ $current_index + 1 ] ) ) {
			wms_enqueue_message( __( 'This carrier connection has been saved.', 'wc-multishipping' ), 'success' );
			self::redirect_to_wizard( self::STEP_CONNECTION, [ 'carrier' => $selected_carriers[ $current_index + 1 ] ] );
		}

		self::update_last_step( self::STEP_ADDRESSES );
		wms_telemetry::track( 'plugin_onboarding_step_completed', [
			'step' => self::STEP_CONNECTION,
			'result' => 'success',
			'configured_carriers_count' => count( $selected_carriers ),
		] );
		wms_enqueue_message( __( 'Your carrier connection settings have been saved.', 'wc-multishipping' ), 'success' );
		self::redirect_to_wizard( self::STEP_ADDRESSES );
	}

	private static function save_address_step() {
		$draft = self::get_draft();
		$selected_carriers = self::get_selected_carriers( $draft );
		$sender_values = [];

		if ( ! empty( $selected_carriers ) ) {
			foreach ( self::get_sender_fields() as $field_id => $field ) {
				$value = wp_unslash( $_POST[ $field_id ] ?? '' );
				$sender_values[ $field_id ] = self::sanitize_field_value( $field, $value );
			}

			self::apply_sender_values( $sender_values, $selected_carriers );
		}

		if ( in_array( 'chronopost', $selected_carriers, true ) ) {
			foreach ( self::get_chronopost_customer_fields() as $field_id => $field ) {
				$value = wp_unslash( $_POST[ $field_id ] ?? '' );
				update_option( $field_id, self::sanitize_field_value( $field, $value ) );
			}
		}

		if ( isset( $_POST['wms_copy_sender_to_billing'] ) ) {
			self::copy_chronopost_sender_to_customer();
		}

		$errors = self::validate_address_completion( $selected_carriers );
		if ( ! empty( $errors ) ) {
			wms_enqueue_message( implode( ' ', $errors ), 'error' );
			self::redirect_to_wizard( self::STEP_ADDRESSES );
		}

		self::update_last_step( self::STEP_RATES );
		wms_telemetry::track( 'plugin_onboarding_step_completed', [
			'step' => self::STEP_ADDRESSES,
			'result' => 'success',
			'configured_carriers_count' => count( $selected_carriers ),
		] );
		wms_enqueue_message( __( 'Your sender and billing details have been saved.', 'wc-multishipping' ), 'success' );
		self::redirect_to_wizard( self::STEP_RATES );
	}

	private static function save_rates_step() {
		$draft = self::get_draft();
		$default_rate_services = self::get_default_rate_service_ids( $draft );
		$zone_id = self::ensure_testing_zone_id();

		if ( empty( $default_rate_services ) ) {
			wms_enqueue_message( __( 'No pickup method was found for the selected carriers. Restart the onboarding from the first step.', 'wc-multishipping' ), 'error' );
			self::redirect_to_wizard( self::STEP_CARRIERS );
		}

		foreach ( $default_rate_services as $service_id ) {
			$service = self::get_service_definitions()[ $service_id ] ?? null;

			if ( ! $service ) {
				continue;
			}

			self::apply_service_settings(
				$service_id,
				$zone_id,
				[
					'title' => $service['default_title'],
					'pricing_condition' => 'weight',
					'min' => 0,
					'max' => 100,
					'price' => 10,
					'free_shipping_condition' => '',
				]
			);
		}

		$draft['zone_id'] = $zone_id;
		$draft['selected_services'] = $default_rate_services;
		self::save_draft( $draft );
		self::update_last_step( self::STEP_PREVIEW );
		wms_telemetry::track( 'plugin_onboarding_step_completed', [
			'step' => self::STEP_RATES,
			'result' => 'success',
			'configured_carriers_count' => count( self::get_selected_carriers( $draft ) ),
		] );

		wms_enqueue_message(
			__( 'A default 10€ test rate has been applied to the France shipping zone for the selected pickup methods.', 'wc-multishipping' ),
			'success'
		);
		self::redirect_to_wizard( self::STEP_PREVIEW );
	}

	private static function apply_service_settings( $service_id, $zone_id, $raw_settings ) {
		$service = self::get_service_definitions()[ $service_id ] ?? null;
		if ( ! $service ) {
			return;
		}

		$instance = self::find_service_instance( $service_id, $zone_id );
		if ( ! $instance ) {
			$zone = self::get_zone_object( $zone_id );
			if ( ! $zone || ! method_exists( $zone, 'add_shipping_method' ) ) {
				return;
			}

			$instance_id = $zone->add_shipping_method( $service_id );
			$instance = \WC_Shipping_Zones::get_shipping_method( $instance_id );
		}

		if ( ! $instance ) {
			return;
		}

		$current_settings = get_option( 'woocommerce_' . $service_id . '_' . $instance->get_instance_id() . '_settings', [] );
		$existing_rate = ! empty( $current_settings['shipping_rates'][0] ) && is_array( $current_settings['shipping_rates'][0] ) ? $current_settings['shipping_rates'][0] : [];
		$min = isset( $raw_settings['min'] ) ? (float) str_replace( ',', '.', (string) $raw_settings['min'] ) : 0;
		$max = isset( $raw_settings['max'] ) ? (float) str_replace( ',', '.', (string) $raw_settings['max'] ) : 10;
		$price = isset( $raw_settings['price'] ) ? (float) str_replace( ',', '.', (string) $raw_settings['price'] ) : 10;

		if ( $max <= $min ) {
			$max = $min + 0.01;
		}

		$pricing_condition = isset( $raw_settings['pricing_condition'] ) && 'cart_amount' === $raw_settings['pricing_condition'] ? 'cart_amount' : 'weight';
		$free_shipping_condition = isset( $raw_settings['free_shipping_condition'] ) ? (float) str_replace( ',', '.', (string) $raw_settings['free_shipping_condition'] ) : 0;
		$title = sanitize_text_field( $raw_settings['title'] ?? $service['default_title'] );

		$next_settings = array_merge(
			$current_settings,
			[
				'title' => $title ?: $service['default_title'],
				'title_if_free' => $current_settings['title_if_free'] ?? __( 'Free Shipping', 'wc-multishipping' ),
				'pricing_condition' => $pricing_condition,
				'free_shipping' => 'no',
				'free_shipping_condition' => $free_shipping_condition > 0 ? $free_shipping_condition : '',
				'management_fees' => $current_settings['management_fees'] ?? 0,
				'packaging_weight' => $current_settings['packaging_weight'] ?? 0,
				'shipping_rates' => [
					[
						'min' => max( 0, $min ),
						'max' => $max,
						'price' => max( 0, $price ),
						'shipping_class' => $existing_rate['shipping_class'] ?? [ 'all' ],
					],
				],
			]
		);

		update_option( 'woocommerce_' . $service_id . '_' . $instance->get_instance_id() . '_settings', $next_settings );
	}

	private static function get_step_view_models( $current_step, $step_statuses ) {
		$steps = [];
		foreach ( self::get_steps() as $step_key => $step ) {
			$steps[] = [
				'key' => $step_key,
				'label' => $step['label'],
				'kicker' => $step['kicker'],
				'title' => $step['title'],
				'description' => $step['description'],
				'is_current' => $step_key === $current_step,
				'is_complete' => ! empty( $step_statuses[ $step_key ] ),
				'url' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=' . $step_key ),
			];
		}

		return $steps;
	}

	private static function get_step_statuses( $draft, $zone_id, $selected_ids ) {
		$selected_carriers = self::get_selected_carriers( $draft );
		$has_preview_product = ! empty( self::get_preview_product_choices() );
		$activation_complete = ! self::is_email_registration_mode() || (bool) get_option( 'wms_customer_installation_registered', false );
		$carriers_complete = ! empty( $selected_ids );
		$connection_complete = self::is_connection_step_complete( $selected_carriers );
		$addresses_complete = empty( self::validate_address_completion( $selected_carriers ) );
		$rates_complete = self::is_rates_step_complete( $selected_ids, $zone_id );

		return [
			self::STEP_ACTIVATION => $activation_complete,
			self::STEP_CARRIERS => $carriers_complete,
			self::STEP_CONNECTION => $connection_complete,
			self::STEP_ADDRESSES => $addresses_complete,
			self::STEP_RATES => $rates_complete,
			self::STEP_PREVIEW => $activation_complete && $carriers_complete && $connection_complete && $addresses_complete && $rates_complete && $has_preview_product,
		];
	}

	private static function is_email_registration_mode() {
		return ! method_exists( config_class::class, 'check_wms_api_key' );
	}

	private static function is_connection_step_complete( $selected_carriers ) {
		if ( in_array( 'chronopost', $selected_carriers, true ) ) {
			$connection_type = chronopost_connection_manager::get_default_connection_type();

			if ( 'jwt' === $connection_type ) {
				$auth_jwt = new chronopost_auth_jwt();
				if ( ! $auth_jwt->is_enrollment_complete() || $auth_jwt->is_refresh_token_expired() ) {
					return false;
				}
			} else {
				if ( '' === trim( (string) get_option( 'wms_chronopost_account_number', '' ) ) || '' === trim( (string) get_option( 'wms_chronopost_account_password', '' ) ) ) {
					return false;
				}
			}
		}

		if ( in_array( 'mondial_relay', $selected_carriers, true ) ) {
			if ( '' === trim( (string) get_option( 'wms_mondial_relay_customer_code', '' ) ) || '' === trim( (string) get_option( 'wms_mondial_relay_private_key', '' ) ) ) {
				return false;
			}
		}

		return true;
	}

	private static function get_configured_connection_carriers() {
		$carriers = [];
		$connection_type = chronopost_connection_manager::get_default_connection_type();

		if ( 'jwt' === $connection_type ) {
			$auth_jwt = new chronopost_auth_jwt();
			if ( $auth_jwt->is_enrollment_complete() && ! $auth_jwt->is_refresh_token_expired() ) {
				$carriers[] = 'chronopost';
			}
		} elseif ( '' !== trim( (string) get_option( 'wms_chronopost_account_number', '' ) ) && '' !== trim( (string) get_option( 'wms_chronopost_account_password', '' ) ) ) {
			$carriers[] = 'chronopost';
		}

		if ( '' !== trim( (string) get_option( 'wms_mondial_relay_customer_code', '' ) ) && '' !== trim( (string) get_option( 'wms_mondial_relay_private_key', '' ) ) ) {
			$carriers[] = 'mondial_relay';
		}

		return $carriers;
	}

	private static function is_rates_step_complete( $selected_ids, $zone_id ) {
		$draft = self::get_draft();
		$default_rate_services = self::get_default_rate_service_ids( $draft );

		if ( ! empty( $default_rate_services ) ) {
			$default_services_ready = true;
			foreach ( $default_rate_services as $service_id ) {
				if ( ! self::is_service_configured( $service_id, $zone_id ) ) {
					$default_services_ready = false;
					break;
				}
			}

			if ( $default_services_ready ) {
				return true;
			}
		}

		$selected_carriers = self::get_selected_carriers( $draft );
		if ( ! empty( $selected_carriers ) ) {
			return self::has_configured_rate_for_carriers( $selected_carriers );
		}

		return self::has_any_wms_configured_rate();
	}

	private static function is_service_configured( $service_id, $zone_id ) {
		$instance = self::find_service_instance( $service_id, $zone_id );
		if ( ! $instance ) {
			return false;
		}

		$settings = get_option( 'woocommerce_' . $service_id . '_' . $instance->get_instance_id() . '_settings', [] );
		return ! empty( $settings['shipping_rates'] );
	}

	private static function has_any_wms_configured_rate() {
		foreach ( [ 'chronopost', 'mondial_relay' ] as $carrier ) {
			foreach ( self::get_carrier_shipping_method_ids( $carrier ) as $service_id ) {
				if ( self::is_service_configured_anywhere( $service_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function has_configured_rate_for_carriers( $carriers ) {
		foreach ( $carriers as $carrier ) {
			$carrier_has_rate = false;

			foreach ( self::get_carrier_shipping_method_ids( $carrier ) as $service_id ) {
				if ( self::is_service_configured_anywhere( $service_id ) ) {
					$carrier_has_rate = true;
					break;
				}
			}

			if ( ! $carrier_has_rate ) {
				return false;
			}
		}

		return true;
	}

	private static function is_service_configured_anywhere( $service_id ) {
		foreach ( array_map( 'absint', array_keys( self::get_zone_choices() ) ) as $zone_id ) {
			if ( self::is_service_configured( $service_id, $zone_id ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_carrier_shipping_method_ids( $carrier ) {
		static $method_ids = [];

		$carrier = sanitize_key( $carrier );
		if ( isset( $method_ids[ $carrier ] ) ) {
			return $method_ids[ $carrier ];
		}

		$loader = null;
		if ( 'chronopost' === $carrier ) {
			$loader = [ chronopost_shipping_methods::class, 'load_shipping_methods' ];
		} elseif ( 'mondial_relay' === $carrier ) {
			$loader = [ mondial_relay_shipping_methods::class, 'load_shipping_methods' ];
		}

		$ids = [];
		if ( is_callable( $loader ) ) {
			try {
				$shipping_methods = call_user_func( $loader );
				if ( is_array( $shipping_methods ) ) {
					foreach ( $shipping_methods as $shipping_method ) {
						$id = '';
						if ( is_object( $shipping_method ) ) {
							$id = isset( $shipping_method->id ) ? (string) $shipping_method->id : '';
							$class_name = get_class( $shipping_method );
							if ( '' === $id && defined( $class_name . '::ID' ) ) {
								$id = (string) constant( $class_name . '::ID' );
							}
						}

						if ( '' !== $id ) {
							$ids[] = sanitize_key( $id );
						}
					}
				}
			} catch ( \Throwable $exception ) {
				$ids = [];
			}
		}

		if ( empty( $ids ) ) {
			foreach ( self::get_service_definitions() as $service_id => $service ) {
				if ( $carrier === $service['carrier'] ) {
					$ids[] = $service_id;
				}
			}
		}

		$method_ids[ $carrier ] = array_values( array_unique( array_filter( $ids ) ) );

		return $method_ids[ $carrier ];
	}

	private static function build_default_draft() {
		$selected_services = [];
		$zone_id = self::get_recommended_zone_id();
		$services = self::get_service_definitions();

		foreach ( array_keys( $services ) as $service_id ) {
			if ( self::find_service_instance( $service_id, $zone_id ) ) {
				$selected_services[] = $service_id;
			}
		}

		if ( empty( $selected_services ) ) {
			$selected_services = self::get_detected_default_services();
		}

		if ( empty( $selected_services ) ) {
			$selected_services = [ 'chronopost_relais', 'mondial_relay_point_relais' ];
		}

		$selected_carriers = [];
		foreach ( $selected_services as $service_id ) {
			$selected_carriers[] = $services[ $service_id ]['carrier'];
		}

		return [
			'wizard_version' => self::WIZARD_VERSION,
			'selected_carriers' => array_values( array_unique( $selected_carriers ) ),
			'selected_services' => $selected_services,
			'zone_id' => $zone_id,
			'preview_product_id' => self::get_default_preview_product_id(),
			'checkout_preview_product_id' => 0,
			'checkout_preview_opened_at' => '',
		];
	}

	private static function has_existing_setup() {
		$auth_jwt = new chronopost_auth_jwt();
		$has_chronopost_pro = $auth_jwt->is_enrollment_complete() && ! $auth_jwt->is_refresh_token_expired();
		$has_wms_options = self::has_any_option_value(
			[
				'wms_chronopost_account_number',
				'wms_chronopost_account_password',
				'wms_chronopost_account_name',
				'wms_chronopost_shipper_name',
				'wms_chronopost_shipper_name_2',
				'wms_chronopost_shipper_address_1',
				'wms_chronopost_shipper_zip_code',
				'wms_chronopost_shipper_city',
				'wms_chronopost_customer_name',
				'wms_chronopost_customer_address_1',
				'wms_mondial_relay_customer_code',
				'wms_mondial_relay_private_key',
				'wms_mondial_relay_shipper_name',
				'wms_mondial_relay_shipper_name_2',
				'wms_mondial_relay_shipper_address_1',
				'wms_mondial_relay_shipper_zip_code',
				'wms_mondial_relay_shipper_city',
			]
		);

		return $has_chronopost_pro || $has_wms_options || self::has_wms_shipping_instances();
	}

	private static function is_fresh_install_for_onboarding() {
		$is_fresh = ! self::has_existing_setup();
		self::record_install_detection( $is_fresh );

		return $is_fresh;
	}

	private static function record_install_detection( $is_fresh ) {
		$state = self::get_state();
		$state['wizard_version'] = self::WIZARD_VERSION;
		$state['auto_eligible'] = $is_fresh ? 'yes' : 'no';

		if ( $is_fresh && empty( $state['fresh_install_detected_at'] ) ) {
			$state['fresh_install_detected_at'] = current_time( 'mysql' );
		}

		if ( ! $is_fresh && empty( $state['existing_setup_detected_at'] ) ) {
			$state['existing_setup_detected_at'] = current_time( 'mysql' );
		}

		update_option( self::OPTION_STATE, $state, false );
	}

	private static function has_any_option_value( $option_names ) {
		foreach ( $option_names as $option_name ) {
			$value = get_option( $option_name, '' );

			if ( is_array( $value ) ) {
				if ( ! empty( $value ) ) {
					return true;
				}
				continue;
			}

			if ( '' !== trim( (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	private static function has_wms_shipping_instances() {
		foreach ( [ 'chronopost', 'mondial_relay' ] as $carrier ) {
			if ( self::has_carrier_shipping_instances( $carrier ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_detected_default_services() {
		$selected_services = [];

		if ( self::has_carrier_setup( 'chronopost' ) ) {
			$selected_services[] = 'chronopost_relais';
		}

		if ( self::has_carrier_setup( 'mondial_relay' ) ) {
			$selected_services[] = 'mondial_relay_point_relais';
		}

		return $selected_services;
	}

	private static function has_carrier_setup( $carrier ) {
		if ( 'chronopost' === $carrier ) {
			return self::has_carrier_shipping_instances( $carrier ) || self::has_any_option_value(
				[
					'wms_chronopost_account_number',
					'wms_chronopost_account_password',
					'wms_chronopost_account_name',
					'wms_chronopost_shipper_name',
					'wms_chronopost_shipper_address_1',
					'wms_chronopost_customer_name',
					'wms_chronopost_customer_address_1',
				]
			);
		}

		if ( 'mondial_relay' === $carrier ) {
			return self::has_carrier_shipping_instances( $carrier ) || self::has_any_option_value(
				[
					'wms_mondial_relay_customer_code',
					'wms_mondial_relay_private_key',
					'wms_mondial_relay_shipper_name',
					'wms_mondial_relay_shipper_address_1',
				]
			);
		}

		return false;
	}

	private static function has_carrier_shipping_instances( $carrier ) {
		$zone_ids = array_map( 'absint', array_keys( self::get_zone_choices() ) );

		foreach ( self::get_carrier_shipping_method_ids( $carrier ) as $service_id ) {
			foreach ( $zone_ids as $zone_id ) {
				if ( self::find_service_instance( $service_id, $zone_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function get_selected_services( $draft ) {
		$services = array_map( 'sanitize_key', (array) $draft['selected_services'] );

		return array_values( array_intersect( $services, array_keys( self::get_service_definitions() ) ) );
	}

	private static function get_selected_carriers( $draft ) {
		$carriers = array_map( 'sanitize_key', (array) $draft['selected_carriers'] );
		$carriers = array_values( array_intersect( $carriers, [ 'chronopost', 'mondial_relay' ] ) );

		if ( empty( $carriers ) ) {
			foreach ( self::get_selected_services( $draft ) as $service_id ) {
				$service = self::get_service_definitions()[ $service_id ] ?? null;
				if ( $service ) {
					$carriers[] = $service['carrier'];
				}
			}
			$carriers = array_values( array_unique( $carriers ) );
		}

		return $carriers;
	}

	private static function get_default_rate_service_ids( $draft ) {
		$service_ids = [];
		$selected_carriers = self::get_selected_carriers( $draft );
		$services = self::get_service_definitions();

		if ( in_array( 'chronopost', $selected_carriers, true ) && isset( $services['chronopost_relais'] ) ) {
			$service_ids[] = 'chronopost_relais';
		}

		if ( in_array( 'mondial_relay', $selected_carriers, true ) && isset( $services['mondial_relay_point_relais'] ) ) {
			$service_ids[] = 'mondial_relay_point_relais';
		}

		return $service_ids;
	}

	private static function sanitize_zone_id( $zone_id, $zone_choices ) {
		$zone_id = absint( $zone_id );
		if ( isset( $zone_choices[ $zone_id ] ) ) {
			return $zone_id;
		}

		return self::get_recommended_zone_id();
	}

	private static function sanitize_preview_product_id( $preview_product_id, $preview_products ) {
		$preview_product_id = absint( $preview_product_id );
		foreach ( $preview_products as $product ) {
			if ( (int) $product['id'] === $preview_product_id ) {
				return $preview_product_id;
			}
		}

		return self::get_default_preview_product_id();
	}

	private static function get_default_preview_product_id() {
		$products = self::get_preview_product_choices();

		return ! empty( $products[0]['id'] ) ? (int) $products[0]['id'] : 0;
	}

	private static function get_preview_product_choices() {
		$products = wc_get_products(
			[
				'status' => 'publish',
				'type' => [ 'simple', 'variable' ],
				'limit' => 20,
				'return' => 'objects',
			]
		);

		$candidates = [];
		$fallback = [];

		foreach ( $products as $product ) {
			if ( ! $product || ! $product->exists() ) {
				continue;
			}

			$entries = [];

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					$entry = self::get_preview_product_choice_entry( $variation, $product );

					if ( $entry ) {
						$entries[] = $entry;
					}
				}
			} else {
				$entry = self::get_preview_product_choice_entry( $product );

				if ( $entry ) {
					$entries[] = $entry;
				}
			}

			foreach ( $entries as $entry ) {
				if ( $entry['weight'] > 0 ) {
					$candidates[] = $entry;
				} else {
					$fallback[] = $entry;
				}
			}
		}

		if ( ! empty( $candidates ) ) {
			usort(
				$candidates,
				function ( $left, $right ) {
					if ( (float) $left['weight'] === (float) $right['weight'] ) {
						return (int) $left['id'] <=> (int) $right['id'];
					}

					return (float) $left['weight'] <=> (float) $right['weight'];
				}
			);
		}

		return array_slice( ! empty( $candidates ) ? $candidates : $fallback, 0, 12 );
	}

	private static function get_preview_product_choice_entry( $product, $parent_product = null ) {
		if (
			! $product
			|| ! $product->exists()
			|| $product->is_virtual()
			|| ! $product->needs_shipping()
			|| ! $product->is_purchasable()
			|| ! $product->is_type( [ 'simple', 'variation' ] )
		) {
			return null;
		}

		$weight = self::get_preview_product_weight( $product, $parent_product );

		return [
			'id' => $product->get_id(),
			'label' => sprintf(
				'%1$s (#%2$d)%3$s',
				$product->get_name(),
				$product->get_id(),
				$weight > 0 ? sprintf( ' — %s %s', wc_format_localized_decimal( $weight ), get_option( 'woocommerce_weight_unit', 'kg' ) ) : ''
			),
			'weight' => $weight,
		];
	}

	private static function get_preview_product_weight( $product, $parent_product = null ) {
		$weight = (float) $product->get_weight();

		if ( $weight <= 0 && $parent_product && $parent_product->exists() ) {
			$weight = (float) $parent_product->get_weight();
		}

		return $weight;
	}

	private static function get_preview_product_label( $preview_product_id, $preview_products ) {
		foreach ( $preview_products as $product ) {
			if ( (int) $product['id'] === (int) $preview_product_id ) {
				return $product['label'];
			}
		}

		return __( 'No preview product selected yet', 'wc-multishipping' );
	}

	private static function get_recommended_zone_id() {
		$france_zone_id = self::find_france_zone_id();
		if ( $france_zone_id > 0 ) {
			return $france_zone_id;
		}

		$zones = self::get_zone_choices();
		unset( $zones[0] );

		if ( ! empty( $zones ) ) {
			$keys = array_keys( $zones );
			return (int) reset( $keys );
		}

		return 0;
	}

	private static function find_france_zone_id() {
		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( (array) ( $zone['zone_locations'] ?? [] ) as $location ) {
				if ( isset( $location->type, $location->code ) && 'country' === $location->type && 'FR' === strtoupper( (string) $location->code ) ) {
					return (int) $zone['id'];
				}
			}
		}

		return 0;
	}

	private static function ensure_testing_zone_id() {
		$france_zone_id = self::find_france_zone_id();
		if ( $france_zone_id > 0 ) {
			return $france_zone_id;
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( __( 'France', 'wc-multishipping' ) );
		$zone_id = (int) $zone->save();

		if ( $zone_id <= 0 ) {
			return self::get_recommended_zone_id();
		}

		$zone = new \WC_Shipping_Zone( $zone_id );
		$zone->add_location( 'FR', 'country' );
		$zone->save();

		return $zone_id;
	}

	private static function get_service_view( $service_id, $zone_id ) {
		$service = self::get_service_definitions()[ $service_id ];
		$instance = self::find_service_instance( $service_id, $zone_id );
		$settings = $instance ? get_option( 'woocommerce_' . $service_id . '_' . $instance->get_instance_id() . '_settings', [] ) : [];
		$rate = ! empty( $settings['shipping_rates'][0] ) && is_array( $settings['shipping_rates'][0] ) ? $settings['shipping_rates'][0] : [];

		return [
			'service_id' => $service_id,
			'carrier' => $service['carrier'],
			'title' => $service['title'],
			'description' => $service['description'],
			'field_title' => $settings['title'] ?? $service['default_title'],
			'pricing_condition' => $settings['pricing_condition'] ?? 'weight',
			'free_shipping_condition' => isset( $settings['free_shipping_condition'] ) ? (string) $settings['free_shipping_condition'] : '',
			'min' => isset( $rate['min'] ) ? (string) $rate['min'] : '0',
			'max' => isset( $rate['max'] ) ? (string) $rate['max'] : '10',
			'price' => isset( $rate['price'] ) ? (string) $rate['price'] : '10',
			'instance_id' => $instance ? $instance->get_instance_id() : 0,
			'instance_url' => $instance ? admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $instance->get_instance_id() ) : '',
			'is_configured' => ! empty( $settings['shipping_rates'] ),
		];
	}

	private static function get_zone_object( $zone_id ) {
		return new \WC_Shipping_Zone( $zone_id );
	}

	private static function find_service_instance( $service_id, $zone_id ) {
		$zone = self::get_zone_object( $zone_id );
		if ( ! $zone || ! method_exists( $zone, 'get_shipping_methods' ) ) {
			return null;
		}

		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( $method && $method->id === $service_id ) {
				return $method;
			}
		}

		return null;
	}

	private static function get_preview_address_for_zone( $zone_id ) {
		$defaults = self::get_store_defaults();
		$address = [
			'country' => $defaults['country'] ?: 'FR',
			'state' => $defaults['state'] ?: '',
			'postcode' => $defaults['zip_code'] ?: '75001',
			'city' => $defaults['city'] ?: 'Paris',
			'address_1' => $defaults['address_1'] ?: '1 Rue de Rivoli',
			'address_2' => $defaults['address_2'] ?: '',
		];

		$zone = self::get_zone_object( $zone_id );
		if ( ! $zone || ! method_exists( $zone, 'get_zone_locations' ) ) {
			return $address;
		}

		foreach ( $zone->get_zone_locations() as $location ) {
			if ( 'country' === $location->type ) {
				$address['country'] = $location->code;
				break;
			}

			if ( 'state' === $location->type && false !== strpos( $location->code, ':' ) ) {
				list( $country, $state ) = explode( ':', $location->code, 2 );
				$address['country'] = $country;
				$address['state'] = $state;
				break;
			}

			if ( 'postcode' === $location->type ) {
				$postcode = preg_replace( '/[^0-9]/', '', (string) $location->code );
				if ( $postcode ) {
					$address['postcode'] = substr( $postcode, 0, 5 );
				}
			}
		}

		return $address;
	}

	private static function clear_preview_pickup_state() {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set( chronopost_pickup_widget::PICKUP_LOCATION_SESSION_VAR_NAME, null );
		WC()->session->set( mondial_relay_pickup_widget::PICKUP_LOCATION_SESSION_VAR_NAME, null );
	}

	private static function prime_preview_shipping_method( $draft, $zone_id ) {
		if ( ! WC()->session || ! WC()->cart || ! WC()->shipping() ) {
			return;
		}

		$packages = WC()->shipping()->get_packages();
		if ( empty( $packages ) && method_exists( WC()->cart, 'get_shipping_packages' ) ) {
			$packages = WC()->cart->get_shipping_packages();
			WC()->shipping()->calculate_shipping( $packages );
			$packages = WC()->shipping()->get_packages();
		}

		if ( empty( $packages[0]['rates'] ) || ! is_array( $packages[0]['rates'] ) ) {
			return;
		}

		$preferred_rate_id = self::find_preferred_preview_rate_id( $draft, $zone_id, $packages[0]['rates'] );
		if ( empty( $preferred_rate_id ) ) {
			return;
		}

		WC()->session->set( 'chosen_shipping_methods', [ $preferred_rate_id ] );
		WC()->cart->calculate_totals();
	}

	private static function cart_has_shipping_rates() {
		if ( ! WC()->shipping() ) {
			return false;
		}

		$packages = WC()->shipping()->get_packages();
		if ( ! is_array( $packages ) ) {
			return false;
		}

		foreach ( $packages as $package ) {
			if ( ! empty( $package['rates'] ) && is_array( $package['rates'] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function find_preferred_preview_rate_id( $draft, $zone_id, $rates ) {
		$selected_services = self::get_selected_services( $draft );
		if ( empty( $selected_services ) ) {
			return '';
		}

		$relay_services = array_values(
			array_intersect(
				$selected_services,
				[
					'chronopost_relais',
					'mondial_relay_point_relais',
					'mondial_relay_lockers',
				]
			)
		);

		$ordered_services = array_values( array_unique( array_merge( $relay_services, $selected_services ) ) );

		foreach ( $ordered_services as $service_id ) {
			$instance = self::find_service_instance( $service_id, $zone_id );
			if ( ! $instance ) {
				continue;
			}

			$rate_id = $service_id . ':' . $instance->get_instance_id();
			if ( isset( $rates[ $rate_id ] ) ) {
				return $rate_id;
			}
		}

		return '';
	}

	private static function update_last_step( $step ) {
		$state = self::get_state();
		$state['wizard_version'] = self::WIZARD_VERSION;
		$state['last_step'] = $step;
		update_option( self::OPTION_STATE, $state, false );
	}

	private static function mark_presented( $step ) {
		$state = self::get_state();

		if ( empty( $state['presented_at'] ) || $state['wizard_version'] !== self::WIZARD_VERSION ) {
			$state['presented_at'] = current_time( 'mysql' );
		}

		$state['wizard_version'] = self::WIZARD_VERSION;
		$state['last_step'] = $step;

		update_option( self::OPTION_STATE, $state, false );
		wms_telemetry::track_once( 'plugin_onboarding_started', self::WIZARD_VERSION, [
			'step' => $step,
			'result' => 'started',
		] );
		wms_telemetry::track_once( 'plugin_onboarding_step_viewed', self::WIZARD_VERSION . '_' . $step, [
			'step' => $step,
			'result' => 'viewed',
		] );
	}

	private static function redirect_to_wizard( $step, $args = [] ) {
		$url = admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=' . $step );
		if ( ! empty( $args ) ) {
			$url = add_query_arg( array_map( 'sanitize_key', $args ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_to_dashboard() {
		wp_safe_redirect( admin_url( 'admin.php?page=wc-multishipping&view=dashboard' ) );
		exit;
	}

	private static function sanitize_field_value( $field, $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
			return sanitize_email( $value );
		}

		if ( isset( $field['type'] ) && 'select' === $field['type'] ) {
			$value = sanitize_text_field( $value );
			return isset( $field['options'][ $value ] ) ? $value : ( $field['default'] ?? '' );
		}

		return sanitize_text_field( $value );
	}

	private static function validate_address_completion( $selected_carriers ) {
		$errors = [];

		if ( in_array( 'chronopost', $selected_carriers, true ) ) {
			foreach ( self::get_required_field_ids( self::get_chronopost_shipper_fields() ) as $field_id ) {
				if ( '' === trim( (string) get_option( $field_id, '' ) ) ) {
					$errors[] = __( 'Chronopost sender details are still incomplete.', 'wc-multishipping' );
					break;
				}
			}

			foreach ( self::get_required_field_ids( self::get_chronopost_customer_fields() ) as $field_id ) {
				if ( '' === trim( (string) get_option( $field_id, '' ) ) ) {
					$errors[] = __( 'Chronopost billing details are still incomplete.', 'wc-multishipping' );
					break;
				}
			}
		}

		if ( in_array( 'mondial_relay', $selected_carriers, true ) ) {
			foreach ( self::get_required_field_ids( self::get_mondial_relay_shipper_fields() ) as $field_id ) {
				if ( '' === trim( (string) get_option( $field_id, '' ) ) ) {
					$errors[] = __( 'Mondial Relay sender details are still incomplete.', 'wc-multishipping' );
					break;
				}
			}
		}

		return array_values( array_unique( $errors ) );
	}

	private static function get_required_field_ids( $fields ) {
		$ids = [];
		foreach ( $fields as $field_id => $field ) {
			if ( ! empty( $field['required'] ) ) {
				$ids[] = $field_id;
			}
		}

		return $ids;
	}

	private static function apply_sender_values( $values, $selected_carriers ) {
		$mapping = [
			'wms_chronopost_shipper' => [
				'carrier' => 'chronopost',
				'civility' => 'E',
			],
			'wms_mondial_relay_shipper' => [
				'carrier' => 'mondial_relay',
				'civility' => 'MR',
			],
		];

		foreach ( $mapping as $prefix => $config ) {
			if ( ! in_array( $config['carrier'], $selected_carriers, true ) ) {
				continue;
			}

			update_option( $prefix . '_civility', $config['civility'] );
			update_option( $prefix . '_name', $values['wms_sender_name'] ?? '' );
			update_option( $prefix . '_name_2', $values['wms_sender_name_2'] ?? '' );
			update_option( $prefix . '_address_1', $values['wms_sender_address_1'] ?? '' );
			update_option( $prefix . '_address_2', $values['wms_sender_address_2'] ?? '' );
			update_option( $prefix . '_zip_code', $values['wms_sender_zip_code'] ?? '' );
			update_option( $prefix . '_city', $values['wms_sender_city'] ?? '' );
			update_option( $prefix . '_country', $values['wms_sender_country'] ?? '' );
			update_option( $prefix . '_email', $values['wms_sender_email'] ?? '' );
			update_option( $prefix . '_phone', $values['wms_sender_phone'] ?? '' );
			update_option( $prefix . '_mobile_phone', $values['wms_sender_mobile_phone'] ?? '' );

			if ( 'chronopost' === $config['carrier'] ) {
				update_option( $prefix . '_contact_name', $values['wms_sender_contact_name'] ?? '' );
			}
		}

		update_option( 'woocommerce_store_address', $values['wms_sender_address_1'] ?? '' );
		update_option( 'woocommerce_store_address_2', $values['wms_sender_address_2'] ?? '' );
		update_option( 'woocommerce_store_city', $values['wms_sender_city'] ?? '' );
		update_option( 'woocommerce_store_postcode', $values['wms_sender_zip_code'] ?? '' );
		$base_state = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_state() : '';
		$country = $values['wms_sender_country'] ?? '';
		update_option( 'woocommerce_default_country', $country . ( $base_state ? ':' . $base_state : '' ) );

		if ( ! empty( $values['wms_sender_phone'] ) ) {
			update_option( 'woocommerce_store_phone', $values['wms_sender_phone'] );
		}
	}

	private static function copy_chronopost_sender_to_customer() {
		$mapping = [
			'wms_chronopost_customer_civility' => 'wms_chronopost_shipper_civility',
			'wms_chronopost_customer_name' => 'wms_chronopost_shipper_name',
			'wms_chronopost_customer_name_2' => 'wms_chronopost_shipper_name_2',
			'wms_chronopost_customer_address_1' => 'wms_chronopost_shipper_address_1',
			'wms_chronopost_customer_address_2' => 'wms_chronopost_shipper_address_2',
			'wms_chronopost_customer_zip_code' => 'wms_chronopost_shipper_zip_code',
			'wms_chronopost_customer_city' => 'wms_chronopost_shipper_city',
			'wms_chronopost_customer_country' => 'wms_chronopost_shipper_country',
			'wms_chronopost_customer_contact_name' => 'wms_chronopost_shipper_contact_name',
			'wms_chronopost_customer_email' => 'wms_chronopost_shipper_email',
			'wms_chronopost_customer_phone' => 'wms_chronopost_shipper_phone',
			'wms_chronopost_customer_mobile_phone' => 'wms_chronopost_shipper_mobile_phone',
		];

		foreach ( $mapping as $target => $source ) {
			update_option( $target, get_option( $source, '' ) );
		}
	}

	private static function get_sender_fields() {
		return [
			'wms_sender_name' => [ 'label' => __( 'First name', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_name_2' => [ 'label' => __( 'Last name', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_contact_name' => [ 'label' => __( 'Contact name', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_address_1' => [ 'label' => __( 'Address', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_address_2' => [ 'label' => __( 'Address 2', 'wc-multishipping' ) ],
			'wms_sender_zip_code' => [ 'label' => __( 'Zip code', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_city' => [ 'label' => __( 'City', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_country' => [
				'label' => __( 'Country', 'wc-multishipping' ),
				'type' => 'select',
				'default' => wc_get_base_location()['country'] ?? 'FR',
				'options' => self::get_country_options(),
				'required' => true,
			],
			'wms_sender_email' => [ 'label' => __( 'Email', 'wc-multishipping' ), 'type' => 'email', 'required' => true ],
			'wms_sender_phone' => [ 'label' => __( 'Phone', 'wc-multishipping' ), 'required' => true ],
			'wms_sender_mobile_phone' => [ 'label' => __( 'Mobile phone', 'wc-multishipping' ) ],
		];
	}

	private static function get_chronopost_shipper_fields() {
		return [
			'wms_chronopost_shipper_civility' => [
				'label' => __( 'Civility', 'wc-multishipping' ),
				'type' => 'select',
				'default' => 'E',
				'options' => [ 'E' => 'Mrs', 'M' => 'Mr', 'L' => 'Ms' ],
				'required' => true,
			],
			'wms_chronopost_shipper_name' => [ 'label' => __( 'First name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_name_2' => [ 'label' => __( 'Last name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_contact_name' => [ 'label' => __( 'Contact name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_address_1' => [ 'label' => __( 'Address', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_address_2' => [ 'label' => __( 'Address 2', 'wc-multishipping' ) ],
			'wms_chronopost_shipper_zip_code' => [ 'label' => __( 'Zip code', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_city' => [ 'label' => __( 'City', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_country' => [
				'label' => __( 'Country', 'wc-multishipping' ),
				'type' => 'select',
				'default' => wc_get_base_location()['country'] ?? 'FR',
				'options' => self::get_country_options(),
				'required' => true,
			],
			'wms_chronopost_shipper_email' => [ 'label' => __( 'Email', 'wc-multishipping' ), 'type' => 'email', 'required' => true ],
			'wms_chronopost_shipper_phone' => [ 'label' => __( 'Phone', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_shipper_mobile_phone' => [ 'label' => __( 'Mobile phone', 'wc-multishipping' ) ],
		];
	}

	private static function get_chronopost_customer_fields() {
		return [
			'wms_chronopost_customer_civility' => [
				'label' => __( 'Civility', 'wc-multishipping' ),
				'type' => 'select',
				'default' => 'E',
				'options' => [ 'E' => 'Mrs', 'M' => 'Mr', 'L' => 'Ms' ],
				'required' => true,
			],
			'wms_chronopost_customer_name' => [ 'label' => __( 'First name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_name_2' => [ 'label' => __( 'Last name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_contact_name' => [ 'label' => __( 'Contact name', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_address_1' => [ 'label' => __( 'Address', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_address_2' => [ 'label' => __( 'Address 2', 'wc-multishipping' ) ],
			'wms_chronopost_customer_zip_code' => [ 'label' => __( 'Zip code', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_city' => [ 'label' => __( 'City', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_country' => [
				'label' => __( 'Country', 'wc-multishipping' ),
				'type' => 'select',
				'default' => wc_get_base_location()['country'] ?? 'FR',
				'options' => self::get_country_options(),
				'required' => true,
			],
			'wms_chronopost_customer_email' => [ 'label' => __( 'Email', 'wc-multishipping' ), 'type' => 'email', 'required' => true ],
			'wms_chronopost_customer_phone' => [ 'label' => __( 'Phone', 'wc-multishipping' ), 'required' => true ],
			'wms_chronopost_customer_mobile_phone' => [ 'label' => __( 'Mobile phone', 'wc-multishipping' ) ],
		];
	}

	private static function get_mondial_relay_shipper_fields() {
		return [
			'wms_mondial_relay_shipper_civility' => [
				'label' => __( 'Civility', 'wc-multishipping' ),
				'type' => 'select',
				'default' => 'MR',
				'options' => [ '' => '', 'MLLE' => 'MLLE', 'MR' => 'MR', 'MME' => 'MME' ],
				'required' => true,
			],
			'wms_mondial_relay_shipper_name' => [ 'label' => __( 'First name', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_name_2' => [ 'label' => __( 'Last name', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_address_1' => [ 'label' => __( 'Address', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_address_2' => [ 'label' => __( 'Address 2', 'wc-multishipping' ) ],
			'wms_mondial_relay_shipper_zip_code' => [ 'label' => __( 'Zip code', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_city' => [ 'label' => __( 'City', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_country' => [
				'label' => __( 'Country', 'wc-multishipping' ),
				'type' => 'select',
				'default' => wc_get_base_location()['country'] ?? 'FR',
				'options' => self::get_country_options(),
				'required' => true,
			],
			'wms_mondial_relay_shipper_email' => [ 'label' => __( 'Email', 'wc-multishipping' ), 'type' => 'email', 'required' => true ],
			'wms_mondial_relay_shipper_phone' => [ 'label' => __( 'Phone', 'wc-multishipping' ), 'required' => true ],
			'wms_mondial_relay_shipper_mobile_phone' => [ 'label' => __( 'Mobile phone', 'wc-multishipping' ) ],
		];
	}

	private static function get_country_options() {
		$countries = function_exists( 'WC' ) && WC() && WC()->countries ? WC()->countries->get_countries() : [];

		if ( empty( $countries ) ) {
			$countries = ( new \WC_Countries() )->get_countries();
		}

		return $countries;
	}
}
