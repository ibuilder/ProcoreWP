<?php
/**
 * OAuth 2.0 Client Credentials grant, backed by a Procore DMSA.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Api\Auth;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\TokenStore;
use ProcoreConnect\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates as a Developer Managed Service Account.
 *
 * This is the correct grant for a public website: the credentials belong to a
 * service account installed by a Procore company administrator, not to a named
 * person, so the integration keeps working when staff change. No refresh token
 * is issued — an expired token is simply requested again.
 */
final class ClientCredentials extends AbstractAuth {

	/**
	 * Return a usable bearer token, requesting a new one when required.
	 *
	 * @return string|\WP_Error Access token, or an error describing the failure.
	 */
	public function access_token() {
		if ( TokenStore::has_valid_token() ) {
			return TokenStore::access_token();
		}

		if ( ! TokenStore::acquire_lock() ) {
			if ( TokenStore::wait_for_refresh() ) {
				return TokenStore::access_token();
			}

			return new \WP_Error(
				'procore_connect_token_busy',
				__( 'Another request is currently authenticating with Procore. Please try again in a moment.', 'connect-for-procore' )
			);
		}

		try {
			// Re-check inside the lock: another process may have just finished.
			if ( TokenStore::has_valid_token() ) {
				return TokenStore::access_token();
			}

			$response = $this->request_token( array( 'grant_type' => 'client_credentials' ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			TokenStore::store( $response, $this->grant_type() );
			Logger::info( 'Obtained a service account access token.' );

			return (string) $response['access_token'];
		} finally {
			TokenStore::release_lock();
		}
	}

	/**
	 * Whether a client ID and secret are available.
	 *
	 * @return bool True when configured.
	 */
	public function is_configured(): bool {
		return '' !== Settings::client_id() && '' !== Settings::client_secret();
	}

	/**
	 * Grant type identifier.
	 *
	 * @return string Grant type.
	 */
	public function grant_type(): string {
		return 'client_credentials';
	}

	/**
	 * Human readable name.
	 *
	 * @return string Label.
	 */
	public function label(): string {
		return __( 'Service Account (Client Credentials)', 'connect-for-procore' );
	}
}
