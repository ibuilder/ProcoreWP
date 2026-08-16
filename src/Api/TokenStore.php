<?php
/**
 * Persistence and concurrency control for Procore OAuth tokens.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Api;

use ProcoreConnect\Support\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Stores access and refresh tokens encrypted at rest, and serialises refreshes.
 *
 * Procore invalidates a refresh token the instant it is exchanged. Two
 * concurrent front-end requests both refreshing would therefore leave one
 * holding a dead token and lock the site out of the API until an administrator
 * reconnects. Every refresh is guarded by a short-lived lock, and losers of the
 * race wait briefly and re-read the freshly stored token.
 */
final class TokenStore {

	/**
	 * Option holding the encrypted token payload.
	 */
	public const OPTION = 'procore_connect_tokens';

	/**
	 * Transient name used as the refresh mutex.
	 */
	private const LOCK_KEY = 'procore_connect_token_lock';

	/**
	 * Lifetime of the refresh lock in seconds.
	 */
	private const LOCK_TTL = 30;

	/**
	 * Safety margin subtracted from `expires_in`, in seconds.
	 *
	 * Guards against a token expiring mid-flight on a slow request.
	 */
	private const EXPIRY_MARGIN = 120;

	/**
	 * Read the stored token payload.
	 *
	 * @return array<string, mixed> Token payload with decrypted values.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$payload = wp_parse_args(
			$stored,
			array(
				'access_token'  => '',
				'refresh_token' => '',
				'expires_at'    => 0,
				'obtained_at'   => 0,
				'grant_type'    => '',
				'environment'   => '',
			)
		);

		$payload['access_token']  = Encryption::decrypt( (string) $payload['access_token'] );
		$payload['refresh_token'] = Encryption::decrypt( (string) $payload['refresh_token'] );
		$payload['expires_at']    = (int) $payload['expires_at'];
		$payload['obtained_at']   = (int) $payload['obtained_at'];

		return $payload;
	}

	/**
	 * Persist a token response from Procore.
	 *
	 * @param array<string, mixed> $response   Decoded `/oauth/token` response body.
	 * @param string               $grant_type Grant type that produced the token.
	 * @return void
	 */
	public static function store( array $response, string $grant_type ): void {
		$expires_in = isset( $response['expires_in'] ) ? (int) $response['expires_in'] : 5400;
		$expires_in = max( 60, $expires_in );

		$payload = array(
			'access_token'  => Encryption::encrypt( (string) ( $response['access_token'] ?? '' ) ),
			'refresh_token' => Encryption::encrypt( (string) ( $response['refresh_token'] ?? '' ) ),
			'expires_at'    => time() + $expires_in - self::EXPIRY_MARGIN,
			'obtained_at'   => time(),
			'grant_type'    => $grant_type,
			'environment'   => Environment::current(),
		);

		update_option( self::OPTION, $payload, false );

		/**
		 * Fires after a Procore access token has been stored.
		 *
		 * @since 2.0.0
		 *
		 * @param string $grant_type Grant type that produced the token.
		 * @param int    $expires_at Unix timestamp at which the token should be considered expired.
		 */
		do_action( 'procore_connect_token_stored', $grant_type, (int) $payload['expires_at'] );
	}

	/**
	 * Discard all stored tokens.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
		self::release_lock();
	}

	/**
	 * Whether a usable, unexpired access token is stored for this environment.
	 *
	 * @return bool True when the stored token can be used as-is.
	 */
	public static function has_valid_token(): bool {
		$payload = self::all();

		if ( '' === $payload['access_token'] ) {
			return false;
		}

		// Tokens are not portable between production and sandbox.
		if ( '' !== $payload['environment'] && Environment::current() !== $payload['environment'] ) {
			return false;
		}

		return $payload['expires_at'] > time();
	}

	/**
	 * The stored access token, without validity checks.
	 *
	 * @return string Access token, or an empty string when none is stored.
	 */
	public static function access_token(): string {
		return (string) self::all()['access_token'];
	}

	/**
	 * The stored refresh token.
	 *
	 * @return string Refresh token, or an empty string when none is stored.
	 */
	public static function refresh_token(): string {
		return (string) self::all()['refresh_token'];
	}

	/**
	 * Attempt to acquire the refresh lock.
	 *
	 * @return bool True when this process owns the lock.
	 */
	public static function acquire_lock(): bool {
		// wp_cache_add() is atomic on a persistent object cache; the transient
		// check below covers sites without one.
		if ( wp_cache_add( self::LOCK_KEY, 1, 'procore-connect', self::LOCK_TTL ) ) {
			set_transient( self::LOCK_KEY, 1, self::LOCK_TTL );

			return true;
		}

		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::LOCK_KEY, 1, self::LOCK_TTL );

		return true;
	}

	/**
	 * Release the refresh lock.
	 *
	 * @return void
	 */
	public static function release_lock(): void {
		wp_cache_delete( self::LOCK_KEY, 'connect-for-procore' );
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Wait briefly for another process to finish refreshing.
	 *
	 * @param int $attempts Number of 250ms polls before giving up.
	 * @return bool True when a valid token became available.
	 */
	public static function wait_for_refresh( int $attempts = 8 ): bool {
		for ( $i = 0; $i < $attempts; $i++ ) {
			usleep( 250000 );

			if ( self::has_valid_token() ) {
				return true;
			}
		}

		return false;
	}
}
