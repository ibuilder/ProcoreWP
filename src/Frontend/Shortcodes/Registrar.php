<?php
/**
 * Shortcode definitions and registration.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The single source of truth for every shortcode the plugin exposes.
 *
 * The admin reference screen, the Gutenberg blocks and the documentation
 * generator all read this registry, so the published documentation cannot drift
 * away from what the code actually accepts.
 *
 * Every tag from ProcoreWP 1.x is preserved verbatim, and the legacy `id`
 * attribute is still honoured as an alias for `project_id`, so existing pages
 * keep rendering after the upgrade.
 */
final class Registrar {

	/**
	 * Memoised definitions.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $definitions = null;

	/**
	 * Hook shortcode registration.
	 *
	 * Deferred to `init` because the definitions carry translated titles and
	 * descriptions. Building them on `plugins_loaded` would ask WordPress to
	 * load the text domain before `init`, which since 6.7 emits a
	 * `_load_textdomain_just_in_time` notice and silently drops translations.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register every shortcode with WordPress.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		foreach ( self::definitions() as $tag => $definition ) {
			$handler  = $definition['handler'];
			$instance = new $handler( $definition );

			add_shortcode( $tag, array( $instance, 'render' ) );
		}
	}

	/**
	 * All shortcode definitions, keyed by tag.
	 *
	 * @return array<string, array<string, mixed>> Definitions.
	 */
	public static function definitions(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$definitions = array(

			/* ---- Preserved from ProcoreWP 1.x ---- */

			'procore_project_list'   => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'projects',
				'template'    => 'collection',
				'title'       => __( 'Projects', 'connect-for-procore' ),
				'description' => __( 'A table of Procore projects for a company.', 'connect-for-procore' ),
				// show_details, sort_by and sort_order are ProcoreWP 1.x aliases,
				// still honoured so existing pages render the same way.
				'atts'        => array(
					'active_only'  => 'true',
					'show_details' => 'true',
					'sort_by'      => '',
					'sort_order'   => '',
				),
				'columns'     => array(
					array(
						'key'    => 'id',
						'label'  => __( 'ID', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'name',
						'label'  => __( 'Project', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'project_number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => '__location',
						'label'  => __( 'Location', 'connect-for-procore' ),
						'format' => 'location',
					),
					array(
						'key'    => 'active',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'status',
					),
				),
			),
			'procore_project'        => array(
				'handler'     => RecordShortcode::class,
				'endpoint'    => 'project',
				'template'    => 'record',
				'description' => __( 'Detail panel for a single Procore project.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'address',
						'label'  => __( 'Address', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => '__location',
						'label'  => __( 'Location', 'connect-for-procore' ),
						'format' => 'location',
					),
					array(
						'key'    => 'zip',
						'label'  => __( 'Postcode', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'project_number',
						'label'  => __( 'Project number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'stage',
						'label'  => __( 'Stage', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'start_date',
						'label'  => __( 'Start date', 'connect-for-procore' ),
						'format' => 'date',
					),
					array(
						'key'    => 'completion_date',
						'label'  => __( 'Completion date', 'connect-for-procore' ),
						'format' => 'date',
					),
					array(
						'key'    => 'active',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'status',
					),
				),
			),
			'procore_team'           => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'project_users',
				'template'    => 'collection',
				'title'       => __( 'Project team', 'connect-for-procore' ),
				'description' => __( 'Team members assigned to a project. Email addresses are hidden unless explicitly enabled.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'name',
						'label'  => __( 'Name', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'job_title',
						'label'  => __( 'Role', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'vendor.name',
						'label'  => __( 'Company', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'email_address',
						'label'  => __( 'Email', 'connect-for-procore' ),
						'format' => 'email',
					),
				),
			),
			'procore_featured_image' => array(
				'handler'     => ImageShortcode::class,
				'endpoint'    => 'project',
				'template'    => 'image',
				'description' => __( 'The logo or featured image for a project.', 'connect-for-procore' ),
			),
			'procore_drawings'       => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'drawing_areas',
				'template'    => 'collection',
				'title'       => __( 'Drawings', 'connect-for-procore' ),
				'description' => __( 'Drawing areas published for a project.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'name',
						'label'  => __( 'Name', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'description',
						'label'  => __( 'Description', 'connect-for-procore' ),
						'format' => 'text',
					),
				),
			),
			'procore_specifications' => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'specification_sections',
				'template'    => 'collection',
				'title'       => __( 'Specifications', 'connect-for-procore' ),
				'description' => __( 'Specification sections published for a project.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'description',
						'label'  => __( 'Description', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'revision',
						'label'  => __( 'Revision', 'connect-for-procore' ),
						'format' => 'text',
					),
				),
			),
			'procore_project_data'   => array(
				'handler'     => FieldShortcode::class,
				'endpoint'    => 'project',
				'template'    => 'field',
				'description' => __( 'A single allow-listed field from a project record.', 'connect-for-procore' ),
			),

			/* ---- Project management tools ---- */

			'procore_rfis'           => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'rfis',
				'template'    => 'collection',
				'title'       => __( 'RFIs', 'connect-for-procore' ),
				'description' => __( 'Requests for information raised on a project.', 'connect-for-procore' ),
				'atts'        => array( 'status' => '' ),
				'columns'     => array(
					array(
						'key'    => 'number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'subject',
						'label'  => __( 'Subject', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'due_date',
						'label'  => __( 'Due', 'connect-for-procore' ),
						'format' => 'date',
					),
				),
			),
			'procore_submittals'     => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'submittals',
				'template'    => 'collection',
				'title'       => __( 'Submittals', 'connect-for-procore' ),
				'description' => __( 'Submittals tracked on a project.', 'connect-for-procore' ),
				'atts'        => array( 'status' => '' ),
				'columns'     => array(
					array(
						'key'    => 'number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'title',
						'label'  => __( 'Title', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'due_date',
						'label'  => __( 'Due', 'connect-for-procore' ),
						'format' => 'date',
					),
				),
			),
			'procore_punch_list'     => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'punch_items',
				'template'    => 'collection',
				'title'       => __( 'Punch list', 'connect-for-procore' ),
				'description' => __( 'Outstanding punch list items for a project.', 'connect-for-procore' ),
				'atts'        => array( 'status' => '' ),
				'columns'     => array(
					array(
						'key'    => 'position',
						'label'  => __( 'Item', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'name',
						'label'  => __( 'Description', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'due_date',
						'label'  => __( 'Due', 'connect-for-procore' ),
						'format' => 'date',
					),
				),
			),
			'procore_observations'   => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'observations',
				'template'    => 'collection',
				'title'       => __( 'Observations', 'connect-for-procore' ),
				'description' => __( 'Quality and safety observations recorded on a project.', 'connect-for-procore' ),
				'atts'        => array( 'status' => '' ),
				'columns'     => array(
					array(
						'key'    => 'number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'name',
						'label'  => __( 'Observation', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'due_date',
						'label'  => __( 'Due', 'connect-for-procore' ),
						'format' => 'date',
					),
				),
			),
			'procore_daily_logs'     => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'daily_logs',
				'template'    => 'collection',
				'title'       => __( 'Daily logs', 'connect-for-procore' ),
				'description' => __( 'Daily construction report logs for a project.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'date',
						'label'  => __( 'Date', 'connect-for-procore' ),
						'format' => 'date',
					),
					array(
						'key'    => 'comments',
						'label'  => __( 'Notes', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
				),
			),
			'procore_change_orders'  => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'change_orders',
				'template'    => 'collection',
				'title'       => __( 'Change orders', 'connect-for-procore' ),
				'description' => __( 'Change order packages raised on a project.', 'connect-for-procore' ),
				'atts'        => array( 'status' => '' ),
				'columns'     => array(
					array(
						'key'    => 'number',
						'label'  => __( 'Number', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'title',
						'label'  => __( 'Title', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'status',
						'label'  => __( 'Status', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'grand_total',
						'label'  => __( 'Value', 'connect-for-procore' ),
						'format' => 'currency',
					),
				),
			),
			'procore_milestones'     => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'milestones',
				'template'    => 'collection',
				'title'       => __( 'Schedule', 'connect-for-procore' ),
				'description' => __( 'Schedule tasks and milestones for a project.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'name',
						'label'  => __( 'Task', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'start_date',
						'label'  => __( 'Start', 'connect-for-procore' ),
						'format' => 'date',
					),
					array(
						'key'    => 'finish_date',
						'label'  => __( 'Finish', 'connect-for-procore' ),
						'format' => 'date',
					),
					array(
						'key'    => 'percent_complete',
						'label'  => __( 'Complete', 'connect-for-procore' ),
						'format' => 'percent',
					),
				),
			),

			/* ---- Company and directory ---- */

			'procore_vendors'        => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'company_vendors',
				'template'    => 'collection',
				'title'       => __( 'Vendors', 'connect-for-procore' ),
				'description' => __( 'Companies in the Procore company directory.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'name',
						'label'  => __( 'Company', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => '__location',
						'label'  => __( 'Location', 'connect-for-procore' ),
						'format' => 'location',
					),
					array(
						'key'    => 'business_phone',
						'label'  => __( 'Phone', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'website',
						'label'  => __( 'Website', 'connect-for-procore' ),
						'format' => 'url',
					),
				),
			),
			'procore_offices'        => array(
				'handler'     => CollectionShortcode::class,
				'endpoint'    => 'offices',
				'template'    => 'collection',
				'title'       => __( 'Offices', 'connect-for-procore' ),
				'description' => __( 'Offices registered against the Procore company.', 'connect-for-procore' ),
				'columns'     => array(
					array(
						'key'    => 'name',
						'label'  => __( 'Office', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => 'address',
						'label'  => __( 'Address', 'connect-for-procore' ),
						'format' => 'text',
					),
					array(
						'key'    => '__location',
						'label'  => __( 'Location', 'connect-for-procore' ),
						'format' => 'location',
					),
					array(
						'key'    => 'phone',
						'label'  => __( 'Phone', 'connect-for-procore' ),
						'format' => 'text',
					),
				),
			),
			'procore_project_map'    => array(
				'handler'     => MapShortcode::class,
				'endpoint'    => 'projects',
				'template'    => 'map',
				'title'       => __( 'Project locations', 'connect-for-procore' ),
				'description' => __( 'Projects that have coordinates, as an accessible location list with geo microdata.', 'connect-for-procore' ),
			),

			/* ---- Generic ---- */

			'procore_data'           => array(
				'handler'     => DataShortcode::class,
				'endpoint'    => '',
				'template'    => 'collection',
				'description' => __( 'Render any endpoint published in the Procore endpoint registry.', 'connect-for-procore' ),
			),
		);

		/**
		 * Filters the shortcode registry.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array<string, mixed>> $definitions Shortcode definitions keyed by tag.
		 */
		$definitions = (array) apply_filters( 'procore_connect_shortcodes', $definitions );

		foreach ( $definitions as $tag => $definition ) {
			$definitions[ $tag ]['tag'] = $tag;
		}

		self::$definitions = $definitions;

		return self::$definitions;
	}

	/**
	 * Retrieve a single definition.
	 *
	 * @param string $tag Shortcode tag.
	 * @return array<string, mixed>|null Definition, or null when unregistered.
	 */
	public static function get( string $tag ): ?array {
		$definitions = self::definitions();

		return $definitions[ $tag ] ?? null;
	}
}
