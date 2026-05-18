<?php

namespace WCMultiShipping\inc\admin\classes\chronopost\auth;

$firebase_jwt_dir = __DIR__ . '/../vendor/firebase/php-jwt/';

if ( ! interface_exists( '\Firebase\JWT\JWTExceptionWithPayloadInterface', false ) ) {
	require_once $firebase_jwt_dir . 'JWTExceptionWithPayloadInterface.php';
}

if ( ! class_exists( '\Firebase\JWT\SignatureInvalidException', false ) ) {
	require_once $firebase_jwt_dir . 'SignatureInvalidException.php';
}

if ( ! class_exists( '\Firebase\JWT\BeforeValidException', false ) ) {
	require_once $firebase_jwt_dir . 'BeforeValidException.php';
}

if ( ! class_exists( '\Firebase\JWT\ExpiredException', false ) ) {
	require_once $firebase_jwt_dir . 'ExpiredException.php';
}

if ( ! class_exists( '\Firebase\JWT\Key', false ) ) {
	require_once $firebase_jwt_dir . 'Key.php';
}

if ( ! class_exists( '\Firebase\JWT\JWT', false ) ) {
	require_once $firebase_jwt_dir . 'JWT.php';
}

class chronopost_auth_jwt implements chronopost_auth_interface {

	const TOKEN_LIFETIME = 15 * 60;
	const REFRESH_TOKEN_LIFETIME = 4 * 30 * 24 * 60 * 60;

	public function authenticate() {
		if ( ! $this->has_keys() ) {
			return false;
		}

		if ( $this->is_token_expired() ) {
			return $this->refresh_token();
		}

		return true;
	}

	public function is_authenticated() {
		return $this->has_keys() && $this->is_enrollment_complete() && ! $this->is_refresh_token_expired();
	}

	public function get_credentials() {
		if ( ! $this->is_authenticated() ) {
			return null;
		}

		$tokens = $this->get_current_tokens();

		return [
			'access_token'  => $tokens['access_token'] ?? null,
			'refresh_token' => $tokens['refresh_token'] ?? null,
			'public_key'    => $this->get_public_key(),
		];
	}

	public function revoke_authentication() {
		delete_option( 'wms_chronopost_jwt_public_key' );
		delete_option( 'wms_chronopost_jwt_private_key' );
		delete_option( 'wms_chronopost_jwt_refresh_token_expiration' );
		delete_option( 'wms_chronopost_jwt_enrollment_complete' );
		delete_option( 'wms_chronopost_jwt_access_token' );
		delete_option( 'wms_chronopost_jwt_refresh_token' );
		delete_option( 'wms_chronopost_jwt_access_token_expiration' );

		$has_soap_configuration =
			trim( (string) get_option( 'wms_chronopost_account_number', '' ) ) !== '' &&
			trim( (string) get_option( 'wms_chronopost_account_password', '' ) ) !== '';

		if ( $has_soap_configuration ) {
			update_option( 'wms_chronopost_connection_type', 'soap' );
		} else {
			delete_option( 'wms_chronopost_connection_type' );
		}

		return true;
	}

	public function get_auth_type() {
		return 'jwt';
	}

	public function generate_keys() {
		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			wms_logger( __( 'Sodium extension is not available. Cannot generate JWT keys.', 'wc-multishipping' ) );
			return false;
		}

		try {
			$keys = sodium_crypto_sign_keypair();

			$public_key = base64_encode( sodium_crypto_sign_publickey( $keys ) );
			update_option( 'wms_chronopost_jwt_public_key', $public_key, false );

			$private_key = base64_encode( sodium_crypto_sign_secretkey( $keys ) );
			update_option( 'wms_chronopost_jwt_private_key', $private_key, false );

			return true;
		} catch ( \Exception $e ) {
			wms_logger( sprintf( __( 'Error generating JWT keys: %s', 'wc-multishipping' ), $e->getMessage() ) );
			return false;
		}
	}

	public function has_keys() {
		$public_key  = get_option( 'wms_chronopost_jwt_public_key' );
		$private_key = get_option( 'wms_chronopost_jwt_private_key' );

		return ! empty( $public_key ) && ! empty( $private_key );
	}

	public function get_public_key() {
		return get_option( 'wms_chronopost_jwt_public_key', '' );
	}

	public function get_private_key() {
		return get_option( 'wms_chronopost_jwt_private_key', '' );
	}

	public function generate_tokens( $refreshed = false, $persist = true ) {
		$private_key = $this->get_private_key();
		if ( empty( $private_key ) ) {
			return false;
		}

		$issued_at = time();

		$token_payload = [
			'iss'  => get_bloginfo( 'name' ),
			'aud'  => 'chronopost.fr',
			'iat'  => $issued_at,
			'nbf'  => $issued_at,
			'exp'  => $issued_at + self::TOKEN_LIFETIME,
			'shop' => 'WOO',
		];

		$refresh_token_payload = [
			'iss'  => get_bloginfo( 'name' ),
			'aud'  => 'chronopost.fr',
			'type' => 'refresh',
			'iat'  => $issued_at,
			'nbf'  => $issued_at,
			'exp'  => $issued_at + self::REFRESH_TOKEN_LIFETIME,
			'shop' => 'WOO',
		];

		if ( $refreshed ) {
			$token_payload['refreshed']         = true;
			$refresh_token_payload['refreshed'] = true;
		}

		try {
			$access_token  = \Firebase\JWT\JWT::encode( $token_payload, $private_key, 'EdDSA' );
			$refresh_token = \Firebase\JWT\JWT::encode( $refresh_token_payload, $private_key, 'EdDSA' );

			if ( $persist ) {
				update_option( 'wms_chronopost_jwt_access_token', $access_token, false );
				update_option( 'wms_chronopost_jwt_refresh_token', $refresh_token, false );
				update_option( 'wms_chronopost_jwt_access_token_expiration', $token_payload['exp'], false );
				update_option( 'wms_chronopost_jwt_refresh_token_expiration', $refresh_token_payload['exp'], false );
			}

			return [
				'access_token'  => $access_token,
				'refresh_token' => $refresh_token,
			];
		} catch ( \Exception $e ) {
			wms_logger( sprintf( __( 'Error generating JWT tokens: %s', 'wc-multishipping' ), $e->getMessage() ) );
			return false;
		}
	}

	public function verify_access_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		$public_key_b64 = $this->get_public_key();
		if ( empty( $public_key_b64 ) ) {
			return false;
		}

		try {
			$key     = new \Firebase\JWT\Key( $public_key_b64, 'EdDSA' );
			$decoded = \Firebase\JWT\JWT::decode( $token, $key );
			return $decoded;
		} catch ( \Exception $e ) {
			wms_logger( sprintf( __( 'JWT token verification failed: %s', 'wc-multishipping' ), $e->getMessage() ) );
			return false;
		}
	}

	public function verify_refresh_token( $token ) {
		$decoded = $this->verify_access_token( $token );
		if ( ! $decoded ) {
			return false;
		}
		if ( ! isset( $decoded->type ) || $decoded->type !== 'refresh' ) {
			return false;
		}
		return $decoded;
	}

	public function complete_enrollment( $refresh_token = null ) {
		if ( $refresh_token ) {
			update_option( 'wms_chronopost_jwt_refresh_token', $refresh_token, false );
		}

		update_option( 'wms_chronopost_jwt_enrollment_complete', '1', false );

		return true;
	}

	public function is_enrollment_complete() {
		return get_option( 'wms_chronopost_jwt_enrollment_complete' ) === '1';
	}

	public function is_token_expired() {
		$expiration = get_option( 'wms_chronopost_jwt_access_token_expiration', null );

		if ( ! is_numeric( $expiration ) ) {
			return false;
		}

		return time() >= (int) $expiration;
	}

	public function is_refresh_token_expired() {
		$expiration = get_option( 'wms_chronopost_jwt_refresh_token_expiration', null );

		if ( ! is_numeric( $expiration ) ) {
			return false;
		}

		return time() >= (int) $expiration;
	}

	public function refresh_token() {
		$stored_token = get_option( 'wms_chronopost_jwt_refresh_token' );

		if ( empty( $stored_token ) ) {
			return false;
		}

		if ( ! $this->verify_refresh_token( $stored_token ) ) {
			return false;
		}

		return $this->generate_tokens( true );
	}

	public function get_current_tokens() {
		return [
			'access_token'  => get_option( 'wms_chronopost_jwt_access_token', '' ),
			'refresh_token' => get_option( 'wms_chronopost_jwt_refresh_token', '' ),
		];
	}

	public function get_access_token() {
		if ( $this->is_token_expired() ) {
			$this->refresh_token();
		}

		return get_option( 'wms_chronopost_jwt_access_token', '' );
	}
}
