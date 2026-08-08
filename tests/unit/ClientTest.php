<?php
/**
 * Transport behaviour: headers, pagination, throttling and fallbacks.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Cache;
use ProcoreConnect\Api\Client;

/**
 * Covers the failure modes that made ProcoreWP 1.x unusable in production.
 */
final class ClientTest extends TestCase {

	/**
	 * Every request must carry the mandatory company scope header.
	 *
	 * ProcoreWP 1.x sent `?company_id=` instead, which most endpoints reject.
	 *
	 * @return void
	 */
	public function test_sends_procore_company_id_header(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array( array( 'id' => 1 ) ) ) ), $calls );

		$client->fetch( 'projects', array(), array( 'company_id' => 4242 ) );

		$this->assertSame( '4242', $calls[0]['args']['headers']['Procore-Company-Id'] );
		$this->assertSame( 'Bearer test-token', $calls[0]['args']['headers']['Authorization'] );
	}

	/**
	 * Requests must go to the API host, never the login host.
	 *
	 * @return void
	 */
	public function test_requests_target_the_api_host(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array() ) ), $calls );

		$client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertStringStartsWith( 'https://api.procore.com/rest/', $calls[0]['url'] );
	}

	/**
	 * A timeout and an identifying user agent must always be set.
	 *
	 * @return void
	 */
	public function test_sets_timeout_and_user_agent(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array() ) ), $calls );

		$client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertSame( 15, $calls[0]['args']['timeout'] );
		$this->assertTrue( $calls[0]['args']['sslverify'] );
		$this->assertStringContainsString( 'Procore Connect/2.0.0', $calls[0]['args']['user-agent'] );
	}

	/**
	 * A single-record endpoint must not receive pagination parameters.
	 *
	 * `page` and `per_page` are meaningless on `/projects/{id}`, and including
	 * them also varies the cache key for what is the same request.
	 *
	 * @return void
	 */
	public function test_unpaginated_endpoints_get_no_pagination_params(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array( 'id' => 123 ) ) ), $calls );

		$client->fetch(
			'project',
			array( 'page' => 3 ),
			array(
				'company_id' => 1,
				'project_id' => 123,
				'per_page'   => 50,
				'page'       => 3,
			)
		);

		$this->assertSame( 'https://api.procore.com/rest/v1.0/projects/123', $calls[0]['url'] );
		$this->assertStringNotContainsString( 'page=', $calls[0]['url'] );
	}

	/**
	 * A paginated endpoint must still honour an explicitly requested page.
	 *
	 * @return void
	 */
	public function test_paginated_endpoints_honour_the_requested_page(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array() ) ), $calls );

		$client->fetch(
			'rfis',
			array(),
			array(
				'company_id' => 1,
				'project_id' => 123,
				'page'       => 4,
				'per_page'   => 25,
			)
		);

		$this->assertStringContainsString( 'page=4', $calls[0]['url'] );
		$this->assertStringContainsString( 'per_page=25', $calls[0]['url'] );
	}

	/**
	 * `all` must follow the Link header rather than guessing page numbers.
	 *
	 * @return void
	 */
	public function test_follows_link_header_for_pagination(): void {
		$calls  = array();
		$client = $this->client_returning(
			array(
				$this->response(
					array( array( 'id' => 1 ), array( 'id' => 2 ) ),
					200,
					array( 'Link' => '<https://api.procore.com/rest/v1.1/projects?page=2>; rel="next"' )
				),
				$this->response( array( array( 'id' => 3 ) ) ),
			),
			$calls
		);

		$rows = $client->fetch(
			'projects',
			array(),
			array(
				'company_id' => 1,
				'all'        => true,
			)
		);

		$this->assertCount( 3, $rows );
		$this->assertCount( 2, $calls );
		$this->assertSame( array( 1, 2, 3 ), array_column( $rows, 'id' ) );
	}

	/**
	 * A Link header pointing off-host must never be followed.
	 *
	 * @return void
	 */
	public function test_ignores_link_header_on_a_foreign_host(): void {
		$calls  = array();
		$client = $this->client_returning(
			array(
				$this->response(
					array( array( 'id' => 1 ) ),
					200,
					array( 'Link' => '<https://attacker.example/rest/v1.1/projects?page=2>; rel="next"' )
				),
			),
			$calls
		);

		$rows = $client->fetch(
			'projects',
			array(),
			array(
				'company_id' => 1,
				'all'        => true,
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertCount( 1, $calls );
	}

	/**
	 * A 429 must be retried after backoff rather than surfaced immediately.
	 *
	 * @return void
	 */
	public function test_retries_after_a_rate_limit_response(): void {
		$calls  = array();
		$client = $this->client_returning(
			array(
				$this->response( array( 'message' => 'slow down' ), 429, array( 'Retry-After' => '1' ) ),
				$this->response( array( array( 'id' => 7 ) ) ),
			),
			$calls
		);

		$rows = $client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertCount( 2, $calls );
		$this->assertSame( 7, $rows[0]['id'] );
	}

	/**
	 * Rate-limit headers must be recorded for the Status screen.
	 *
	 * @return void
	 */
	public function test_records_rate_limit_headers(): void {
		$client = $this->client_returning(
			array(
				$this->response(
					array(),
					200,
					array(
						'X-Rate-Limit-Limit'     => '3600',
						'X-Rate-Limit-Remaining' => '3211',
						'X-Rate-Limit-Reset'     => '1893456000',
					)
				),
			)
		);

		$client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$limit = Client::rate_limit();

		$this->assertSame( 3600, $limit['limit'] );
		$this->assertSame( 3211, $limit['remaining'] );
	}

	/**
	 * A cached response must be served without a second HTTP call.
	 *
	 * @return void
	 */
	public function test_serves_from_cache_on_the_second_call(): void {
		$calls  = array();
		$client = $this->client_returning( array( $this->response( array( array( 'id' => 5 ) ) ) ), $calls );

		$client->fetch( 'projects', array(), array( 'company_id' => 1 ) );
		$second = $client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertCount( 1, $calls );
		$this->assertSame( 5, $second[0]['id'] );
		$this->assertTrue( $client->meta()['cached'] );
	}

	/**
	 * A Procore outage must serve the last good payload, not an error.
	 *
	 * @return void
	 */
	public function test_falls_back_to_stale_cache_on_failure(): void {
		$client = $this->client_returning( array( $this->response( array( array( 'id' => 9 ) ) ) ) );
		$client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		// Expire the fresh entry but leave the stale copy in place.
		foreach ( array_keys( $GLOBALS['procore_connect_test_transients'] ) as $key ) {
			if ( 0 === strpos( (string) $key, 'procore_connect_c_' ) ) {
				delete_transient( (string) $key );
			}
		}

		$client->set_transport(
			static function () {
				return new \WP_Error( 'down', 'Procore is unreachable.' );
			}
		);

		$result = $client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 9, $result[0]['id'] );
		$this->assertTrue( $client->meta()['stale'] );
	}

	/**
	 * Without a stale copy, a hard failure must surface as an error.
	 *
	 * @return void
	 */
	public function test_returns_an_error_when_no_fallback_exists(): void {
		$client = $this->client_returning( array( $this->response( array( 'message' => 'Forbidden' ), 403 ) ) );

		$result = $client->fetch( 'projects', array(), array( 'company_id' => 1 ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'Forbidden', $result->get_error_message() );
	}

	/**
	 * A zero identifier must fall back to the site default, not fail.
	 *
	 * The REST proxy always sends `company_id` and `project_id` as integers
	 * that default to 0, so treating a supplied zero as an explicit choice
	 * made the proxy ignore the configured defaults entirely.
	 *
	 * @return void
	 */
	public function test_zero_identifiers_fall_back_to_site_defaults(): void {
		Settings::set( 'default_company_id', 4242 );
		Settings::set( 'default_project_id', 77 );

		$calls  = array();
		$client = $this->client_returning( array( $this->response( array() ) ), $calls );

		$result = $client->fetch(
			'rfis',
			array(),
			array(
				'company_id' => 0,
				'project_id' => 0,
			)
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( '4242', $calls[0]['args']['headers']['Procore-Company-Id'] );
		$this->assertStringContainsString( '/projects/77/rfis', $calls[0]['url'] );
	}

	/**
	 * A company-scoped endpoint must refuse to run without a company.
	 *
	 * @return void
	 */
	public function test_requires_a_company_for_scoped_endpoints(): void {
		$client = $this->client_returning( array() );

		$result = $client->fetch( 'projects' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_missing_company', $result->get_error_code() );
	}

	/**
	 * A project-scoped endpoint must refuse to run without a project.
	 *
	 * @return void
	 */
	public function test_requires_a_project_for_project_endpoints(): void {
		$client = $this->client_returning( array() );

		$result = $client->fetch( 'rfis', array(), array( 'company_id' => 1 ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_missing_project', $result->get_error_code() );
	}

	/**
	 * Repeated failures must open the circuit and stop further requests.
	 *
	 * @return void
	 */
	public function test_circuit_breaker_opens_after_repeated_failures(): void {
		$client = new Client();
		$client->set_auth( new FakeAuth() );
		$client->set_transport(
			static function () {
				return new \WP_Error( 'down', 'Procore is unreachable.' );
			}
		);
		Client::set_instance( $client );

		for ( $i = 0; $i < 5; $i++ ) {
			$client->fetch( 'projects', array( 'nonce' => $i ), array( 'company_id' => 1 ) );
		}

		$this->assertTrue( $client->circuit_is_open() );
	}

	/**
	 * A shortcode must not be able to cache below the configured floor.
	 *
	 * @return void
	 */
	public function test_cache_floor_is_enforced(): void {
		Settings::set( 'cache_floor', 600 );

		$this->assertSame( 600, Cache::clamp_ttl( 5 ) );
		$this->assertSame( 900, Cache::clamp_ttl( 900 ) );
	}
}
