<?php
/**
 * Read-only REST proxy for Procore data.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Rest;

use ProcoreWP\Admin\Settings;
use ProcoreWP\Api\Client;
use ProcoreWP\Api\Endpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes cached Procore data under `/wp-json/procorewp/v1/`.
 *
 * Themes and front-end JavaScript can read project data without the browser
 * ever seeing a Procore credential. The proxy is read-only, serves from the
 * same cache the shortcodes use, and can only reach endpoints marked public in
 * the registry — so publishing a route never widens what is reachable.
 */
final class Controller {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'procorewp/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the proxy routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/endpoints',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_endpoints' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/data/(?P<endpoint>[a-z0-9_]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_data' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'endpoint'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ): bool {
							return array_key_exists( (string) $value, Endpoints::public_endpoints() );
						},
					),
					'company_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'project_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'per_page'   => array(
						'type'              => 'integer',
						'default'           => 100,
						'minimum'           => 1,
						'maximum'           => 2000,
						'sanitize_callback' => 'absint',
					),
					'page'       => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * List the endpoints this proxy will serve.
	 *
	 * @return \WP_REST_Response Endpoint metadata.
	 */
	public function get_endpoints(): \WP_REST_Response {
		$endpoints = array();

		foreach ( Endpoints::public_endpoints() as $slug => $definition ) {
			$endpoints[] = array(
				'slug'   => $slug,
				'label'  => (string) $definition['label'],
				'scope'  => (string) $definition['scope'],
				'fields' => (array) $definition['fields'],
			);
		}

		return rest_ensure_response( array( 'endpoints' => $endpoints ) );
	}

	/**
	 * Serve a cached endpoint payload.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error Response payload, or an error.
	 */
	public function get_data( \WP_REST_Request $request ) {
		$client = Client::instance();

		$data = $client->fetch(
			(string) $request->get_param( 'endpoint' ),
			array( 'page' => (int) $request->get_param( 'page' ) ),
			array(
				'company_id' => (int) $request->get_param( 'company_id' ),
				'project_id' => (int) $request->get_param( 'project_id' ),
				'per_page'   => (int) $request->get_param( 'per_page' ),
			)
		);

		if ( is_wp_error( $data ) ) {
			$status = (int) ( $data->get_error_data()['status'] ?? 502 );

			return new \WP_Error(
				$data->get_error_code(),
				$this->public_message( $data ),
				array( 'status' => $status >= 400 && $status < 600 ? $status : 502 )
			);
		}

		$meta = $client->meta();

		$response = rest_ensure_response(
			array(
				'endpoint' => (string) $request->get_param( 'endpoint' ),
				'cached'   => (bool) $meta['cached'],
				'stale'    => (bool) $meta['stale'],
				'total'    => $meta['total'],
				'data'     => $data,
			)
		);

		$response->header( 'X-ProcoreWP-Cached', $meta['cached'] ? '1' : '0' );

		return $response;
	}

	/**
	 * Whether the current request may use the proxy.
	 *
	 * @return bool|\WP_Error True when permitted, or an error.
	 */
	public function check_permission() {
		if ( ! Settings::get( 'enable_rest', false ) ) {
			return new \WP_Error(
				'procorewp_rest_disabled',
				__( 'The ProcoreWP REST proxy is disabled.', 'procorewp' ),
				array( 'status' => 404 )
			);
		}

		$level = (string) Settings::get( 'rest_access', 'logged_in' );

		if ( 'public' === $level ) {
			return true;
		}

		if ( 'editor' === $level ) {
			return current_user_can( 'edit_posts' );
		}

		return is_user_logged_in();
	}

	/**
	 * Reduce an internal error to something safe to return over HTTP.
	 *
	 * Administrators receive the underlying detail; everyone else does not,
	 * because Procore error messages routinely name accounts and permissions.
	 *
	 * @param \WP_Error $error Internal error.
	 * @return string Message.
	 */
	private function public_message( \WP_Error $error ): string {
		if ( current_user_can( 'manage_options' ) ) {
			return $error->get_error_message();
		}

		return __( 'Project information is temporarily unavailable.', 'procorewp' );
	}
}
