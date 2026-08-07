<?php
/**
 * WP-CLI commands.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Cli;

use ProcoreWP\Admin\ConnectionTester;
use ProcoreWP\Admin\Settings;
use ProcoreWP\Api\Cache;
use ProcoreWP\Api\Client;
use ProcoreWP\Api\Environment;
use ProcoreWP\Api\TokenStore;
use ProcoreWP\Support\Arr;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the Procore connection from the command line.
 */
final class Commands {

	/**
	 * Register the command namespace with WP-CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'procorewp', self::class );
	}

	/**
	 * Verify the Procore connection and report each check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp test
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {
		$report = ( new ConnectionTester() )->run();

		$rows = array();

		foreach ( $report['steps'] as $step ) {
			$rows[] = array(
				'check'  => $step['label'],
				'result' => $step['ok'] ? 'OK' : 'FAIL',
				'detail' => $step['message'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'check', 'result', 'detail' ) );

		if ( ! empty( $report['probes'] ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Endpoint permissions:' );
			\WP_CLI\Utils\format_items( 'table', $report['probes'], array( 'slug', 'status', 'message', 'permission' ) );
		}

		if ( $report['ok'] ) {
			\WP_CLI::success( $report['summary'] );

			return;
		}

		\WP_CLI::warning( $report['summary'] );
	}

	/**
	 * Purge cached Procore responses.
	 *
	 * ## OPTIONS
	 *
	 * [--group=<group>]
	 * : Only purge one endpoint group, e.g. projects.
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp cache-clear
	 *     wp procorewp cache-clear --group=rfis
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function cache_clear( array $args, array $assoc_args ): void {
		$group   = isset( $assoc_args['group'] ) ? sanitize_key( $assoc_args['group'] ) : '';
		$removed = Cache::flush( $group );

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of cache entries removed. */
				_n( 'Removed %d cached response.', 'Removed %d cached responses.', $removed, 'procorewp' ),
				$removed
			)
		);
	}

	/**
	 * Refresh cached project data for the default company.
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp cache-warm
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function cache_warm( array $args, array $assoc_args ): void {
		$company = Settings::default_company_id();

		if ( $company <= 0 ) {
			\WP_CLI::error( 'No default company is configured.' );
		}

		$projects = Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id'   => $company,
				'all'          => true,
				'bypass_cache' => true,
			)
		);

		if ( is_wp_error( $projects ) ) {
			\WP_CLI::error( $projects->get_error_message() );
		}

		\WP_CLI::success( sprintf( 'Cached %d projects.', is_array( $projects ) ? count( $projects ) : 0 ) );
	}

	/**
	 * List the projects visible to the configured credentials.
	 *
	 * ## OPTIONS
	 *
	 * [--company=<id>]
	 * : Company ID. Defaults to the configured company.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp projects --format=csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function projects( array $args, array $assoc_args ): void {
		$company = isset( $assoc_args['company'] ) ? absint( $assoc_args['company'] ) : Settings::default_company_id();

		$projects = Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id' => $company,
				'all'        => true,
			)
		);

		if ( is_wp_error( $projects ) ) {
			\WP_CLI::error( $projects->get_error_message() );
		}

		$rows = array();

		foreach ( (array) $projects as $project ) {
			$rows[] = array(
				'id'       => absint( Arr::get( $project, 'id', 0 ) ),
				'name'     => Arr::str( $project, 'name' ),
				'number'   => Arr::str( $project, 'project_number' ),
				'location' => \ProcoreWP\Support\Format::location( $project ),
				'active'   => Arr::get( $project, 'active' ) ? 'yes' : 'no',
			);
		}

		\WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table',
			$rows,
			array( 'id', 'name', 'number', 'location', 'active' )
		);
	}

	/**
	 * Discard the stored token so the next request re-authenticates.
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp reset-token
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function reset_token( array $args, array $assoc_args ): void {
		TokenStore::clear();
		Client::reset_circuit();

		\WP_CLI::success( 'Stored tokens cleared.' );
	}

	/**
	 * Print a summary of the current configuration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp procorewp doctor
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function doctor( array $args, array $assoc_args ): void {
		$limit   = Client::rate_limit();
		$circuit = Client::circuit_state();

		$rows = array(
			array(
				'setting' => 'Plugin version',
				'value'   => PROCOREWP_VERSION,
			),
			array(
				'setting' => 'Environment',
				'value'   => Environment::current(),
			),
			array(
				'setting' => 'API host',
				'value'   => Environment::api_host(),
			),
			array(
				'setting' => 'Login host',
				'value'   => Environment::login_host(),
			),
			array(
				'setting' => 'Redirect URI',
				'value'   => Environment::redirect_uri(),
			),
			array(
				'setting' => 'Auth mode',
				'value'   => (string) Settings::get( 'auth_mode', '' ),
			),
			array(
				'setting' => 'Client ID set',
				'value'   => '' !== Settings::client_id() ? 'yes' : 'no',
			),
			array(
				'setting' => 'Client secret set',
				'value'   => '' !== Settings::client_secret() ? 'yes' : 'no',
			),
			array(
				'setting' => 'Default company',
				'value'   => (string) Settings::default_company_id(),
			),
			array(
				'setting' => 'Cache enabled',
				'value'   => Cache::enabled() ? 'yes' : 'no',
			),
			array(
				'setting' => 'Cached responses',
				'value'   => (string) Cache::stats()['total'],
			),
			array(
				'setting' => 'Rate limit',
				'value'   => $limit['limit'] > 0 ? $limit['remaining'] . '/' . $limit['limit'] : 'unknown',
			),
			array(
				'setting' => 'Circuit breaker',
				'value'   => $circuit['open_until'] > time() ? 'open' : 'closed',
			),
		);

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'setting', 'value' ) );
	}
}
