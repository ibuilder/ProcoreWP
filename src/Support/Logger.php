<?php
/**
 * Opt-in diagnostic logging.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Support;

use ProcoreWP\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Writes diagnostic messages to the WordPress debug log and keeps a short
 * in-database ring buffer for the admin Status screen.
 *
 * Logging is disabled by default. Nothing written here ever contains a client
 * secret or access token; callers are responsible for passing redacted context.
 */
final class Logger {

	/**
	 * Option name holding the recent-events ring buffer.
	 */
	public const OPTION = 'procorewp_log';

	/**
	 * Maximum number of retained entries.
	 */
	private const MAX_ENTRIES = 50;

	/**
	 * Record an error-level event.
	 *
	 * @param string               $message Human readable message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Record a warning-level event.
	 *
	 * @param string               $message Human readable message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Record an informational event.
	 *
	 * @param string               $message Human readable message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Retrieve the retained log entries, newest first.
	 *
	 * @return array<int, array<string, mixed>> Log entries.
	 */
	public static function entries(): array {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Discard all retained log entries.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Append an entry to the ring buffer and, when enabled, the debug log.
	 *
	 * @param string               $level   Severity level.
	 * @param string               $message Human readable message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	private static function log( string $level, string $message, array $context ): void {
		if ( ! Settings::get( 'enable_logging', false ) ) {
			return;
		}

		$entry = array(
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
			'context' => self::redact( $context ),
		);

		$entries = self::entries();
		array_unshift( $entries, $entry );
		$entries = array_slice( $entries, 0, self::MAX_ENTRIES );

		update_option( self::OPTION, $entries, false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$suffix = empty( $entry['context'] ) ? '' : ' ' . wp_json_encode( $entry['context'] );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Gated behind WP_DEBUG_LOG and an explicit opt-in setting.
			error_log( sprintf( '[ProcoreWP][%s] %s%s', $level, $message, $suffix ) );
		}
	}

	/**
	 * Strip anything that resembles a credential from a context array.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed> Redacted context.
	 */
	private static function redact( array $context ): array {
		$sensitive = array( 'client_secret', 'access_token', 'refresh_token', 'authorization', 'code', 'secret', 'password' );
		$clean     = array();

		foreach ( $context as $key => $value ) {
			$normalised = strtolower( (string) $key );

			if ( in_array( $normalised, $sensitive, true ) ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::redact( $value );
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = is_string( $value ) ? mb_substr( $value, 0, 500 ) : $value;
				continue;
			}

			$clean[ $key ] = '[object]';
		}

		return $clean;
	}
}
