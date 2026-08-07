<?php
/**
 * Renders a project's featured image.
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
 * Powers `[procore_featured_image]`.
 */
final class ImageShortcode extends AbstractShortcode {

	/**
	 * Record keys checked, in order, for a usable image URL.
	 *
	 * @var array<int, string>
	 */
	private const IMAGE_KEYS = array( 'logo_url', 'photo_url', 'image_url', 'thumbnail_url', 'logo.url' );

	/**
	 * Attribute defaults.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'width'  => 300,
				'height' => '',
				'alt'    => '',
				'lazy'   => 'true',
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
		$url = '';

		foreach ( self::IMAGE_KEYS as $key ) {
			$candidate = Arr::str( $data, $key );

			if ( '' !== $candidate ) {
				$url = $candidate;
				break;
			}
		}

		$url = esc_url_raw( $url, array( 'https' ) );

		if ( '' === $url ) {
			return Renderer::notice( $this->empty_text( $atts ), 'empty' );
		}

		$alt = '' !== $atts['alt']
			? (string) $atts['alt']
			: sprintf(
				/* translators: %s: project name. */
				__( '%s project image', 'procorewp' ),
				Arr::str( $data, 'name', __( 'Procore', 'procorewp' ) )
			);

		return Renderer::render(
			$this->template( $atts ),
			array(
				'url'    => $url,
				'alt'    => $alt,
				'width'  => absint( $atts['width'] ),
				'height' => absint( $atts['height'] ),
				'lazy'   => in_array( strtolower( (string) $atts['lazy'] ), array( 'true', '1', 'yes', 'on' ), true ),
				'class'  => Format::classes( 'procorewp procorewp-image', (string) $atts['class'] ),
				'atts'   => $atts,
				'tag'    => $this->tag,
			)
		);
	}

	/**
	 * The empty-state message when no image is available.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Message.
	 */
	protected function empty_text( array $atts ): string {
		return '' !== $atts['empty_text']
			? (string) $atts['empty_text']
			: __( 'No project image is available.', 'procorewp' );
	}
}
