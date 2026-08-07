<?php
/**
 * Renders a list of Procore records.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Frontend\Shortcodes;

use ProcoreWP\Frontend\Renderer;
use ProcoreWP\Support\Arr;
use ProcoreWP\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Handles every shortcode that renders a collection of records.
 *
 * Filtering, ordering and limiting are applied after retrieval so they behave
 * identically across endpoints whose server-side query support varies.
 */
class CollectionShortcode extends AbstractShortcode {

	/**
	 * Build the rendered output.
	 *
	 * @param mixed                $data Response payload.
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Rendered markup.
	 */
	protected function output( $data, array $atts ): string {
		$rows = $this->extract_rows( $data );
		$rows = $this->filter_rows( $rows, $atts );

		$orderby = $this->orderby( $atts );

		if ( '' !== $orderby ) {
			$rows = Arr::sort_by( $rows, $orderby, $this->order( $atts ) );
		}

		$limit = absint( $atts['limit'] );

		if ( $limit > 0 && count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		if ( empty( $rows ) ) {
			return Renderer::notice( $this->empty_text( $atts ), 'empty' );
		}

		return Renderer::render(
			$this->template( $atts ),
			array(
				'rows'       => $rows,
				'columns'    => $this->resolve_columns( $atts ),
				'title'      => $this->title( $atts ),
				'class'      => Format::classes( 'procorewp procorewp-collection procorewp-' . str_replace( '_', '-', $this->endpoint ), (string) $atts['class'] ),
				'show_email' => ! empty( $atts['show_email'] ),
				'atts'       => $atts,
				'tag'        => $this->tag,
			)
		);
	}

	/**
	 * The field to sort on, honouring the ProcoreWP 1.x `sort_by` alias.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Field path, or an empty string for no sorting.
	 */
	protected function orderby( array $atts ): string {
		if ( '' !== (string) $atts['orderby'] ) {
			return (string) $atts['orderby'];
		}

		return (string) ( $atts['sort_by'] ?? '' );
	}

	/**
	 * The sort direction, honouring the ProcoreWP 1.x `sort_order` alias.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Either `asc` or `desc`.
	 */
	protected function order( array $atts ): string {
		$legacy = strtolower( (string) ( $atts['sort_order'] ?? '' ) );

		if ( 'desc' === $legacy ) {
			return 'desc';
		}

		if ( 'asc' === $legacy ) {
			return 'asc';
		}

		return (string) $atts['order'];
	}

	/**
	 * Column definitions, honouring the ProcoreWP 1.x `show_details` attribute.
	 *
	 * In 1.x, `show_details="false"` reduced the project list to ID and name.
	 * An explicit `false` is still respected so existing pages look the same;
	 * omitting it now shows the full column set, which is the more useful
	 * default for a new page.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return array<int, array<string, string>> Column definitions.
	 */
	protected function resolve_columns( array $atts ): array {
		$columns = parent::resolve_columns( $atts );

		if ( ! array_key_exists( 'show_details', $atts ) || $atts['show_details'] ) {
			return $columns;
		}

		if ( '' !== (string) $atts['columns'] || '' !== (string) $atts['fields'] ) {
			return $columns;
		}

		return array_values(
			array_filter(
				$columns,
				static function ( array $column ): bool {
					return in_array( (string) $column['key'], array( 'id', 'name' ), true );
				}
			)
		);
	}

	/**
	 * Normalise a response body into a list of records.
	 *
	 * Some Procore resources return a bare array, others wrap the list in a
	 * named key such as `data` or the resource name.
	 *
	 * @param mixed $data Response payload.
	 * @return array<int, mixed> Records.
	 */
	protected function extract_rows( $data ): array {
		if ( Arr::is_list( $data ) ) {
			return (array) $data;
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		foreach ( array( 'data', 'items', $this->endpoint ) as $key ) {
			if ( isset( $data[ $key ] ) && Arr::is_list( $data[ $key ] ) ) {
				return (array) $data[ $key ];
			}
		}

		// A single record returned where a list was expected.
		return array( $data );
	}

	/**
	 * Apply shortcode-level row filtering.
	 *
	 * @param array<int, mixed>    $rows Records.
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return array<int, mixed> Filtered records.
	 */
	protected function filter_rows( array $rows, array $atts ): array {
		if ( array_key_exists( 'active_only', $atts ) && $atts['active_only'] ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ): bool {
						$active = Arr::get( $row, 'active' );

						return null === $active ? true : (bool) $active;
					}
				)
			);
		}

		if ( ! empty( $atts['status'] ) ) {
			$wanted = strtolower( (string) $atts['status'] );

			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $wanted ): bool {
						return strtolower( Arr::str( $row, 'status' ) ) === $wanted;
					}
				)
			);
		}

		return $rows;
	}
}
