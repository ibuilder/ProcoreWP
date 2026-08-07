<?php
/**
 * Procore environment and region host resolution.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Api;

use ProcoreWP\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the login and API hosts for the configured Procore environment.
 *
 * Procore serves authentication from a different host to the REST API — a
 * distinction ProcoreWP 1.x missed, which is why it could never obtain a token.
 * Sandboxes and regional zones each have their own pair of hosts and require
 * their own client credentials.
 */
final class Environment {

	/**
	 * Known environments keyed by settings value.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const ENVIRONMENTS = array(
		'production'      => array(
			'login' => 'https://login.procore.com',
			'api'   => 'https://api.procore.com',
		),
		'sandbox'         => array(
			'login' => 'https://login-sandbox.procore.com',
			'api'   => 'https://sandbox.procore.com',
		),
		'sandbox_monthly' => array(
			'login' => 'https://login-sandbox-monthly.procore.com',
			'api'   => 'https://api-monthly.procore.com',
		),
	);

	/**
	 * Environment identifiers and their translated labels.
	 *
	 * @return array<string, string> Map of value to label.
	 */
	public static function choices(): array {
		return array(
			'production'      => __( 'Production', 'procorewp' ),
			'sandbox'         => __( 'Developer Sandbox', 'procorewp' ),
			'sandbox_monthly' => __( 'Monthly Sandbox', 'procorewp' ),
			'custom'          => __( 'Custom (regional or federal zone)', 'procorewp' ),
		);
	}

	/**
	 * The currently configured environment identifier.
	 *
	 * @return string Environment key.
	 */
	public static function current(): string {
		$value = (string) Settings::get( 'environment', 'production' );

		return array_key_exists( $value, self::choices() ) ? $value : 'production';
	}

	/**
	 * Base URL of the OAuth login host, without a trailing slash.
	 *
	 * @return string Login host URL.
	 */
	public static function login_host(): string {
		$environment = self::current();

		if ( 'custom' === $environment ) {
			return self::normalise( (string) Settings::get( 'custom_login_url', '' ), self::ENVIRONMENTS['production']['login'] );
		}

		/**
		 * Filters the Procore OAuth login host.
		 *
		 * @since 2.0.0
		 *
		 * @param string $host        Login host URL without a trailing slash.
		 * @param string $environment Active environment identifier.
		 */
		return (string) apply_filters( 'procorewp_login_host', self::ENVIRONMENTS[ $environment ]['login'], $environment );
	}

	/**
	 * Base URL of the REST API host, without a trailing slash.
	 *
	 * @return string API host URL.
	 */
	public static function api_host(): string {
		$environment = self::current();

		if ( 'custom' === $environment ) {
			return self::normalise( (string) Settings::get( 'custom_api_url', '' ), self::ENVIRONMENTS['production']['api'] );
		}

		/**
		 * Filters the Procore REST API host.
		 *
		 * @since 2.0.0
		 *
		 * @param string $host        API host URL without a trailing slash.
		 * @param string $environment Active environment identifier.
		 */
		return (string) apply_filters( 'procorewp_api_host', self::ENVIRONMENTS[ $environment ]['api'], $environment );
	}

	/**
	 * Full URL of the OAuth token endpoint.
	 *
	 * @return string Token endpoint URL.
	 */
	public static function token_url(): string {
		return self::login_host() . '/oauth/token';
	}

	/**
	 * Full URL of the OAuth authorization endpoint.
	 *
	 * @return string Authorize endpoint URL.
	 */
	public static function authorize_url(): string {
		return self::login_host() . '/oauth/authorize';
	}

	/**
	 * The redirect URI this site presents during the Authorization Code flow.
	 *
	 * This exact value must be registered in the Procore Developer Portal.
	 *
	 * @return string Redirect URI.
	 */
	public static function redirect_uri(): string {
		/**
		 * Filters the OAuth redirect URI registered with Procore.
		 *
		 * @since 2.0.0
		 *
		 * @param string $uri Redirect URI.
		 */
		return (string) apply_filters(
			'procorewp_redirect_uri',
			admin_url( 'admin-post.php?action=procorewp_oauth_callback' )
		);
	}

	/**
	 * Whether the active environment points at production Procore data.
	 *
	 * @return bool True for production.
	 */
	public static function is_production(): bool {
		return 'production' === self::current();
	}

	/**
	 * Validate and normalise a user-supplied host URL.
	 *
	 * @param string $url      Candidate URL.
	 * @param string $fallback Value used when the candidate is unusable.
	 * @return string Normalised URL without a trailing slash.
	 */
	private static function normalise( string $url, string $fallback ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return $fallback;
		}

		$url   = esc_url_raw( $url, array( 'https' ) );
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] ) {
			return $fallback;
		}

		return 'https://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}
}
