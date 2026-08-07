<?php
/**
 * Renders a single Procore record.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Frontend\Shortcodes;

use ProcoreWP\Frontend\Renderer;
use ProcoreWP\Support\Format;

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
				'class'      => Format::classes( 'procorewp procorewp-record procorewp-' . str_replace( '_', '-', $this->endpoint ), (string) $atts['class'] ),
				'show_email' => ! empty( $atts['show_email'] ),
				'atts'       => $atts,
				'tag'        => $this->tag,
			)
		);
	}
}
