<?php
/**
 * Shared behaviour for the Procore OAuth grant types.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Api\Auth;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Environment;
use ProcoreConnect\Api\TokenStore;
use ProcoreConnect\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Implements the token-endpoint exchange common to both grant types.
 */
abstract class AbstractAuth implements AuthInterface {

	/**
	 * Exchange credentials at the Procore token endpoint.
	 *
	 * Note that this posts to the *login* host, which is a different origin to
	 * the REST API host.
	 *
	 * @param array<string, string> $body Request parameters.
	 * @return array<string, mixed>|\WP_Error Decoded token response, or an error.
	 */
	protected function request_token( array $body ) {
		$client_id     = Settings::client_id();
		$client_secret = Settings::client_secret();

		if ( '' === $client_id || '' === $client_secret ) {
			return new \WP_Error(
				'procore_connect_missing_credentials',
				__( 'Enter your Procore Client ID and Client Secret before connecting.', 'connect-for-procore' )
			);
		}

		$body['client_id']     = $client_id;
		$body['client_secret'] = $client_secret;

		$response = wp_remote_post(
			Environment::token_url(),
			array(
				'timeout'    => 20,
				'sslverify'  => true,
				'user-agent' => $this->user_agent(),
				'headers'    => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Accept'       => 'application/json',
				),
				'body'       => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::error( 'Token request transport failure.', array( 'error' => $response->get_error_message() ) );

			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			Logger::error( 'Token endpoint returned an unreadable body.', array( 'status' => $code ) );

			return new \WP_Error(
				'procore_connect_token_unreadable',
				__( 'Procore returned an unreadable authentication response.', 'connect-for-procore' )
			);
		}

		if ( $code >= 400 || isset( $decoded['error'] ) ) {
			$message = (string) ( $decoded['error_description'] ?? $decoded['error'] ?? '' );

			if ( '' === $message ) {
				/* translators: %d: HTTP status code. */
				$message = sprintf( __( 'Procore rejected the authentication request (HTTP %d).', 'connect-for-procore' ), $code );
			}

			Logger::error(
				'Token endpoint rejected the request.',
				array(
					'status'  => $code,
					'message' => $message,
				)
			);

			return new \WP_Error( 'procore_connect_token_rejected', $message, array( 'status' => $code ) );
		}

		if ( empty( $decoded['access_token'] ) ) {
			return new \WP_Error(
				'procore_connect_token_missing',
				__( 'Procore did not return an access token.', 'connect-for-procore' )
			);
		}

		return $decoded;
	}

	/**
	 * User agent sent with authentication requests.
	 *
	 * @return string User agent string.
	 */
	protected function user_agent(): string {
		return sprintf(
			'ConnectForProcore/%s; WordPress/%s; %s',
			PROCORE_CONNECT_VERSION,
			get_bloginfo( 'version' ),
			home_url( '/' )
		);
	}

	/**
	 * Discard any stored token so the next request re-authenticates.
	 *
	 * @return void
	 */
	public function reset(): void {
		TokenStore::clear();
	}
}
