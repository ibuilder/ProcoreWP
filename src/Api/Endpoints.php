<?php
/**
 * Registry of the Procore REST endpoints this plugin is allowed to call.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Allow-list of reachable Procore endpoints.
 *
 * Every request the plugin makes resolves through this registry. Nothing
 * outside it is callable, which is what makes the generic `[procore_data]`
 * shortcode and the public REST proxy safe to expose.
 *
 * Procore versions each resource independently and several tools moved paths
 * between releases, so the registry is filterable: a site can correct or extend
 * a path via `procorewp_endpoints` without forking the plugin. The admin
 * Connection screen probes each registered endpoint so an operator can see at a
 * glance which ones their credentials and tool permissions actually reach.
 */
final class Endpoints {

	/**
	 * Endpoint scope: no company or project context in the request.
	 */
	public const SCOPE_NONE = 'none';

	/**
	 * Endpoint scope: requires a company identifier.
	 */
	public const SCOPE_COMPANY = 'company';

	/**
	 * Endpoint scope: requires a project identifier.
	 */
	public const SCOPE_PROJECT = 'project';

	/**
	 * Memoised registry.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $registry = null;

	/**
	 * Retrieve the full endpoint registry.
	 *
	 * @return array<string, array<string, mixed>> Registry keyed by endpoint slug.
	 */
	public static function all(): array {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$registry = array(

			/* Identity and company scope. */

			'me'                     => array(
				'label'      => __( 'Authenticated account', 'procorewp' ),
				'path'       => '/rest/v1.0/me',
				'scope'      => self::SCOPE_NONE,
				'paginated'  => false,
				'ttl'        => 300,
				'permission' => __( 'None (identity endpoint)', 'procorewp' ),
				'fields'     => array( 'id', 'login', 'name' ),
				'public'     => false,
			),
			'companies'              => array(
				'label'      => __( 'Companies', 'procorewp' ),
				'path'       => '/rest/v1.0/companies',
				'scope'      => self::SCOPE_NONE,
				'paginated'  => true,
				'ttl'        => 3600,
				'permission' => __( 'Company Directory: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'is_active' ),
				'public'     => false,
			),
			'offices'                => array(
				'label'      => __( 'Company offices', 'procorewp' ),
				'path'       => '/rest/v1.0/companies/{company_id}/offices',
				'scope'      => self::SCOPE_COMPANY,
				'paginated'  => true,
				'ttl'        => 21600,
				'permission' => __( 'Company Admin: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'address', 'city', 'state_code', 'zip', 'phone' ),
				'public'     => true,
			),
			'company_vendors'        => array(
				'label'      => __( 'Company directory (vendors)', 'procorewp' ),
				'path'       => '/rest/v1.0/vendors',
				'scope'      => self::SCOPE_COMPANY,
				'query'      => array( 'company_id' ),
				'paginated'  => true,
				'ttl'        => 21600,
				'permission' => __( 'Company Directory: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'city', 'state_code', 'business_phone', 'website' ),
				'public'     => true,
			),

			/* Projects. */

			'projects'               => array(
				'label'      => __( 'Projects', 'procorewp' ),
				'path'       => '/rest/v1.1/projects',
				'scope'      => self::SCOPE_COMPANY,
				'query'      => array( 'company_id' ),
				'paginated'  => true,
				'ttl'        => 900,
				'permission' => __( 'Company Admin / Project Directory: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'project_number', 'city', 'state_code', 'active', 'stage' ),
				'public'     => true,
			),
			'project'                => array(
				'label'      => __( 'Project detail', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => false,
				'ttl'        => 900,
				'permission' => __( 'Project Admin: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'address', 'city', 'state_code', 'zip', 'start_date', 'completion_date', 'active' ),
				'public'     => true,
			),
			'project_users'          => array(
				'label'      => __( 'Project team', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}/users',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => true,
				'ttl'        => 1800,
				'permission' => __( 'Project Directory: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'job_title', 'vendor', 'email_address' ),
				'public'     => true,
			),
			'project_vendors'        => array(
				'label'      => __( 'Project vendors', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}/vendors',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => true,
				'ttl'        => 1800,
				'permission' => __( 'Project Directory: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'city', 'state_code', 'business_phone' ),
				'public'     => true,
			),

			/* Documents. */

			'drawing_areas'          => array(
				'label'      => __( 'Drawing areas', 'procorewp' ),
				'path'       => '/rest/v1.0/drawing_areas',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 21600,
				'permission' => __( 'Drawings: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'description' ),
				'public'     => true,
			),
			'drawing_revisions'      => array(
				'label'      => __( 'Drawing revisions', 'procorewp' ),
				'path'       => '/rest/v1.0/drawing_revisions',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 21600,
				'permission' => __( 'Drawings: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'title', 'revision_number', 'received_date' ),
				'public'     => true,
			),
			'specification_sections' => array(
				'label'      => __( 'Specification sections', 'procorewp' ),
				'path'       => '/rest/v1.0/specification_sections',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 21600,
				'permission' => __( 'Specifications: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'description', 'revision' ),
				'public'     => true,
			),

			/* Project management tools. */

			'rfis'                   => array(
				'label'      => __( 'RFIs', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}/rfis',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => true,
				'ttl'        => 600,
				'permission' => __( 'RFIs: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'subject', 'status', 'due_date' ),
				'public'     => true,
			),
			'submittals'             => array(
				'label'      => __( 'Submittals', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}/submittals',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => true,
				'ttl'        => 600,
				'permission' => __( 'Submittals: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'title', 'status', 'due_date' ),
				'public'     => true,
			),
			'punch_items'            => array(
				'label'      => __( 'Punch list', 'procorewp' ),
				'path'       => '/rest/v1.0/punch_items',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 600,
				'permission' => __( 'Punch List: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'position', 'name', 'status', 'due_date' ),
				'public'     => true,
			),
			'observations'           => array(
				'label'      => __( 'Observations', 'procorewp' ),
				'path'       => '/rest/v1.0/observations/items',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 600,
				'permission' => __( 'Observations: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'name', 'status', 'due_date' ),
				'public'     => true,
			),
			'daily_logs'             => array(
				'label'      => __( 'Daily construction report logs', 'procorewp' ),
				'path'       => '/rest/v1.0/daily_construction_report_logs',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 1800,
				'permission' => __( 'Daily Log: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'date', 'comments', 'status' ),
				'public'     => true,
			),
			'change_orders'          => array(
				'label'      => __( 'Change order packages', 'procorewp' ),
				'path'       => '/rest/v1.0/change_order_packages',
				'scope'      => self::SCOPE_PROJECT,
				'query'      => array( 'project_id' ),
				'paginated'  => true,
				'ttl'        => 1800,
				'permission' => __( 'Change Orders: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'number', 'title', 'status', 'grand_total' ),
				'public'     => true,
			),
			'milestones'             => array(
				'label'      => __( 'Schedule tasks', 'procorewp' ),
				'path'       => '/rest/v1.0/projects/{project_id}/schedule/tasks',
				'scope'      => self::SCOPE_PROJECT,
				'paginated'  => true,
				'ttl'        => 1800,
				'permission' => __( 'Schedule: Read Only', 'procorewp' ),
				'fields'     => array( 'id', 'name', 'start_date', 'finish_date', 'percent_complete' ),
				'public'     => true,
			),
		);

		/**
		 * Filters the registry of callable Procore endpoints.
		 *
		 * Use this to correct a path after a Procore resource version bump, or
		 * to register an additional endpoint for `[procore_data]`.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array<string, mixed>> $registry Endpoint definitions keyed by slug.
		 */
		$registry = (array) apply_filters( 'procorewp_endpoints', $registry );

		self::$registry = array_map( array( self::class, 'normalise' ), $registry );

		return self::$registry;
	}

	/**
	 * Retrieve a single endpoint definition.
	 *
	 * @param string $slug Endpoint slug.
	 * @return array<string, mixed>|null Definition, or null when not registered.
	 */
	public static function get( string $slug ): ?array {
		$all = self::all();

		return $all[ $slug ] ?? null;
	}

	/**
	 * Whether an endpoint slug is registered.
	 *
	 * @param string $slug Endpoint slug.
	 * @return bool True when registered.
	 */
	public static function exists( string $slug ): bool {
		return null !== self::get( $slug );
	}

	/**
	 * Endpoints that may be reached by `[procore_data]` and the REST proxy.
	 *
	 * @return array<string, array<string, mixed>> Public endpoint definitions.
	 */
	public static function public_endpoints(): array {
		return array_filter(
			self::all(),
			static function ( array $definition ): bool {
				return ! empty( $definition['public'] );
			}
		);
	}

	/**
	 * Build the request path for an endpoint, substituting context identifiers.
	 *
	 * @param string $slug    Endpoint slug.
	 * @param int    $company Company identifier.
	 * @param int    $project Project identifier.
	 * @return string|\WP_Error Resolved path, or an error when context is missing.
	 */
	public static function path( string $slug, int $company = 0, int $project = 0 ) {
		$definition = self::get( $slug );

		if ( null === $definition ) {
			return new \WP_Error(
				'procorewp_unknown_endpoint',
				/* translators: %s: endpoint slug. */
				sprintf( __( 'Unknown Procore endpoint: %s', 'procorewp' ), $slug )
			);
		}

		$path = (string) $definition['path'];

		if ( false !== strpos( $path, '{company_id}' ) ) {
			if ( $company <= 0 ) {
				return new \WP_Error( 'procorewp_missing_company', __( 'A Procore company ID is required for this request.', 'procorewp' ) );
			}

			$path = str_replace( '{company_id}', (string) $company, $path );
		}

		if ( false !== strpos( $path, '{project_id}' ) ) {
			if ( $project <= 0 ) {
				return new \WP_Error( 'procorewp_missing_project', __( 'A Procore project ID is required for this request.', 'procorewp' ) );
			}

			$path = str_replace( '{project_id}', (string) $project, $path );
		}

		if ( self::SCOPE_PROJECT === $definition['scope'] && $project <= 0 ) {
			return new \WP_Error( 'procorewp_missing_project', __( 'A Procore project ID is required for this request.', 'procorewp' ) );
		}

		return $path;
	}

	/**
	 * Query arguments an endpoint requires in addition to caller-supplied ones.
	 *
	 * @param string $slug    Endpoint slug.
	 * @param int    $company Company identifier.
	 * @param int    $project Project identifier.
	 * @return array<string, int> Query arguments.
	 */
	public static function context_query( string $slug, int $company = 0, int $project = 0 ): array {
		$definition = self::get( $slug );

		if ( null === $definition ) {
			return array();
		}

		$query = array();

		foreach ( (array) $definition['query'] as $key ) {
			if ( 'company_id' === $key && $company > 0 ) {
				$query['company_id'] = $company;
			}

			if ( 'project_id' === $key && $project > 0 ) {
				$query['project_id'] = $project;
			}
		}

		return $query;
	}

	/**
	 * Apply defaults to a registry entry.
	 *
	 * @param array<string, mixed> $definition Raw definition.
	 * @return array<string, mixed> Definition with defaults applied.
	 */
	private static function normalise( array $definition ): array {
		return wp_parse_args(
			$definition,
			array(
				'label'      => '',
				'path'       => '',
				'scope'      => self::SCOPE_NONE,
				'query'      => array(),
				'paginated'  => false,
				'ttl'        => 900,
				'permission' => '',
				'fields'     => array(),
				'public'     => false,
			)
		);
	}
}
