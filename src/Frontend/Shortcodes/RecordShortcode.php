<?php
/**
 * Renders a single Procore record.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend\Shortcodes;

use ProcoreConnect\Frontend\Renderer;
use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Handles shortcodes that render one record as a labelled detail list.
 */
class RecordShortcode extends AbstractShortcode {

	/**
	 * Build the rendered output.
	 *
	 * @param mixed                $data Response payload.
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Rendered markup.
	 */
	protected function output( $data, array $atts ): string {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return Renderer::notice( $this->empty_text( $atts ), 'empty' );
		}

		return Renderer::render(
			$this->template( $atts ),
			array(
				'record'     => $data,
				'columns'    => $this->resolve_columns( $atts ),
				'title'      => '' !== $this->title( $atts ) ? $this->title( $atts ) : (string) ( $data['name'] ?? '' ),
				'class'      => Format::classes( 'procore-connect procore-connect-record procore-connect-' . str_replace( '_', '-', $this->endpoint ), (string) $atts['class'] ),
				'show_email' => ! empty( $atts['show_email'] ),
				'atts'       => $atts,
				'tag'        => $this->tag,
			)
		);
	}
}
