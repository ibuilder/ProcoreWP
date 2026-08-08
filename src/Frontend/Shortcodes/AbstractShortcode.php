<?php
/**
 * Shared behaviour for every Procore Connect shortcode.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend\Shortcodes;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Cache;
use ProcoreConnect\Api\Client;
use ProcoreConnect\Api\Endpoints;
use ProcoreConnect\Frontend\Assets;
use ProcoreConnect\Frontend\Renderer;
use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Base class handling attribute sanitization, data retrieval and error output.
 *
 * Concrete shortcodes supply an endpoint slug, a template and a column map;
 * everything else — validation, caching, escaping and failure handling — is
 * uniform, which is what keeps the escaping guarantees auditable.
 */
abstract class AbstractShortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	protected $tag;

	/**
	 * Endpoint slug from the registry.
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * Template name used to render the result.
	 *
	 * @var string
	 */
	protected $template;

	/**
	 * Column definitions used by list templates.
	 *
	 * @var array<int, array<string, string>>
	 */
	protected $columns = array();

	/**
	 * Additional shortcode-specific attribute defaults.
	 *
	 * @var array<string, mixed>
	 */
	protected $extra_atts = array();

	/**
	 * Heading rendered above the output when `title` is not overridden.
	 *
	 * @var string
	 */
	protected $default_title = '';

	/**
	 * Construct the shortcode from a registry definition.
	 *
	 * @param array<string, mixed> $definition Shortcode definition.
	 */
	public function __construct( array $definition ) {
		$this->tag           = (string) ( $definition['tag'] ?? '' );
		$this->endpoint      = (string) ( $definition['endpoint'] ?? '' );
		$this->template      = (string) ( $definition['template'] ?? 'collection' );
		$this->columns       = (array) ( $definition['columns'] ?? array() );
		$this->extra_atts    = (array) ( $definition['atts'] ?? array() );
		$this->default_title = (string) ( $definition['title'] ?? '' );
	}

	/**
	 * The shortcode tag.
	 *
	 * @return string Tag.
	 */
	public function tag(): string {
		return $this->tag;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, mixed>|string $atts    Raw shortcode attributes.
	 * @param string|null                 $content Enclosed content, unused.
	 * @return string Rendered markup.
	 */
	public function render( $atts, ?string $content = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by add_shortcode().
		$atts = shortcode_atts( $this->defaults(), (array) $atts, $this->tag );
		$atts = $this->sanitize_atts( $atts );

		Assets::mark_needed();

		$result = $this->resolve( $atts );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->output( $result, $atts );
	}

	/**
	 * Attribute defaults, merging the common vocabulary with per-shortcode extras.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public function defaults(): array {
		return array_merge(
			array(
				'company_id' => '',
				'project_id' => '',
				'id'         => '',
				'limit'      => 0,
				'page'       => 1,
				'orderby'    => '',
				'order'      => 'asc',
				'columns'    => '',
				'fields'     => '',
				'template'   => '',
				'class'      => '',
				'title'      => '',
				'cache'      => '',
				'empty_text' => '',
				'show_email' => 'false',
				'all'        => 'false',
			),
			$this->extra_atts
		);
	}

	/**
	 * Sanitize every attribute before it is used.
	 *
	 * @param array<string, mixed> $atts Raw attributes.
	 * @return array<string, mixed> Sanitized attributes.
	 */
	protected function sanitize_atts( array $atts ): array {
		$clean = array();

		foreach ( $atts as $key => $value ) {
			switch ( $key ) {
				case 'company_id':
				case 'project_id':
				case 'id':
				case 'limit':
				case 'page':
				case 'cache':
				case 'width':
				case 'height':
				case 'max_pages':
					$clean[ $key ] = '' === $value ? '' : absint( $value );
					break;

				case 'order':
					$clean[ $key ] = 'desc' === strtolower( (string) $value ) ? 'desc' : 'asc';
					break;

				case 'orderby':
				case 'endpoint':
					$clean[ $key ] = preg_replace( '/[^a-z0-9_.\-]/i', '', (string) $value ) ?? '';
					break;

				case 'columns':
				case 'fields':
					$clean[ $key ] = preg_replace( '/[^a-z0-9_.,\-]/i', '', (string) $value ) ?? '';
					break;

				case 'template':
					$clean[ $key ] = preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ?? '';
					break;

				case 'class':
					$clean[ $key ] = Format::classes( '', (string) $value );
					break;

				case 'show_email':
				case 'all':
				case 'show_details':
				case 'active_only':
					$clean[ $key ] = in_array( strtolower( (string) $value ), array( 'true', '1', 'yes', 'on' ), true );
					break;

				default:
					$clean[ $key ] = sanitize_text_field( (string) $value );
					break;
			}//end switch
		}//end foreach

		return $clean;
	}

	/**
	 * Retrieve the data this shortcode renders.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return mixed|\WP_Error Response payload, or an error.
	 */
	protected function resolve( array $atts ) {
		if ( '' === $this->endpoint || ! Endpoints::exists( $this->endpoint ) ) {
			return new \WP_Error( 'procore_connect_unknown_endpoint', __( 'This shortcode is not bound to a known Procore endpoint.', 'procore-connect' ) );
		}

		return Client::instance()->fetch( $this->endpoint, $this->query_args( $atts ), $this->fetch_options( $atts ) );
	}

	/**
	 * Query parameters sent with the request.
	 *
	 * Pagination is deliberately not added here. It is passed through
	 * `fetch_options()` so the client can drop it for endpoints the registry
	 * marks as unpaginated — sending `page` to a single-record resource such as
	 * `/projects/{id}` is meaningless and needlessly varies the cache key.
	 *
	 * The parameter is retained because this is an extension point: subclasses
	 * override it to add endpoint-specific filters derived from the attributes.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return array<string, mixed> Query parameters.
	 */
	protected function query_args( array $atts ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Extension point for subclasses.
		return array();
	}

	/**
	 * Client behaviour options derived from the attributes.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return array<string, mixed> Options.
	 */
	protected function fetch_options( array $atts ): array {
		$options = array(
			'company_id' => $this->company_id( $atts ),
			'project_id' => $this->project_id( $atts ),
			'all'        => ! empty( $atts['all'] ),
			'page'       => max( 1, (int) $atts['page'] ),
		);

		if ( '' !== $atts['cache'] && $atts['cache'] > 0 ) {
			$options['ttl'] = Cache::clamp_ttl( (int) $atts['cache'] );
		}

		if ( ! empty( $atts['limit'] ) ) {
			$options['per_page'] = min( 2000, max( 1, (int) $atts['limit'] ) );
		}

		return $options;
	}

	/**
	 * Resolve the company identifier for this render.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return int Company identifier.
	 */
	protected function company_id( array $atts ): int {
		$explicit = absint( $atts['company_id'] );

		return $explicit > 0 ? $explicit : Settings::default_company_id();
	}

	/**
	 * Resolve the project identifier for this render.
	 *
	 * The legacy `id` attribute is accepted as an alias so pages written for
	 * ProcoreWP 1.x continue to work unchanged.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return int Project identifier.
	 */
	protected function project_id( array $atts ): int {
		$explicit = absint( $atts['project_id'] );

		if ( $explicit <= 0 ) {
			$explicit = absint( $atts['id'] );
		}

		return $explicit > 0 ? $explicit : Settings::default_project_id();
	}

	/**
	 * Column definitions, honouring a `columns` attribute override.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return array<int, array<string, string>> Column definitions.
	 */
	protected function resolve_columns( array $atts ): array {
		$list      = '' !== (string) $atts['columns'] ? (string) $atts['columns'] : (string) $atts['fields'];
		$requested = array_filter( array_map( 'trim', explode( ',', $list ) ) );

		if ( empty( $requested ) ) {
			return $this->columns;
		}

		$indexed = array();

		foreach ( $this->columns as $column ) {
			$indexed[ $column['key'] ] = $column;
		}

		$resolved = array();

		foreach ( $requested as $key ) {
			$resolved[] = $indexed[ $key ] ?? array(
				'key'    => $key,
				'label'  => Format::label( $key ),
				'format' => 'text',
			);
		}

		return $resolved;
	}

	/**
	 * Build the rendered output.
	 *
	 * @param mixed                $data Response payload.
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Rendered markup.
	 */
	abstract protected function output( $data, array $atts ): string;

	/**
	 * The template name for this render.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Template name.
	 */
	protected function template( array $atts ): string {
		return '' !== $atts['template'] ? (string) $atts['template'] : $this->template;
	}

	/**
	 * Convert an API failure into safe front-end output.
	 *
	 * Administrators see the underlying message; everyone else sees a neutral
	 * notice, because Procore errors routinely name accounts and permissions.
	 *
	 * @param \WP_Error $error Failure.
	 * @return string Rendered markup.
	 */
	protected function error( \WP_Error $error ): string {
		if ( current_user_can( 'manage_options' ) ) {
			return Renderer::notice(
				sprintf(
					/* translators: %s: error message from the Procore API. */
					__( 'Procore Connect (visible to administrators only): %s', 'procore-connect' ),
					$error->get_error_message()
				),
				'error'
			);
		}

		return Renderer::notice( __( 'Project information is temporarily unavailable.', 'procore-connect' ), 'error' );
	}

	/**
	 * The heading rendered above the output.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Heading text, or an empty string to omit it.
	 */
	protected function title( array $atts ): string {
		if ( isset( $atts['title'] ) && '' !== $atts['title'] ) {
			return '-' === $atts['title'] ? '' : (string) $atts['title'];
		}

		return $this->default_title;
	}

	/**
	 * The message shown when the endpoint returns no records.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Message.
	 */
	protected function empty_text( array $atts ): string {
		return '' !== $atts['empty_text']
			? (string) $atts['empty_text']
			: __( 'No records were found.', 'procore-connect' );
	}
}
