<?php
/**
 * Response caching for Procore API calls.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Api;

use ProcoreWP\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Transient-backed cache with a stale fallback layer.
 *
 * Procore enforces both an hourly and a ten-second spike rate limit, so an
 * uncached shortcode on a busy page will exhaust the quota within minutes.
 * Every response therefore lands in two places: a short-lived "fresh" entry
 * that satisfies normal reads, and a long-lived "stale" copy that is served
 * when Procore is unreachable, so an outage degrades a page rather than
 * blanking it.
 *
 * Storage goes through the transient API so sites running a persistent object
 * cache (Redis, Memcached) get that behaviour for free.
 */
final class Cache {

	/**
	 * Prefix for fresh cache entries.
	 */
	private const FRESH_PREFIX = 'procorewp_c_';

	/**
	 * Prefix for stale fallback entries.
	 */
	private const STALE_PREFIX = 'procorewp_s_';

	/**
	 * Option storing the key index used for targeted purging.
	 */
	private const INDEX_OPTION = 'procorewp_cache_index';

	/**
	 * Longest lifetime for a stale fallback entry, in seconds.
	 */
	private const STALE_MAX_TTL = WEEK_IN_SECONDS;

	/**
	 * Build a deterministic cache key.
	 *
	 * The environment is part of the key so switching between production and a
	 * sandbox never serves data from the other.
	 *
	 * @param string               $slug    Endpoint slug.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param int                  $company Company identifier.
	 * @param int                  $project Project identifier.
	 * @return string Cache key.
	 */
	public static function key( string $slug, array $args, int $company, int $project ): string {
		ksort( $args );

		$signature = wp_json_encode(
			array(
				'slug'        => $slug,
				'args'        => $args,
				'company'     => $company,
				'project'     => $project,
				'environment' => Environment::current(),
				'host'        => Environment::api_host(),
			)
		);

		return md5( (string) $signature );
	}

	/**
	 * Read a fresh cache entry.
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value, or null on a miss.
	 */
	public static function get( string $key ) {
		if ( ! self::enabled() ) {
			return null;
		}

		$value = get_transient( self::FRESH_PREFIX . $key );

		return false === $value ? null : $value;
	}

	/**
	 * Read the stale fallback copy of an entry.
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value, or null when no fallback exists.
	 */
	public static function get_stale( string $key ) {
		$value = get_transient( self::STALE_PREFIX . $key );

		return false === $value ? null : $value;
	}

	/**
	 * Store a value in both the fresh and stale layers.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Fresh lifetime in seconds.
	 * @param string $group Cache group used for targeted purging.
	 * @return void
	 */
	public static function set( string $key, $value, int $ttl, string $group = 'default' ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$ttl = self::clamp_ttl( $ttl );

		set_transient( self::FRESH_PREFIX . $key, $value, $ttl );
		set_transient( self::STALE_PREFIX . $key, $value, min( self::STALE_MAX_TTL, max( $ttl * 12, DAY_IN_SECONDS ) ) );

		self::index( $group, $key );
	}

	/**
	 * Remove a single entry from both layers.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public static function delete( string $key ): void {
		delete_transient( self::FRESH_PREFIX . $key );
		delete_transient( self::STALE_PREFIX . $key );
	}

	/**
	 * Purge cached responses.
	 *
	 * @param string $group Group to purge, or an empty string to purge everything.
	 * @return int Number of entries removed.
	 */
	public static function flush( string $group = '' ): int {
		$index   = self::read_index();
		$removed = 0;

		foreach ( $index as $indexed_group => $keys ) {
			if ( '' !== $group && $indexed_group !== $group ) {
				continue;
			}

			foreach ( (array) $keys as $key ) {
				self::delete( (string) $key );
				++$removed;
			}

			unset( $index[ $indexed_group ] );
		}

		if ( '' === $group ) {
			delete_option( self::INDEX_OPTION );
		} else {
			update_option( self::INDEX_OPTION, $index, false );
		}

		/**
		 * Fires after the Procore response cache has been purged.
		 *
		 * @since 2.0.0
		 *
		 * @param string $group   Purged group, or an empty string for a full purge.
		 * @param int    $removed Number of entries removed.
		 */
		do_action( 'procorewp_cache_flushed', $group, $removed );

		return $removed;
	}

	/**
	 * Summarise the current cache contents for the admin Status screen.
	 *
	 * @return array<string, int> Entry count keyed by group, plus a `total` key.
	 */
	public static function stats(): array {
		$index = self::read_index();
		$stats = array();
		$total = 0;

		foreach ( $index as $group => $keys ) {
			$count           = count( (array) $keys );
			$stats[ $group ] = $count;
			$total          += $count;
		}

		$stats['total'] = $total;

		return $stats;
	}

	/**
	 * Whether caching is currently active.
	 *
	 * @return bool True when enabled.
	 */
	public static function enabled(): bool {
		/**
		 * Filters whether Procore responses are cached.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enabled Whether caching is active.
		 */
		return (bool) apply_filters( 'procorewp_cache_enabled', (bool) Settings::get( 'enable_cache', true ) );
	}

	/**
	 * Constrain a requested lifetime to the configured bounds.
	 *
	 * Shortcode authors may shorten a lifetime but never below the site-wide
	 * floor, so a single page cannot be configured into a rate-limit breach.
	 *
	 * @param int $ttl Requested lifetime in seconds.
	 * @return int Permitted lifetime in seconds.
	 */
	public static function clamp_ttl( int $ttl ): int {
		$floor = (int) Settings::get( 'cache_floor', 60 );
		$floor = max( 30, $floor );

		return max( $floor, min( $ttl, MONTH_IN_SECONDS ) );
	}

	/**
	 * Record a key against a group so it can be purged selectively.
	 *
	 * @param string $group Group name.
	 * @param string $key   Cache key.
	 * @return void
	 */
	private static function index( string $group, string $key ): void {
		$index = self::read_index();

		if ( ! isset( $index[ $group ] ) || ! is_array( $index[ $group ] ) ) {
			$index[ $group ] = array();
		}

		if ( in_array( $key, $index[ $group ], true ) ) {
			return;
		}

		$index[ $group ][] = $key;

		// Keep the index bounded; the oldest keys simply expire on their own.
		if ( count( $index[ $group ] ) > 500 ) {
			$index[ $group ] = array_slice( $index[ $group ], -500 );
		}

		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Read the purge index.
	 *
	 * @return array<string, array<int, string>> Index keyed by group.
	 */
	private static function read_index(): array {
		$index = get_option( self::INDEX_OPTION, array() );

		return is_array( $index ) ? $index : array();
	}
}
