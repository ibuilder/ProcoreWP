<?php
/**
 * OAuth 2.0 Authorization Code grant.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Api\Auth;

use ProcoreWP\Admin\Settings;
use ProcoreWP\Api\Environment;
use ProcoreWP\Api\TokenStore;
use ProcoreWP\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates on behalf of a signed-in Procore user.
 *
 * An administrator clicks "Connect to Procore", authorises the app, and the
 * plugin exchanges the returned code for an access and refresh token pair. The
 * resulting permissions are those of that individual, so the connection breaks
 * if their Procore access is revoked; the service account grant is preferable
 * for unattended sites.
 *
 * Procore invalidates a refresh token the moment it is exchanged, so the new
 * pair is always written to storage before the new access token is used.
 */
final class AuthorizationCode extends AbstractAuth {

	/**
	 * Transient prefix for pending CSRF state values.
	 */
	private const STATE_PREFIX = 'procorewp_oauth_state_';

	/**
	 * Lifetime of a pending state value, in seconds.
	 */
	private const STATE_TTL = 900;

	/**
	 * Return a usable bearer token, refreshing when required.
	 *
	 * @return string|\WP_Error Access token, or an error describing the failure.
	 */
	public function access_token() {
		if ( TokenStore::has_valid_token() ) {
			return TokenStore::access_token();
		}

		if ( '' === TokenStore::refresh_token() ) {
			return new \WP_Error(
				'procorewp_not_connected',
				__( 'This site is not connected to Procore. Open Procore → Connection and choose "Connect to Procore".', 'procorewp' )
			);
		}

		if ( ! TokenStore::acquire_lock() ) {
			if ( TokenStore::wait_for_refresh() ) {
				return TokenStore::access_token();
			}

			return new \WP_Error(
				'procorewp_token_busy',
				__( 'Another request is currently refreshing the Procore connection. Please try again in a moment.', 'procorewp' )
			);
		}

		try {
			if ( TokenStore::has_valid_token() ) {
				return TokenStore::access_token();
			}

			$refresh_token = TokenStore::refresh_token();

			if ( '' === $refresh_token ) {
				return new \WP_Error(
					'procorewp_not_connected',
					__( 'This site is not connected to Procore.', 'procorewp' )
				);
			}

			$response = $this->request_token(
				array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
					'redirect_uri'  => Environment::redirect_uri(),
				)
			);

			if ( is_wp_error( $response ) ) {
				// A rejected refresh token can never recover; force a reconnect.
				if ( 'procorewp_token_rejected' === $response->get_error_code() ) {
					TokenStore::clear();
					Logger::error( 'Refresh token rejected by Procore; the connection has been reset.' );

					return new \WP_Error(
						'procorewp_reconnect_required',
						__( 'The Procore connection has expired. Open Procore → Connection and reconnect.', 'procorewp' )
					);
				}

				return $response;
			}

			TokenStore::store( $response, $this->grant_type() );
			Logger::info( 'Refreshed the Procore user access token.' );

			return (string) $response['access_token'];
		} finally {
			TokenStore::release_lock();
		}//end try
	}

	/**
	 * Build the Procore authorization URL and register its CSRF state.
	 *
	 * @return string Authorization URL.
	 */
	public function authorization_url(): string {
		$state = wp_generate_password( 32, false );

		set_transient( self::STATE_PREFIX . $state, get_current_user_id(), self::STATE_TTL );

		return add_query_arg(
			array(
				'client_id'     => rawurlencode( Settings::client_id() ),
				'response_type' => 'code',
				'redirect_uri'  => rawurlencode( Environment::redirect_uri() ),
				'state'         => rawurlencode( $state ),
			),
			Environment::authorize_url()
		);
	}

	/**
	 * Exchange an authorization code for a token pair.
	 *
	 * @param string $code  Authorization code returned by Procore.
	 * @param string $state CSRF state value returned by Procore.
	 * @return true|\WP_Error True on success, or an error describing the failure.
	 */
	public function exchange_code( string $code, string $state ) {
		if ( '' === $state || false === get_transient( self::STATE_PREFIX . $state ) ) {
			return new \WP_Error(
				'procorewp_bad_state',
				__( 'The Procore authorization response could not be verified. Please start the connection again.', 'procorewp' )
			);
		}

		delete_transient( self::STATE_PREFIX . $state );

		if ( '' === $code ) {
			return new \WP_Error(
				'procorewp_missing_code',
				__( 'Procore did not return an authorization code.', 'procorewp' )
			);
		}

		$response = $this->request_token(
			array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => Environment::redirect_uri(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		TokenStore::store( $response, $this->grant_type() );
		Logger::info( 'Completed the Procore authorization code exchange.' );

		return true;
	}

	/**
	 * Whether the site currently holds a user connection.
	 *
	 * @return bool True when a refresh token is stored.
	 */
	public function is_connected(): bool {
		return '' !== TokenStore::refresh_token();
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
		return 'authorization_code';
	}

	/**
	 * Human readable name.
	 *
	 * @return string Label.
	 */
	public function label(): string {
		return __( 'User Account (Authorization Code)', 'procorewp' );
	}
}
