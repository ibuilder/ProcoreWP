<?php
/**
 * Renders projects with coordinates as an accessible location list.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend\Shortcodes;

use ProcoreConnect\Frontend\Renderer;
use ProcoreConnect\Support\Arr;
use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Powers `[procore_project_map]`.
 *
 * No third-party mapping script is loaded: bundling one would send visitor IP
 * addresses to an external service without consent and would fail the
 * wordpress.org rule against remotely hosted assets. Instead each project is
 * emitted with machine-readable geo microdata plus a link to the visitor's own
 * map provider, and the coordinates are exposed on the container so a theme can
 * attach whichever mapping library the site already licenses.
 */
final class MapShortcode extends CollectionShortcode {

	/**
	 * Attribute defaults.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'active_only' => 'true',
				'link'        => 'true',
			)
		);
	}

	/**
	 * Build the rendered output.
	 *
	 * @param mixed                $data Response payload.
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Rendered markup.
	 */
	protected function output( $data, array $atts ): string {
		$rows   = $this->filter_rows( $this->extract_rows( $data ), $atts );
		$points = array();

		foreach ( $rows as $row ) {
			$latitude  = Arr::get( $row, 'latitude' );
			$longitude = Arr::get( $row, 'longitude' );

			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				continue;
			}

			$points[] = array(
				'id'        => absint( Arr::get( $row, 'id', 0 ) ),
				'name'      => Arr::str( $row, 'name' ),
				'location'  => Format::location( $row ),
				'latitude'  => round( (float) $latitude, 6 ),
				'longitude' => round( (float) $longitude, 6 ),
			);
		}

		$limit = absint( $atts['limit'] );

		if ( $limit > 0 && count( $points ) > $limit ) {
			$points = array_slice( $points, 0, $limit );
		}

		if ( empty( $points ) ) {
			return Renderer::notice( $this->empty_text( $atts ), 'empty' );
		}

		return Renderer::render(
			$this->template( $atts ),
			array(
				'points' => $points,
				'title'  => $this->title( $atts ),
				'link'   => in_array( strtolower( (string) $atts['link'] ), array( 'true', '1', 'yes', 'on' ), true ),
				'class'  => Format::classes( 'procore-connect procore-connect-map', (string) $atts['class'] ),
				'atts'   => $atts,
				'tag'    => $this->tag,
			)
		);
	}

	/**
	 * The empty-state message when no project has coordinates.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Message.
	 */
	protected function empty_text( array $atts ): string {
		return '' !== $atts['empty_text']
			? (string) $atts['empty_text']
			: __( 'No project locations are available.', 'connect-for-procore' );
	}
}
