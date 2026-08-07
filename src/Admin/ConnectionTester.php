<?php
/**
 * Step-by-step verification of the Procore connection.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Admin;

use ProcoreWP\Api\Client;
use ProcoreWP\Api\Endpoints;
use ProcoreWP\Api\Environment;
use ProcoreWP\Api\TokenStore;
use ProcoreWP\Support\Arr;
use ProcoreWP\Support\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Produces a diagnostic report rather than a pass/fail verdict.
 *
 * ProcoreWP 1.x reported only "connection successful" or "connection failed",
 * which is unhelpful: the common real-world failure is a valid token whose
 * service account lacks read permission on one particular tool. Each stage is
 * therefore reported separately, and every registered endpoint is probed so an
 * operator can see exactly which shortcodes their permissions will support.
 */
final class ConnectionTester {

	/**
	 * Run the full diagnostic sequence.
	 *
	 * @return array<string, mixed> Report with `steps`, `probes` and `summary`.
	 */
	public function run(): array {
		$steps = array();

		$steps[] = $this->check_prerequisites();

		$token   = $this->check_token();
		$steps[] = $token;

		if ( ! $token['ok'] ) {
			return $this->report( $steps, array() );
		}

		$identity = $this->check_identity();
		$steps[]  = $identity;

		$companies = $this->check_companies();
		$steps[]   = $companies;

		$company = $this->check_company_access();
		$steps[] = $company;

		$steps[] = $this->check_rate_limit();

		$probes = $company['ok'] ? $this->probe_endpoints() : array();

		return $this->report( $steps, $probes );
	}

	/**
	 * Confirm the plugin has what it needs before contacting Procore.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_prerequisites(): array {
		$issues = array();

		if ( '' === Settings::client_id() ) {
			$issues[] = __( 'No Client ID is set.', 'procorewp' );
		}

		if ( '' === Settings::client_secret() ) {
			$issues[] = __( 'No Client Secret is set.', 'procorewp' );
		}

		if ( ! Encryption::is_strong() ) {
			$issues[] = __( 'OpenSSL is unavailable, so credentials cannot be encrypted at rest. Define PROCOREWP_CLIENT_SECRET in wp-config.php instead.', 'procorewp' );
		}

		return $this->step(
			__( 'Configuration', 'procorewp' ),
			empty( $issues ),
			empty( $issues )
				? sprintf(
					/* translators: 1: environment label, 2: API host. */
					__( 'Ready. Environment: %1$s (%2$s).', 'procorewp' ),
					Environment::choices()[ Environment::current() ],
					Environment::api_host()
				)
				: implode( ' ', $issues )
		);
	}

	/**
	 * Obtain an access token.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_token(): array {
		$client = Client::instance();
		$auth   = $client->auth();

		if ( ! $auth->is_configured() ) {
			return $this->step( __( 'Access token', 'procorewp' ), false, __( 'Credentials are incomplete.', 'procorewp' ) );
		}

		$token = $auth->access_token();

		if ( is_wp_error( $token ) ) {
			return $this->step( __( 'Access token', 'procorewp' ), false, $token->get_error_message() );
		}

		$stored  = TokenStore::all();
		$expires = (int) $stored['expires_at'];

		return $this->step(
			__( 'Access token', 'procorewp' ),
			true,
			sprintf(
				/* translators: 1: grant type label, 2: human readable time difference. */
				__( 'Obtained via %1$s. Valid for another %2$s.', 'procorewp' ),
				$auth->label(),
				human_time_diff( time(), max( $expires, time() ) )
			)
		);
	}

	/**
	 * Read the authenticated account.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_identity(): array {
		$me = Client::instance()->fetch( 'me', array(), array( 'bypass_cache' => true ) );

		if ( is_wp_error( $me ) ) {
			return $this->step( __( 'Authenticated account', 'procorewp' ), false, $me->get_error_message() );
		}

		$name  = Arr::str( $me, 'name' );
		$login = Arr::str( $me, 'login' );

		return $this->step(
			__( 'Authenticated account', 'procorewp' ),
			true,
			'' !== $name || '' !== $login
				? sprintf(
					/* translators: 1: account display name, 2: account login. */
					__( 'Connected as %1$s (%2$s).', 'procorewp' ),
					'' !== $name ? $name : __( 'service account', 'procorewp' ),
					'' !== $login ? $login : __( 'no login', 'procorewp' )
				)
				: __( 'Connected.', 'procorewp' )
		);
	}

	/**
	 * List the companies the credentials can reach.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_companies(): array {
		$companies = $this->companies();

		if ( is_wp_error( $companies ) ) {
			return $this->step( __( 'Company access', 'procorewp' ), false, $companies->get_error_message() );
		}

		if ( empty( $companies ) ) {
			return $this->step(
				__( 'Company access', 'procorewp' ),
				false,
				__( 'Procore returned no companies. Ask a company administrator to install the app and grant it access.', 'procorewp' )
			);
		}

		$names = array();

		foreach ( array_slice( $companies, 0, 10 ) as $company ) {
			$names[] = sprintf( '%s (%d)', Arr::str( $company, 'name' ), absint( Arr::get( $company, 'id', 0 ) ) );
		}

		return $this->step(
			__( 'Company access', 'procorewp' ),
			true,
			sprintf(
				/* translators: 1: number of companies, 2: comma-separated company names. */
				_n( '%1$d company available: %2$s', '%1$d companies available: %2$s', count( $companies ), 'procorewp' ),
				count( $companies ),
				implode( ', ', $names )
			)
		);
	}

	/**
	 * Confirm the configured default company is usable.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_company_access(): array {
		$company = Settings::default_company_id();

		if ( $company <= 0 ) {
			return $this->step(
				__( 'Default company', 'procorewp' ),
				false,
				__( 'No default company is set. Choose one below, or pass company_id in every shortcode.', 'procorewp' )
			);
		}

		$projects = Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id'   => $company,
				'bypass_cache' => true,
				'per_page'     => 1,
			)
		);

		if ( is_wp_error( $projects ) ) {
			return $this->step( __( 'Default company', 'procorewp' ), false, $projects->get_error_message() );
		}

		$total = Client::instance()->meta()['total'];

		return $this->step(
			__( 'Default company', 'procorewp' ),
			true,
			null === $total
				? sprintf(
					/* translators: %d: company ID. */
					__( 'Projects are readable for company %d.', 'procorewp' ),
					$company
				)
				: sprintf(
					/* translators: 1: number of projects, 2: company ID. */
					_n( '%1$d project visible in company %2$d.', '%1$d projects visible in company %2$d.', (int) $total, 'procorewp' ),
					(int) $total,
					$company
				)
		);
	}

	/**
	 * Report the remaining rate-limit headroom.
	 *
	 * @return array<string, mixed> Step result.
	 */
	private function check_rate_limit(): array {
		$limit = Client::rate_limit();

		if ( 0 === $limit['limit'] ) {
			return $this->step( __( 'Rate limit', 'procorewp' ), true, __( 'Procore has not reported a rate limit yet.', 'procorewp' ) );
		}

		$healthy = $limit['limit'] < 1 || ( $limit['remaining'] / max( 1, $limit['limit'] ) ) > 0.1;

		return $this->step(
			__( 'Rate limit', 'procorewp' ),
			$healthy,
			sprintf(
				/* translators: 1: remaining requests, 2: total allowed requests, 3: human readable time until reset. */
				__( '%1$d of %2$d requests remaining; resets in %3$s.', 'procorewp' ),
				$limit['remaining'],
				$limit['limit'],
				human_time_diff( time(), max( $limit['reset'], time() ) )
			)
		);
	}

	/**
	 * Probe every registered endpoint to reveal which tool permissions apply.
	 *
	 * @return array<int, array<string, mixed>> Probe results.
	 */
	private function probe_endpoints(): array {
		$company = Settings::default_company_id();
		$project = $this->sample_project_id( $company );
		$results = array();

		foreach ( Endpoints::all() as $slug => $definition ) {
			if ( in_array( $slug, array( 'me', 'companies' ), true ) ) {
				continue;
			}

			if ( Endpoints::SCOPE_PROJECT === $definition['scope'] && $project <= 0 ) {
				$results[] = array(
					'slug'       => $slug,
					'label'      => (string) $definition['label'],
					'permission' => (string) $definition['permission'],
					'status'     => 'skipped',
					'message'    => __( 'No project was available to test against.', 'procorewp' ),
				);
				continue;
			}

			$response = Client::instance()->fetch(
				$slug,
				array(),
				array(
					'company_id'   => $company,
					'project_id'   => $project,
					'per_page'     => 1,
					'bypass_cache' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$results[] = array(
					'slug'       => $slug,
					'label'      => (string) $definition['label'],
					'permission' => (string) $definition['permission'],
					'status'     => 'failed',
					'message'    => $response->get_error_message(),
				);
				continue;
			}

			$results[] = array(
				'slug'       => $slug,
				'label'      => (string) $definition['label'],
				'permission' => (string) $definition['permission'],
				'status'     => 'ok',
				'message'    => __( 'Readable.', 'procorewp' ),
			);
		}//end foreach

		return $results;
	}

	/**
	 * Find a project identifier to probe project-scoped endpoints against.
	 *
	 * @param int $company Company identifier.
	 * @return int Project identifier, or zero when none is available.
	 */
	private function sample_project_id( int $company ): int {
		$configured = Settings::default_project_id();

		if ( $configured > 0 ) {
			return $configured;
		}

		$projects = Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id' => $company,
				'per_page'   => 1,
			)
		);

		if ( is_wp_error( $projects ) || ! is_array( $projects ) || empty( $projects ) ) {
			return 0;
		}

		return absint( Arr::get( reset( $projects ), 'id', 0 ) );
	}

	/**
	 * Retrieve the companies available to the current credentials.
	 *
	 * @return array<int, mixed>|\WP_Error Companies, or an error.
	 */
	public function companies() {
		$companies = Client::instance()->fetch( 'companies', array(), array( 'bypass_cache' => true ) );

		if ( is_wp_error( $companies ) ) {
			return $companies;
		}

		return is_array( $companies ) ? $companies : array();
	}

	/**
	 * Build a step result.
	 *
	 * @param string $label   Step name.
	 * @param bool   $ok      Whether the step succeeded.
	 * @param string $message Detail message.
	 * @return array<string, mixed> Step result.
	 */
	private function step( string $label, bool $ok, string $message ): array {
		return array(
			'label'   => $label,
			'ok'      => $ok,
			'message' => $message,
		);
	}

	/**
	 * Assemble the final report.
	 *
	 * @param array<int, array<string, mixed>> $steps  Step results.
	 * @param array<int, array<string, mixed>> $probes Endpoint probe results.
	 * @return array<string, mixed> Report.
	 */
	private function report( array $steps, array $probes ): array {
		$failed = 0;

		foreach ( $steps as $step ) {
			if ( ! $step['ok'] ) {
				++$failed;
			}
		}

		return array(
			'steps'   => $steps,
			'probes'  => $probes,
			'ok'      => 0 === $failed,
			'summary' => 0 === $failed
				? __( 'Everything checked out.', 'procorewp' )
				: sprintf(
					/* translators: %d: number of failed checks. */
					_n( '%d check needs attention.', '%d checks need attention.', $failed, 'procorewp' ),
					$failed
				),
		);
	}
}
