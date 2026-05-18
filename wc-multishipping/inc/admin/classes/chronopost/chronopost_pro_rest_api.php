<?php

namespace WCMultiShipping\inc\admin\classes\chronopost;

use DateTime;
use InvalidArgumentException;
use JsonException;
use ReflectionClass;
use RuntimeException;
use WCMultiShipping\inc\admin\classes\chronopost\auth\chronopost_auth_jwt;

class chronopost_pro_rest_api {

	const NAMESPACE = 'chronopost/v1';
	const API_VERSION = '1.1.0';
	const DRY_RUN_MODE = true;

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/renew', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_renew' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/revoke', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_revoke' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/orders', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_orders' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_order' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( self::NAMESPACE, '/addresses', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_address' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_address' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( self::NAMESPACE, '/states', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_states' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/relay-points', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_relay_points' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/shipping-method-settings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_shipping_method_settings' ],
			'permission_callback' => '__return_true',
		] );
	}


	private function check_restricted_access( $request ) {
		$token = $this->extract_bearer_token( $request );

		if ( empty( $token ) ) {
			return $this->render_exception_response(
				new InvalidArgumentException( 'Missing Bearer token' ),
				401
			);
		}

		$auth = new chronopost_auth_jwt();
		if ( ! $auth->has_keys() ) {
			$auth->generate_keys();
		}

		try {
			$key = new \Firebase\JWT\Key( $auth->get_public_key(), 'EdDSA' );
			\Firebase\JWT\JWT::decode( $token, $key );

			return true;
		} catch ( \Exception $exception ) {
			return $this->render_exception_response( $exception, 401 );
		}
	}

	private function dispatch_enveloped_request( $request, callable $callback, $restricted = false ) {
		$license_access = $this->check_pro_license_access( $request );
		if ( $license_access instanceof \WP_REST_Response ) {
			return $license_access;
		}

		if ( $restricted ) {
			$access = $this->check_restricted_access( $request );
			if ( $access instanceof \WP_REST_Response ) {
				return $access;
			}
		}

		try {
			return new \WP_REST_Response( [
				'success' => true,
				'result'  => $callback(),
				'version' => self::API_VERSION,
			], 200 );
		} catch ( \Exception $exception ) {
			$status = $exception instanceof \Firebase\JWT\ExpiredException ? 401 : 400;

			return $this->render_exception_response( $exception, $status );
		}
	}

	private function render_exception_response( \Exception $exception, $status = 400 ) {
		return new \WP_REST_Response( [
			'success' => false,
			'result'  => false,
			'error'   => [
				'code'    => $exception->getCode(),
				'type'    => ( new ReflectionClass( $exception ) )->getShortName(),
				'message' => $exception->getMessage(),
			],
			'version' => self::API_VERSION,
		], $status );
	}

	private function check_pro_license_access( $request ) {
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( empty( $route ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$route = (string) $_SERVER['REQUEST_URI'];
		}

		if (
			false !== strpos( $route, '/chronopost/v1/relay-points' ) ||
			false !== strpos( $route, '/chronopost/v1/shipping-method-settings' ) ||
			false !== strpos( $route, '/chronopost/v1/renew' ) ||
			false !== strpos( $route, '/chronopost/v1/revoke' )
		) {
			return true;
		}

		if ( $this->has_valid_pro_license() ) {
			return true;
		}

		wms_logger(
			sprintf(
				'Chronopost PRO: blocked REST access because no valid WcMultiShipping PRO license is active for route %s',
				$route
			)
		);

		return $this->render_exception_response(
			new InvalidArgumentException(
				$this->get_pro_license_error_message( $route ),
				400
			),
			400
		);
	}

	private function get_pro_license_error_message( $route ) {
		if ( false !== strpos( $route, '/chronopost/v1/orders' ) ) {
			return __( 'A valid WcMultiShipping Pro license is required to import WooCommerce orders from Chronopost PRO.', 'wc-multishipping' );
		}

		if ( false !== strpos( $route, '/chronopost/v1/addresses' ) ) {
			return __( 'A valid WcMultiShipping Pro license is required to synchronise sender addresses with Chronopost PRO.', 'wc-multishipping' );
		}

		if ( false !== strpos( $route, '/chronopost/v1/states' ) ) {
			return __( 'A valid WcMultiShipping Pro license is required to synchronise WooCommerce order statuses with Chronopost PRO.', 'wc-multishipping' );
		}

		return __( 'A valid WcMultiShipping Pro license is required to use Chronopost PRO synchronisation endpoints.', 'wc-multishipping' );
	}

	private function has_valid_pro_license() {
		$is_valid = false;


		return $is_valid;
	}

	private function extract_bearer_token( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! empty( $auth_header ) && strpos( $auth_header, 'Bearer ' ) === 0 ) {
			return substr( $auth_header, 7 );
		}

		$fallback = $request->get_header( 'X-JWT-Token' );
		if ( ! empty( $fallback ) ) {
			return $fallback;
		}

		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) && strpos( $_SERVER['HTTP_AUTHORIZATION'], 'Bearer ' ) === 0 ) {
			return substr( $_SERVER['HTTP_AUTHORIZATION'], 7 );
		}

		return null;
	}


	public function handle_renew( $request ) {
		$this->log_request( 'renew_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () use ( $request ) {
				$params        = $this->get_request_body_params( $request );
				$refresh_token = isset( $params['refresh_token'] ) ? htmlspecialchars( (string) $params['refresh_token'], ENT_QUOTES, 'UTF-8' ) : '';

				if ( empty( $refresh_token ) ) {
					throw new InvalidArgumentException( 'Missing refresh token' );
				}

				$auth    = new chronopost_auth_jwt();
				$decoded = $auth->verify_refresh_token( $refresh_token );

				if ( ! $decoded ) {
					throw new InvalidArgumentException( 'Invalid refresh token' );
				}

				$tokens = $auth->generate_tokens( true );
				if ( ! $tokens ) {
					throw new InvalidArgumentException( 'Failed to generate tokens' );
				}

				$auth->complete_enrollment( $tokens['refresh_token'] );

				wms_logger(
					sprintf(
						'Chronopost PRO dry run [%s]: issued fresh tokens after renew.',
						self::NAMESPACE . '/renew'
					)
				);

				return [
					'token'         => $tokens['access_token'],
					'refresh_token' => $tokens['refresh_token'],
				];
			}
		);
	}

	public function handle_revoke( $request ) {
		$this->log_request( 'revoke_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () use ( $request ) {
				$params = $this->get_request_body_params( $request );
				$token  = isset( $params['token'] ) ? htmlspecialchars( (string) $params['token'], ENT_QUOTES, 'UTF-8' ) : '';

				if ( empty( $token ) ) {
					$token = (string) $this->extract_bearer_token( $request );
				}

				if ( empty( $token ) ) {
					throw new InvalidArgumentException( 'Missing token' );
				}

				$auth = new chronopost_auth_jwt();
				$key  = new \Firebase\JWT\Key( $auth->get_public_key(), 'EdDSA' );
				\Firebase\JWT\JWT::decode( $token, $key );

				if ( $this->is_dry_run_enabled() ) {
					wms_logger( 'Chronopost PRO dry run: revoke request received, authentication kept intact.' );
					return true;
				}

				return $auth->revoke_authentication() === true;
			}
		);
	}


	public function get_orders( $request ) {
		$this->log_request( 'orders_get_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () use ( $request ) {
				$date_from = null;
				$date_to   = null;
				$status    = null;

				if ( '' !== (string) $request->get_param( 'date_from' ) && null !== $request->get_param( 'date_from' ) ) {
					$date_from = ( new DateTime( (string) $request->get_param( 'date_from' ) ) )->format( 'Y-m-d H:i:s' );
				}

				if ( '' !== (string) $request->get_param( 'date_to' ) && null !== $request->get_param( 'date_to' ) ) {
					$date_to = ( new DateTime( (string) $request->get_param( 'date_to' ) ) )->format( 'Y-m-d H:i:s' );
				}

				if ( '' !== (string) $request->get_param( 'status' ) && null !== $request->get_param( 'status' ) ) {
					$status = filter_var( $request->get_param( 'status' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );
					if ( null === $status ) {
						throw new InvalidArgumentException( 'Invalid status value' );
					}
				}

				$model  = new chronopost_pro_order_model();
				$orders = $model->get_orders( $date_from, $date_to, $status );

				wms_logger( sprintf( 'Chronopost PRO: GET /orders returned %d order(s).', count( $orders ) ) );

				return $orders;
			},
			true
		);
	}

	public function update_order( $request ) {
		$this->log_request( 'orders_put_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () use ( $request ) {
				$params = $this->get_request_body_params( $request );

				if ( empty( $params['order_id'] ) ) {
					throw new InvalidArgumentException( 'Missing order ID' );
				}

				$order_id = filter_var( $params['order_id'], FILTER_VALIDATE_INT );
				if ( false === $order_id || ! wc_get_order( $order_id ) ) {
					throw new InvalidArgumentException( 'Invalid order ID' );
				}

				if ( $this->is_dry_run_enabled() ) {
					wms_logger(
						sprintf(
							'Chronopost PRO dry run: skipped order mutation for order #%d with payload %s',
							$order_id,
							wp_json_encode( $this->sanitize_for_log( $params ) )
						)
					);

					return true;
				}

				$model = new chronopost_pro_order_model();

				if ( ! empty( $params['comment'] ) ) {
					$model->add_comment( $order_id, self::sanitize_string( (string) $params['comment'] ) );
				}

				if ( ! empty( $params['status'] ) ) {
					$status = filter_var( $params['status'], FILTER_VALIDATE_INT );
					$model->update_state( $order_id, (int) $status );
				}

				if ( ! empty( $params['tracking'] ) ) {
					$model->update_tracking( $order_id, self::sanitize_string( (string) $params['tracking'] ) );
				}

				return true;
			},
			true
		);
	}


	public function get_address( $request ) {
		$this->log_request( 'addresses_get_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () {
				return $this->build_address_response();
			},
			true
		);
	}

	public function update_address( $request ) {
		$this->log_request( 'addresses_put_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () use ( $request ) {
				$raw_body = $request->get_body();
				$params   = json_decode( $raw_body, false, 10 );

				if ( JSON_ERROR_NONE !== json_last_error() ) {
					throw new JsonException( json_last_error_msg() );
				}

				$field_map = [
					'civility'    => 'wms_chronopost_shipper_civility',
					'name'        => 'wms_chronopost_shipper_name',
					'name2'       => 'wms_chronopost_shipper_name_2',
					'address'     => 'wms_chronopost_shipper_address_1',
					'address2'    => 'wms_chronopost_shipper_address_2',
					'zipcode'     => 'wms_chronopost_shipper_zip_code',
					'city'        => 'wms_chronopost_shipper_city',
					'country'     => 'wms_chronopost_shipper_country',
					'contactName' => 'wms_chronopost_shipper_contact_name',
					'email'       => 'wms_chronopost_shipper_email',
					'phone'       => 'wms_chronopost_shipper_phone',
					'mobile'      => 'wms_chronopost_shipper_mobile_phone',
				];

				if ( $this->is_dry_run_enabled() ) {
					wms_logger(
						sprintf(
							'Chronopost PRO dry run: skipped address update with payload %s',
							wp_json_encode( $this->sanitize_for_log( $params ) )
						)
					);

					return $this->build_address_response();
				}

				foreach ( $field_map as $json_field => $option_key ) {
					if ( isset( $params->$json_field ) ) {
						update_option( $option_key, sanitize_text_field( $params->$json_field ) );
					}
				}

				return $this->build_address_response();
			},
			true
		);
	}

	private function build_address_response() {
		return [
			'type'        => 'shipper',
			'civility'    => get_option( 'wms_chronopost_shipper_civility', '' ),
			'name'        => get_option( 'wms_chronopost_shipper_name', '' ),
			'name2'       => get_option( 'wms_chronopost_shipper_name_2', '' ),
			'address'     => get_option( 'wms_chronopost_shipper_address_1', '' ),
			'address2'    => get_option( 'wms_chronopost_shipper_address_2', '' ),
			'zipcode'     => get_option( 'wms_chronopost_shipper_zip_code', '' ),
			'city'        => get_option( 'wms_chronopost_shipper_city', '' ),
			'country'     => get_option( 'wms_chronopost_shipper_country', 'FR' ),
			'contactName' => get_option( 'wms_chronopost_shipper_contact_name', '' ),
			'email'       => get_option( 'wms_chronopost_shipper_email', '' ),
			'phone'       => get_option( 'wms_chronopost_shipper_phone', '' ),
			'mobile'      => get_option( 'wms_chronopost_shipper_mobile_phone', '' ),
		];
	}

	private function get_request_body_params( $request ) {
		$params = $request->get_body_params();
		if ( ! empty( $params ) ) {
			return $params;
		}

		$json_params = $request->get_json_params();
		if ( ! empty( $json_params ) && is_array( $json_params ) ) {
			return $json_params;
		}

		$body = $request->get_body();
		if ( empty( $body ) ) {
			return [];
		}

		$decoded = json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		parse_str( $body, $parsed );

		return is_array( $parsed ) ? $parsed : [];
	}

	private static function sanitize_string( $string ) {
		return htmlspecialchars( wp_strip_all_tags( trim( $string ) ), ENT_QUOTES, 'UTF-8' );
	}


	public function get_states( $request ) {
		$this->log_request( 'states_get_request', $request );

		return $this->dispatch_enveloped_request(
			$request,
			function () {
				$model = new chronopost_pro_order_model();
				return $model->get_order_stati();
			}
		);
	}

	public function get_relay_points( $request ) {
		$this->log_request( 'relay_points_get_request', $request );

		$method_id = $request->get_param( 'method_id' );
		$postcode = $request->get_param( 'postcode' );
		$city     = $request->get_param( 'city' );
		$address_1 = $request->get_param( 'address_1' );
		$country  = $request->get_param( 'country' ) ?: 'FR';

		if ( empty( $method_id ) || empty( $postcode ) || empty( $city ) ) {
			return new \WP_REST_Response( [
				'status'  => 'error',
				'code'    => 'MISSING_PARAMETERS',
				'message' => 'Required parameters: method_id, postcode, city',
				'data'    => null,
			], 400 );
		}

		$api_helper = new chronopost_api_helper();
		$result     = $api_helper->search_relay_points( $method_id, [
			'postcode'  => $postcode,
			'city'      => $city,
			'country'   => $country,
			'address_1' => $address_1,
		] );

		if ( ! $result || ! empty( $result->errorCode ) || empty( $result->listePointRelais ) ) {
			return new \WP_REST_Response( [
				'status'  => 'error',
				'code'    => 'NO_RELAYS_AVAILABLE',
				'message' => __( 'No relay points found for this address.', 'wc-multishipping' ),
				'data'    => [],
			], 200 );
		}

		$relay_points = [];
		$raw_points   = is_array( $result->listePointRelais ) ? $result->listePointRelais : [ $result->listePointRelais ];

		foreach ( $raw_points as $relay ) {
			$relay_points[] = [
				'id'            => $relay->identifiant ?? '',
				'name'          => $relay->nom ?? '',
				'address'       => $relay->adresse1 ?? '',
				'address2'      => $relay->adresse2 ?? '',
				'city'          => $relay->localite ?? '',
				'postcode'      => $relay->codePostal ?? '',
				'country'       => $relay->codePays ?? $country,
				'latitude'      => $relay->coordGeolocalisationLatitude ?? '',
				'longitude'     => $relay->coordGeolocalisationLongitude ?? '',
				'opening_hours' => $relay->listeHoraireOuverture ?? [],
			];
		}

		return new \WP_REST_Response( [
			'status' => 'success',
			'data'   => [
				'pickup_relays'      => $relay_points,
				'canModifyPostcode'  => false,
				'address'            => [
					'postcode'  => $postcode,
					'city'      => $city,
					'address_1' => $address_1,
					'country'   => $country,
				],
				'mapOptions'         => [
					'methodID'         => $method_id,
					'pickupRelays'     => $relay_points,
					'idMap'            => 'chronomap',
					'pickupRelayIcon'  => '',
					'homeIcon'         => '',
					'activateGmap'     => true,
					'canModifyPostcode'=> false,
				],
			],
		], 200 );
	}

	public function get_shipping_method_settings( $request ) {
		$this->log_request( 'shipping_method_settings_get_request', $request );

		$shipping_method_id = $request->get_param( 'shipping_method_id' );
		$instance_id = $request->get_param( 'instance_id' );

		if ( empty( $shipping_method_id ) || empty( $instance_id ) ) {
			return new \WP_Error(
				'missing_parameters',
				'Missing required parameters',
				[ 'status' => 400 ]
			);
		}

		if ( function_exists( 'WC' ) && WC() ) {
			WC()->initialize_session();
		}

		$method_instance = \WC_Shipping_Zones::get_shipping_method( $instance_id );

		if ( ! $method_instance ) {
			return new \WP_Error(
				'shipping_method_not_found',
				'Shipping method not found',
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( [
			'enable_frontend_saturday_shipping' => $method_instance->get_option( 'enable_frontend_saturday_shipping' ),
			'deliver_on_saturday'              => $method_instance->get_option( 'deliver_on_saturday' ),
			'deliver_on_saturday_amount'       => $method_instance->get_option( 'deliver_on_saturday_amount' ),
			'is_fee_applied'                   => function_exists( 'WC' ) && WC() && WC()->session ? WC()->session->get( 'saturday_shipping' ) : null,
			'is_sending_day'                   => \WCMultiShipping\inc\admin\classes\chronopost\chronopost_label::is_sending_day(),
		] );
	}

	private function is_dry_run_enabled() {
		return self::DRY_RUN_MODE;
	}

	private function log_request( $label, $request ) {
		$payload = [
			'label'       => $label,
			'route'       => method_exists( $request, 'get_route' ) ? $request->get_route() : '',
			'method'      => method_exists( $request, 'get_method' ) ? $request->get_method() : '',
			'params'      => $this->sanitize_for_log( $request->get_params() ),
			'json_params' => $this->sanitize_for_log( $request->get_json_params() ),
			'headers'     => $this->sanitize_headers_for_log( $request ),
			'body'        => $this->sanitize_for_log( $request->get_body() ),
			'dry_run'     => $this->is_dry_run_enabled(),
		];

		wms_logger( 'Chronopost PRO request: ' . wp_json_encode( $payload ) );
	}

	private function sanitize_headers_for_log( $request ) {
		$headers = [];
		foreach ( $request->get_headers() as $key => $values ) {
			$headers[ $key ] = $this->sanitize_for_log( $values );
		}

		return $headers;
	}

	private function sanitize_for_log( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = [];
			foreach ( $value as $key => $item ) {
				$sanitized[ $key ] = $this->sanitize_for_log_value( (string) $key, $item );
			}

			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return $this->sanitize_for_log( (array) $value );
		}

		return $this->sanitize_for_log_value( '', $value );
	}

	private function sanitize_for_log_value( $key, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return $this->sanitize_for_log( $value );
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		$lower_key = strtolower( $key );
		if ( false !== strpos( $lower_key, 'authorization' ) || false !== strpos( $lower_key, 'token' ) ) {
			return $this->mask_sensitive_value( $value );
		}

		return $value;
	}

	private function mask_sensitive_value( $value ) {
		$length = strlen( $value );
		if ( $length <= 12 ) {
			return str_repeat( '*', $length );
		}

		return substr( $value, 0, 6 ) . '...' . substr( $value, -6 );
	}
}
