<?php
/**
 * Endpoint allow-list behaviour.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Api\Endpoints;

/**
 * The registry is the boundary that makes `[procore_data]` and the REST proxy
 * safe, so its refusals matter as much as its successes.
 */
final class EndpointsTest extends TestCase {

	/**
	 * An unregistered slug must be rejected.
	 *
	 * @return void
	 */
	public function test_unknown_endpoints_are_rejected(): void {
		$this->assertFalse( Endpoints::exists( 'company_admin_users' ) );

		$path = Endpoints::path( 'company_admin_users' );

		$this->assertTrue( is_wp_error( $path ) );
		$this->assertSame( 'procore_connect_unknown_endpoint', $path->get_error_code() );
	}

	/**
	 * Identity endpoints must stay out of reach of shortcodes and the proxy.
	 *
	 * @return void
	 */
	public function test_identity_endpoints_are_not_public(): void {
		$public = Endpoints::public_endpoints();

		$this->assertArrayNotHasKey( 'me', $public );
		$this->assertArrayNotHasKey( 'companies', $public );
		$this->assertArrayHasKey( 'projects', $public );
	}

	/**
	 * Context identifiers must be interpolated into the path.
	 *
	 * @return void
	 */
	public function test_path_interpolates_identifiers(): void {
		$this->assertSame( '/rest/v1.0/projects/88/rfis', Endpoints::path( 'rfis', 1, 88 ) );
		$this->assertSame( '/rest/v1.0/companies/4/offices', Endpoints::path( 'offices', 4 ) );
	}

	/**
	 * A path placeholder must never be left unfilled.
	 *
	 * @return void
	 */
	public function test_path_refuses_missing_context(): void {
		$this->assertTrue( is_wp_error( Endpoints::path( 'rfis', 1, 0 ) ) );
		$this->assertTrue( is_wp_error( Endpoints::path( 'offices', 0 ) ) );
	}

	/**
	 * Endpoints that scope by query string must receive those parameters.
	 *
	 * @return void
	 */
	public function test_context_query_is_added_where_required(): void {
		$this->assertSame( array( 'company_id' => 7 ), Endpoints::context_query( 'projects', 7 ) );
		$this->assertSame( array( 'project_id' => 12 ), Endpoints::context_query( 'punch_items', 7, 12 ) );
		$this->assertSame( array(), Endpoints::context_query( 'rfis', 7, 12 ) );
	}

	/**
	 * Every registered endpoint must declare the fields it can render.
	 *
	 * @return void
	 */
	public function test_registry_entries_are_complete(): void {
		foreach ( Endpoints::all() as $slug => $definition ) {
			$this->assertNotSame( '', $definition['path'], $slug . ' has no path' );
			$this->assertNotSame( '', $definition['label'], $slug . ' has no label' );
			$this->assertGreaterThan( 0, $definition['ttl'], $slug . ' has no cache lifetime' );
			$this->assertIsArray( $definition['fields'], $slug . ' has no fields' );
			$this->assertStringStartsWith( '/rest/', $definition['path'], $slug . ' is not a REST path' );
		}
	}
}
