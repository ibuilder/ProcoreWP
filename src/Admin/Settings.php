<?php
/**
 * Plugin settings storage, defaults and sanitization.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Admin;

use ProcoreWP\Api\Environment;
use ProcoreWP\Support\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's single settings option.
 *
 * The client secret is encrypted before it reaches the database and is never
 * echoed back into a form field. Sites that prefer to keep credentials out of
 * the database entirely can define `PROCOREWP_CLIENT_ID` and
 * `PROCOREWP_CLIENT_SECRET` in `wp-config.php`; those take precedence and are
 * never written to an option.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'procorewp_settings';

	/**
	 * Settings group used by the Settings API.
	 */
	public const GROUP = 'procorewp_settings_group';

	/**
	 * Legacy option written by ProcoreWP 1.x.
	 */
	public const LEGACY_OPTION = 'procore_integration_settings';

	/**
	 * Memoised settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public static function defaults(): array {
		return array(
			'auth_mode'          => 'client_credentials',
			'environment'        => 'production',
			'custom_login_url'   => '',
			'custom_api_url'     => '',
			'client_id'          => '',
			'client_secret'      => '',
			'default_company_id' => 0,
			'default_project_id' => 0,

			'enable_cache'       => true,
			'cache_floor'        => 300,
			'cache_multiplier'   => 100,
			'per_page'           => 100,
			'request_timeout'    => 15,

			'suppress_emails'    => true,
			'date_format'        => '',
			'currency_symbol'    => '$',
			'custom_css'         => '',
			'load_styles'        => true,

			'enable_rest'        => false,
			'rest_access'        => 'logged_in',
			'enable_blocks'      => true,
			'enable_logging'     => false,
			'keep_data'          => false,
		);
	}

	/**
	 * All settings, with defaults applied.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );

		self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

		return self::$cache;
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Value returned when the key is unknown.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default_value = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	/**
	 * Write a single setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public static function set( string $key, $value ): void {
		$all         = self::all();
		$all[ $key ] = $value;

		update_option( self::OPTION, $all, false );
		self::flush();
	}

	/**
	 * Discard the memoised settings.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * The effective Procore client ID.
	 *
	 * @return string Client ID.
	 */
	public static function client_id(): string {
		if ( defined( 'PROCOREWP_CLIENT_ID' ) && '' !== (string) constant( 'PROCOREWP_CLIENT_ID' ) ) {
			return (string) constant( 'PROCOREWP_CLIENT_ID' );
		}

		return (string) self::get( 'client_id', '' );
	}

	/**
	 * The effective Procore client secret, decrypted.
	 *
	 * @return string Client secret.
	 */
	public static function client_secret(): string {
		if ( defined( 'PROCOREWP_CLIENT_SECRET' ) && '' !== (string) constant( 'PROCOREWP_CLIENT_SECRET' ) ) {
			return (string) constant( 'PROCOREWP_CLIENT_SECRET' );
		}

		return Encryption::decrypt( (string) self::get( 'client_secret', '' ) );
	}

	/**
	 * Whether credentials come from `wp-config.php` constants.
	 *
	 * @return bool True when constants are in use.
	 */
	public static function credentials_are_constants(): bool {
		return defined( 'PROCOREWP_CLIENT_SECRET' ) && '' !== (string) constant( 'PROCOREWP_CLIENT_SECRET' );
	}

	/**
	 * The default Procore company identifier.
	 *
	 * @return int Company ID, or zero when unset.
	 */
	public static function default_company_id(): int {
		if ( defined( 'PROCOREWP_COMPANY_ID' ) ) {
			return absint( constant( 'PROCOREWP_COMPANY_ID' ) );
		}

		return absint( self::get( 'default_company_id', 0 ) );
	}

	/**
	 * The default Procore project identifier.
	 *
	 * @return int Project ID, or zero when unset.
	 */
	public static function default_project_id(): int {
		return absint( self::get( 'default_project_id', 0 ) );
	}

	/**
	 * Sanitize the settings array submitted from the options form.
	 *
	 * Registered as the `sanitize_callback` for `register_setting()`, which
	 * ProcoreWP 1.x omitted entirely.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( $input ): array {
		$existing = self::all();
		$input    = is_array( $input ) ? $input : array();
		$clean    = $existing;

		$clean['auth_mode'] = in_array( $input['auth_mode'] ?? '', array( 'client_credentials', 'authorization_code' ), true )
			? $input['auth_mode']
			: 'client_credentials';

		$clean['environment'] = array_key_exists( $input['environment'] ?? '', Environment::choices() )
			? $input['environment']
			: 'production';

		$clean['custom_login_url'] = esc_url_raw( trim( (string) ( $input['custom_login_url'] ?? '' ) ), array( 'https' ) );
		$clean['custom_api_url']   = esc_url_raw( trim( (string) ( $input['custom_api_url'] ?? '' ) ), array( 'https' ) );

		$clean['client_id'] = sanitize_text_field( (string) ( $input['client_id'] ?? '' ) );

		// An empty secret field means "leave the stored secret alone"; the form
		// renders a masked placeholder rather than the real value.
		$submitted_secret = trim( (string) ( $input['client_secret'] ?? '' ) );

		if ( '' !== $submitted_secret ) {
			$clean['client_secret'] = Encryption::encrypt( $submitted_secret );
		}

		if ( ! empty( $input['clear_client_secret'] ) ) {
			$clean['client_secret'] = '';
		}

		$clean['default_company_id'] = absint( $input['default_company_id'] ?? 0 );
		$clean['default_project_id'] = absint( $input['default_project_id'] ?? 0 );

		$clean['enable_cache']     = ! empty( $input['enable_cache'] );
		$clean['cache_floor']      = max( 30, min( DAY_IN_SECONDS, absint( $input['cache_floor'] ?? 300 ) ) );
		$clean['cache_multiplier'] = max( 10, min( 1000, absint( $input['cache_multiplier'] ?? 100 ) ) );
		$clean['per_page']         = max( 1, min( 2000, absint( $input['per_page'] ?? 100 ) ) );
		$clean['request_timeout']  = max( 5, min( 60, absint( $input['request_timeout'] ?? 15 ) ) );

		$clean['suppress_emails'] = ! empty( $input['suppress_emails'] );
		$clean['date_format']     = sanitize_text_field( (string) ( $input['date_format'] ?? '' ) );
		$clean['currency_symbol'] = sanitize_text_field( (string) ( $input['currency_symbol'] ?? '$' ) );
		$clean['custom_css']      = wp_strip_all_tags( (string) ( $input['custom_css'] ?? '' ) );
		$clean['load_styles']     = ! empty( $input['load_styles'] );

		$clean['enable_rest']    = ! empty( $input['enable_rest'] );
		$clean['rest_access']    = in_array( $input['rest_access'] ?? '', array( 'public', 'logged_in', 'editor' ), true )
			? $input['rest_access']
			: 'logged_in';
		$clean['enable_blocks']  = ! empty( $input['enable_blocks'] );
		$clean['enable_logging'] = ! empty( $input['enable_logging'] );
		$clean['keep_data']      = ! empty( $input['keep_data'] );

		self::flush();

		/**
		 * Filters the sanitized settings before they are stored.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, mixed> $clean Sanitized settings.
		 * @param array<string, mixed> $input Raw submitted values.
		 */
		return (array) apply_filters( 'procorewp_sanitize_settings', $clean, $input );
	}

	/**
	 * Import settings saved by ProcoreWP 1.x.
	 *
	 * The 1.x option stored the secret in plain text and held a token obtained
	 * from the wrong host, so the secret is re-encrypted and the token dropped.
	 *
	 * @return bool True when a legacy option was migrated.
	 */
	public static function migrate_legacy(): bool {
		$legacy = get_option( self::LEGACY_OPTION );

		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return false;
		}

		$current = self::all();

		if ( ! empty( $legacy['client_id'] ) && '' === (string) $current['client_id'] ) {
			$current['client_id'] = sanitize_text_field( (string) $legacy['client_id'] );
		}

		if ( ! empty( $legacy['client_secret'] ) && '' === (string) $current['client_secret'] ) {
			$current['client_secret'] = Encryption::encrypt( (string) $legacy['client_secret'] );
		}

		if ( ! empty( $legacy['default_company_id'] ) && 0 === (int) $current['default_company_id'] ) {
			$current['default_company_id'] = absint( $legacy['default_company_id'] );
		}

		update_option( self::OPTION, $current, false );
		update_option( 'procorewp_migrated_from', '1.x', false );
		delete_option( self::LEGACY_OPTION );

		self::flush();

		return true;
	}
}
