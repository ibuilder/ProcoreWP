<?php
/**
 * Authenticated admin AJAX endpoints.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Admin;

use ProcoreWP\Api\Cache;
use ProcoreWP\Api\Client;
use ProcoreWP\Api\TokenStore;
use ProcoreWP\Support\Arr;
use ProcoreWP\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin screen's asynchronous actions.
 *
 * Every handler verifies both a nonce and the `manage_options` capability
 * before doing anything, which is the check ProcoreWP 1.x omitted on its
 * "Test Connection" button.
 */
final class Ajax {

	/**
	 * Nonce action shared by every handler.
	 */
	public const NONCE = 'procorewp_admin';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_procorewp_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_procorewp_clear_cache', array( $this, 'clear_cache' ) );
		add_action( 'wp_ajax_procorewp_companies', array( $this, 'companies' ) );
		add_action( 'wp_ajax_procorewp_projects', array( $this, 'projects' ) );
		add_action( 'wp_ajax_procorewp_disconnect', array( $this, 'disconnect' ) );
	}

	/**
	 * Run the connection diagnostic.
	 *
	 * @return void
	 */
	public function test_connection(): void {
		$this->authorise();

		wp_send_json_success( ( new ConnectionTester() )->run() );
	}

	/**
	 * Purge cached Procore responses.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->authorise();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorise() calls check_ajax_referer() immediately above.
		$group   = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';
		$removed = Cache::flush( $group );

		Client::reset_circuit();

		wp_send_json_success(
			array(
				'removed' => $removed,
				'message' => sprintf(
					/* translators: %d: number of cache entries removed. */
					_n( 'Removed %d cached response.', 'Removed %d cached responses.', $removed, 'procorewp' ),
					$removed
				),
				'stats'   => Cache::stats(),
			)
		);
	}

	/**
	 * List the companies available to the stored credentials.
	 *
	 * @return void
	 */
	public function companies(): void {
		$this->authorise();

		$companies = ( new ConnectionTester() )->companies();

		if ( is_wp_error( $companies ) ) {
			wp_send_json_error( array( 'message' => $companies->get_error_message() ) );
		}

		$options = array();

		foreach ( (array) $companies as $company ) {
			$options[] = array(
				'id'   => absint( Arr::get( $company, 'id', 0 ) ),
				'name' => Arr::str( $company, 'name' ),
			);
		}

		wp_send_json_success( array( 'companies' => $options ) );
	}

	/**
	 * List projects for a company, used to populate block and settings pickers.
	 *
	 * @return void
	 */
	public function projects(): void {
		$this->authorise();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorise() calls check_ajax_referer() immediately above.
		$company  = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : Settings::default_company_id();
		$projects = Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id' => $company,
				'all'        => true,
			)
		);

		if ( is_wp_error( $projects ) ) {
			wp_send_json_error( array( 'message' => $projects->get_error_message() ) );
		}

		$options = array();

		foreach ( (array) $projects as $project ) {
			$options[] = array(
				'id'     => absint( Arr::get( $project, 'id', 0 ) ),
				'name'   => Arr::str( $project, 'name' ),
				'active' => (bool) Arr::get( $project, 'active', true ),
			);
		}

		wp_send_json_success( array( 'projects' => $options ) );
	}

	/**
	 * Discard the stored tokens.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		$this->authorise();

		TokenStore::clear();
		Cache::flush();
		Client::reset_circuit();
		Logger::info( 'Procore connection reset from the admin screen.' );

		wp_send_json_success( array( 'message' => __( 'Disconnected from Procore.', 'procorewp' ) ) );
	}

	/**
	 * Verify the nonce and capability, terminating the request on failure.
	 *
	 * @return void
	 */
	private function authorise(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'procorewp' ) ), 403 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );
	}
}
