<?php
/**
 * HTTP client for the Procore REST API.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Api;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Auth\AuthInterface;
use ProcoreConnect\Api\Auth\AuthorizationCode;
use ProcoreConnect\Api\Auth\ClientCredentials;
use ProcoreConnect\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * The single choke point for every Procore API call.
 *
 * Responsibilities beyond issuing the request: attaching the mandatory
 * `Procore-Company-Id` header, honouring the documented rate-limit headers,
 * backing off on 429 and 503, walking `Link` headers for pagination, breaking
 * the circuit after repeated failures, and falling back to a stale cached copy
 * rather than letting a Procore outage blank a published page.
 */
final class Client {

	/**
	 * Option holding the most recent rate-limit snapshot.
	 */
	public const RATE_LIMIT_OPTION = 'procore_connect_rate_limit';

	/**
	 * Option holding circuit breaker state.
	 */
	public const CIRCUIT_OPTION = 'procore_connect_circuit';

	/**
	 * Consecutive failures before the circuit opens.
	 */
	private const CIRCUIT_THRESHOLD = 5;

	/**
	 * How long the circuit stays open, in seconds.
	 */
	private const CIRCUIT_COOLDOWN = 300;

	/**
	 * Maximum attempts per request, including the first.
	 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Hard ceiling on pages walked when collecting a full result set.
	 */
	private const MAX_PAGES = 20;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Authentication strategy.
	 *
	 * @var AuthInterface|null
	 */
	private $auth = null;

	/**
	 * HTTP transport, overridable for testing.
	 *
	 * @var callable|null
	 */
	private $transport = null;

	/**
	 * Metadata describing the most recent fetch.
	 *
	 * @var array<string, mixed>
	 */
	private $meta = array();

	/**
	 * Retrieve the shared client instance.
	 *
	 * @return self Client instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Replace the shared instance. Intended for tests.
	 *
	 * @param self|null $client Replacement client, or null to reset.
	 * @return void
	 */
	public static function set_instance( ?self $client ): void {
		self::$instance = $client;
	}

	/**
	 * Override the HTTP transport. Intended for tests.
	 *
	 * @param callable|null $transport Callable matching `wp_remote_request( $url, $args )`.
	 * @return void
	 */
	public function set_transport( ?callable $transport ): void {
		$this->transport = $transport;
	}

	/**
	 * Override the authentication strategy.
	 *
	 * @param AuthInterface|null $auth Strategy, or null to resolve from settings.
	 * @return void
	 */
	public function set_auth( ?AuthInterface $auth ): void {
		$this->auth = $auth;
	}

	/**
	 * The active authentication strategy.
	 *
	 * @return AuthInterface Strategy resolved from the saved settings.
	 */
	public function auth(): AuthInterface {
		if ( null === $this->auth ) {
			$this->auth = 'authorization_code' === Settings::get( 'auth_mode', 'client_credentials' )
				? new AuthorizationCode()
				: new ClientCredentials();
		}

		return $this->auth;
	}

	/**
	 * Metadata describing the most recent fetch.
	 *
	 * @return array<string, mixed> Metadata including `cached`, `stale` and `total`.
	 */
	public function meta(): array {
		return $this->meta;
	}

	/**
	 * Fetch a registered endpoint.
	 *
	 * @param string               $slug    Endpoint slug from the registry.
	 * @param array<string, mixed> $params  Additional query parameters.
	 * @param array<string, mixed> $options Behaviour overrides: `company_id`, `project_id`,
	 *                                      `ttl`, `all`, `per_page`, `max_pages`, `bypass_cache`.
	 * @return mixed|\WP_Error Decoded response body, or an error.
	 */
	public function fetch( string $slug, array $params = array(), array $options = array() ) {
		$definition = Endpoints::get( $slug );

		if ( null === $definition ) {
			return new \WP_Error(
				'procore_connect_unknown_endpoint',
				/* translators: %s: endpoint slug. */
				sprintf( __( 'Unknown Procore endpoint: %s', 'procore-connect' ), $slug )
			);
		}

		/*
		 * A zero is treated as "not supplied" rather than as an explicit
		 * choice, so callers that always pass the key — the REST proxy sends
		 * an integer parameter that defaults to 0 — still inherit the site
		 * defaults instead of failing with a missing-context error.
		 */
		$company = absint( $options['company_id'] ?? 0 );
		$project = absint( $options['project_id'] ?? 0 );

		if ( $company <= 0 ) {
			$company = Settings::default_company_id();
		}

		if ( $project <= 0 ) {
			$project = Settings::default_project_id();
		}

		if ( Endpoints::SCOPE_NONE !== $definition['scope'] && $company <= 0 ) {
			return new \WP_Error(
				'procore_connect_missing_company',
				__( 'No Procore company ID is available. Set a default company in Procore → Connection, or pass company_id.', 'procore-connect' )
			);
		}

		$path = Endpoints::path( $slug, $company, $project );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$query = array_merge( Endpoints::context_query( $slug, $company, $project ), $params );

		if ( ! empty( $definition['paginated'] ) ) {
			$query['per_page'] = min( 2000, max( 1, (int) ( $options['per_page'] ?? Settings::get( 'per_page', 100 ) ) ) );
			$query['page']     = max( 1, (int) ( $query['page'] ?? $options['page'] ?? 1 ) );
		} else {
			// A single-record resource has no pages; sending them is meaningless
			// noise that also varies the cache key for identical requests.
			unset( $query['page'], $query['per_page'] );
		}

		$ttl        = isset( $options['ttl'] ) ? (int) $options['ttl'] : (int) $definition['ttl'];
		$collect    = ! empty( $options['all'] ) && ! empty( $definition['paginated'] );
		$cache_key  = Cache::key( $slug . ( $collect ? ':all' : '' ), $query, $company, $project );
		$this->meta = array(
			'endpoint' => $slug,
			'cached'   => false,
			'stale'    => false,
			'total'    => null,
		);

		if ( empty( $options['bypass_cache'] ) ) {
			$cached = Cache::get( $cache_key );

			if ( null !== $cached ) {
				$this->meta['cached'] = true;
				$this->meta['total']  = is_array( $cached ) ? count( $cached ) : null;

				return $cached;
			}
		}

		if ( $this->circuit_is_open() ) {
			return $this->stale_or_error(
				$cache_key,
				new \WP_Error(
					'procore_connect_circuit_open',
					__( 'Procore requests are paused after repeated failures. They will resume automatically.', 'procore-connect' )
				)
			);
		}

		$result = $collect
			? $this->collect_pages( $path, $query, $company, (int) ( $options['max_pages'] ?? self::MAX_PAGES ) )
			: $this->request( $path, $query, $company );

		if ( is_wp_error( $result ) ) {
			$this->record_failure();

			return $this->stale_or_error( $cache_key, $result );
		}

		$this->record_success();

		Cache::set( $cache_key, $result['body'], $ttl, $slug );

		$this->meta['total'] = $result['total'];

		return $result['body'];
	}

	/**
	 * Issue a single API request with retries.
	 *
	 * @param string               $path    API path beginning with a slash.
	 * @param array<string, mixed> $query   Query parameters.
	 * @param int                  $company Company identifier for the scope header.
	 * @return array{body: mixed, total: ?int, next: ?string}|\WP_Error Response, or an error.
	 */
	private function request( string $path, array $query, int $company ) {
		$url = Environment::api_host() . $path;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		return $this->send( $url, $company );
	}

	/**
	 * Walk `Link: rel="next"` headers to assemble a complete result set.
	 *
	 * @param string               $path      API path beginning with a slash.
	 * @param array<string, mixed> $query     Query parameters.
	 * @param int                  $company   Company identifier for the scope header.
	 * @param int                  $max_pages Hard page ceiling.
	 * @return array{body: mixed, total: ?int, next: ?string}|\WP_Error Combined response, or an error.
	 */
	private function collect_pages( string $path, array $query, int $company, int $max_pages ) {
		$max_pages = max( 1, min( self::MAX_PAGES, $max_pages ) );
		$response  = $this->request( $path, $query, $company );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows  = is_array( $response['body'] ) ? $response['body'] : array();
		$next  = $response['next'];
		$pages = 1;

		while ( null !== $next && $pages < $max_pages ) {
			$page = $this->send( $next, $company );

			if ( is_wp_error( $page ) ) {
				// Return what was gathered rather than discarding a partial set.
				Logger::warning(
					'Pagination stopped early.',
					array(
						'error' => $page->get_error_message(),
						'pages' => $pages,
					)
				);
				break;
			}

			if ( is_array( $page['body'] ) ) {
				$rows = array_merge( $rows, $page['body'] );
			}

			$next = $page['next'];
			++$pages;
		}//end while

		if ( null !== $next && $pages >= $max_pages ) {
			Logger::warning(
				'Pagination hit the page ceiling; the result set is truncated.',
				array(
					'pages' => $pages,
					'path'  => $path,
				)
			);
		}

		return array(
			'body'  => $rows,
			'total' => count( $rows ),
			'next'  => null,
		);
	}

	/**
	 * Perform an HTTP GET with retry and backoff.
	 *
	 * @param string $url     Absolute request URL.
	 * @param int    $company Company identifier for the scope header.
	 * @return array{body: mixed, total: ?int, next: ?string}|\WP_Error Response, or an error.
	 */
	private function send( string $url, int $company ) {
		$last_error = null;

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$token = $this->auth()->access_token();

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$headers = array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			);

			// Mandatory for /me, /companies and every multi-region request.
			if ( $company > 0 ) {
				$headers['Procore-Company-Id'] = (string) $company;
			}

			$args = array(
				'method'      => 'GET',
				'timeout'     => (int) Settings::get( 'request_timeout', 15 ),
				'sslverify'   => true,
				'redirection' => 3,
				'user-agent'  => sprintf( 'Procore Connect/%s; WordPress/%s; %s', PROCORE_CONNECT_VERSION, get_bloginfo( 'version' ), home_url( '/' ) ),
				'headers'     => $headers,
			);

			/**
			 * Filters the HTTP arguments for a Procore API request.
			 *
			 * @since 2.0.0
			 *
			 * @param array<string, mixed> $args Request arguments.
			 * @param string               $url  Request URL.
			 */
			$args = (array) apply_filters( 'procore_connect_request_args', $args, $url );

			$response = $this->dispatch( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;

				if ( $attempt < self::MAX_ATTEMPTS ) {
					$this->sleep( $this->backoff( $attempt ) );
					continue;
				}

				return $response;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );

			$this->record_rate_limit( $response );

			if ( 401 === $status && $attempt < self::MAX_ATTEMPTS ) {
				// The token was revoked or rotated server-side; drop it and retry once.
				TokenStore::clear();
				$last_error = new \WP_Error( 'procore_connect_unauthorized', __( 'Procore rejected the access token.', 'procore-connect' ) );
				continue;
			}

			if ( 429 === $status || 503 === $status ) {
				$wait = $this->retry_delay( $response, $attempt );

				if ( $attempt < self::MAX_ATTEMPTS ) {
					Logger::warning(
						'Procore throttled the request; backing off.',
						array(
							'status' => $status,
							'wait'   => $wait,
						)
					);
					$this->sleep( $wait );
					continue;
				}

				return new \WP_Error(
					'procore_connect_rate_limited',
					__( 'Procore is rate limiting this site. Cached data will be shown until the limit resets.', 'procore-connect' ),
					array( 'status' => $status )
				);
			}//end if

			return $this->parse( $response, $status );
		}//end for

		return $last_error instanceof \WP_Error
			? $last_error
			: new \WP_Error( 'procore_connect_request_failed', __( 'The Procore request could not be completed.', 'procore-connect' ) );
	}

	/**
	 * Send the request through the configured transport.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|\WP_Error Raw WordPress HTTP response.
	 */
	private function dispatch( string $url, array $args ) {
		if ( is_callable( $this->transport ) ) {
			return call_user_func( $this->transport, $url, $args );
		}

		return wp_remote_request( $url, $args );
	}

	/**
	 * Decode a successful response and extract pagination metadata.
	 *
	 * @param array<string, mixed> $response Raw WordPress HTTP response.
	 * @param int                  $status   HTTP status code.
	 * @return array{body: mixed, total: ?int, next: ?string}|\WP_Error Parsed response, or an error.
	 */
	private function parse( $response, int $status ) {
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = '' === $raw ? array() : json_decode( $raw, true );

		if ( $status >= 400 ) {
			$message = '';

			if ( is_array( $decoded ) ) {
				$message = (string) ( $decoded['message'] ?? $decoded['error'] ?? '' );

				if ( '' === $message && isset( $decoded['errors'] ) ) {
					$message = is_array( $decoded['errors'] ) ? implode( '; ', array_map( 'strval', $decoded['errors'] ) ) : (string) $decoded['errors'];
				}
			}

			if ( '' === $message ) {
				/* translators: %d: HTTP status code. */
				$message = sprintf( __( 'Procore returned HTTP %d.', 'procore-connect' ), $status );
			}

			Logger::error(
				'Procore API error.',
				array(
					'status'  => $status,
					'message' => $message,
				)
			);

			return new \WP_Error( 'procore_connect_api_error', $message, array( 'status' => $status ) );
		}//end if

		if ( null === $decoded && '' !== $raw ) {
			return new \WP_Error( 'procore_connect_bad_json', __( 'Procore returned a response that could not be decoded.', 'procore-connect' ) );
		}

		$total = wp_remote_retrieve_header( $response, 'total' );

		return array(
			'body'  => $decoded,
			'total' => ( '' === $total || null === $total ) ? null : (int) $total,
			'next'  => $this->next_link( $response ),
		);
	}

	/**
	 * Extract the `rel="next"` URL from a Link header.
	 *
	 * @param array<string, mixed> $response Raw WordPress HTTP response.
	 * @return string|null Next page URL, or null when this is the final page.
	 */
	private function next_link( $response ): ?string {
		$link = wp_remote_retrieve_header( $response, 'link' );

		if ( is_array( $link ) ) {
			$link = implode( ', ', $link );
		}

		if ( ! is_string( $link ) || '' === $link ) {
			return null;
		}

		if ( ! preg_match( '/<([^>]+)>\s*;\s*rel\s*=\s*"?next"?/i', $link, $matches ) ) {
			return null;
		}

		$url = esc_url_raw( trim( $matches[1] ), array( 'https' ) );

		if ( '' === $url ) {
			return null;
		}

		// Never follow a Link header off the configured API host.
		$host     = wp_parse_url( $url, PHP_URL_HOST );
		$expected = wp_parse_url( Environment::api_host(), PHP_URL_HOST );

		return ( $host && $host === $expected ) ? $url : null;
	}

	/**
	 * Persist the rate-limit headers for the admin Status screen.
	 *
	 * @param array<string, mixed> $response Raw WordPress HTTP response.
	 * @return void
	 */
	private function record_rate_limit( $response ): void {
		$limit     = wp_remote_retrieve_header( $response, 'x-rate-limit-limit' );
		$remaining = wp_remote_retrieve_header( $response, 'x-rate-limit-remaining' );
		$reset     = wp_remote_retrieve_header( $response, 'x-rate-limit-reset' );

		if ( '' === $limit && '' === $remaining ) {
			return;
		}

		update_option(
			self::RATE_LIMIT_OPTION,
			array(
				'limit'     => (int) $limit,
				'remaining' => (int) $remaining,
				'reset'     => (int) $reset,
				'updated'   => time(),
			),
			false
		);
	}

	/**
	 * The most recent rate-limit snapshot.
	 *
	 * @return array<string, int> Snapshot with `limit`, `remaining`, `reset` and `updated`.
	 */
	public static function rate_limit(): array {
		$stored = get_option( self::RATE_LIMIT_OPTION, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'limit'     => 0,
				'remaining' => 0,
				'reset'     => 0,
				'updated'   => 0,
			)
		);
	}

	/**
	 * Determine how long to wait before retrying a throttled request.
	 *
	 * @param array<string, mixed> $response Raw WordPress HTTP response.
	 * @param int                  $attempt  Attempt number, starting at 1.
	 * @return int Seconds to wait.
	 */
	private function retry_delay( $response, int $attempt ): int {
		$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );

		if ( $retry_after > 0 ) {
			return min( 10, $retry_after );
		}

		$reset = (int) wp_remote_retrieve_header( $response, 'x-rate-limit-reset' );

		if ( $reset > time() ) {
			return min( 10, $reset - time() );
		}

		return $this->backoff( $attempt );
	}

	/**
	 * Exponential backoff with jitter.
	 *
	 * @param int $attempt Attempt number, starting at 1.
	 * @return int Seconds to wait.
	 */
	private function backoff( int $attempt ): int {
		return min( 8, (int) pow( 2, $attempt ) ) + wp_rand( 0, 1 );
	}

	/**
	 * Pause execution between retries.
	 *
	 * @param int $seconds Seconds to sleep.
	 * @return void
	 */
	private function sleep( int $seconds ): void {
		if ( $seconds <= 0 ) {
			return;
		}

		/**
		 * Filters the retry pause. Return zero to disable sleeping, as tests do.
		 *
		 * @since 2.0.0
		 *
		 * @param int $seconds Seconds to sleep.
		 */
		$seconds = (int) apply_filters( 'procore_connect_retry_sleep', $seconds );

		if ( $seconds > 0 ) {
			sleep( min( 10, $seconds ) );
		}
	}

	/**
	 * Serve the stale cached copy when one exists, otherwise return the error.
	 *
	 * @param string    $cache_key Cache key.
	 * @param \WP_Error $error     Error to return when no fallback exists.
	 * @return mixed|\WP_Error Stale payload, or the supplied error.
	 */
	private function stale_or_error( string $cache_key, \WP_Error $error ) {
		$stale = Cache::get_stale( $cache_key );

		if ( null === $stale ) {
			return $error;
		}

		$this->meta['cached'] = true;
		$this->meta['stale']  = true;

		Logger::warning(
			'Serving a stale cached response after an API failure.',
			array( 'error' => $error->get_error_message() )
		);

		return $stale;
	}

	/**
	 * Whether the circuit breaker is currently open.
	 *
	 * @return bool True when requests are paused.
	 */
	public function circuit_is_open(): bool {
		$state = self::circuit_state();

		return $state['open_until'] > time();
	}

	/**
	 * Current circuit breaker state.
	 *
	 * @return array<string, int> State with `failures` and `open_until`.
	 */
	public static function circuit_state(): array {
		$stored = get_option( self::CIRCUIT_OPTION, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'failures'   => 0,
				'open_until' => 0,
			)
		);
	}

	/**
	 * Reset the circuit breaker.
	 *
	 * @return void
	 */
	public static function reset_circuit(): void {
		delete_option( self::CIRCUIT_OPTION );
	}

	/**
	 * Count a failed request towards the circuit breaker threshold.
	 *
	 * @return void
	 */
	private function record_failure(): void {
		$state = self::circuit_state();
		++$state['failures'];

		if ( $state['failures'] >= self::CIRCUIT_THRESHOLD ) {
			$state['open_until'] = time() + self::CIRCUIT_COOLDOWN;
			$state['failures']   = 0;

			Logger::error( 'Procore circuit breaker opened after repeated failures.' );
		}

		update_option( self::CIRCUIT_OPTION, $state, false );
	}

	/**
	 * Clear the failure counter after a successful request.
	 *
	 * @return void
	 */
	private function record_success(): void {
		$state = self::circuit_state();

		if ( 0 === $state['failures'] && 0 === $state['open_until'] ) {
			return;
		}

		self::reset_circuit();
	}
}
