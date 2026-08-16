<?php
/**
 * Generic allow-listed endpoint reader.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend\Shortcodes;

use ProcoreConnect\Api\Endpoints;
use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Powers `[procore_data]`, which renders any endpoint marked public in the
 * registry without needing a bespoke shortcode class.
 *
 * The `endpoint` attribute is resolved against the registry, so a page author
 * can only reach paths a developer has explicitly published.
 */
final class DataShortcode extends CollectionShortcode {

	/**
	 * Attribute defaults.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'endpoint' => '',
				'status'   => '',
			)
		);
	}

	/**
	 * Retrieve the data this shortcode renders.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return mixed|\WP_Error Response payload, or an error.
	 */
	protected function resolve( array $atts ) {
		$slug = (string) $atts['endpoint'];

		if ( '' === $slug ) {
			return new \WP_Error(
				'procore_connect_missing_endpoint',
				sprintf(
					/* translators: %s: comma-separated list of endpoint slugs. */
					__( 'The endpoint attribute is required. Available endpoints: %s', 'connect-for-procore' ),
					implode( ', ', array_keys( Endpoints::public_endpoints() ) )
				)
			);
		}

		if ( ! array_key_exists( $slug, Endpoints::public_endpoints() ) ) {
			return new \WP_Error(
				'procore_connect_endpoint_not_public',
				sprintf(
					/* translators: 1: requested endpoint slug, 2: comma-separated list of endpoint slugs. */
					__( 'The endpoint "%1$s" is not available to shortcodes. Available endpoints: %2$s', 'connect-for-procore' ),
					$slug,
					implode( ', ', array_keys( Endpoints::public_endpoints() ) )
				)
			);
		}

		$this->endpoint = $slug;

		$definition = Endpoints::get( $slug );

		if ( empty( $this->columns ) && null !== $definition ) {
			$this->columns = array_map(
				static function ( string $field ): array {
					return array(
						'key'    => $field,
						'label'  => Format::label( $field ),
						'format' => 'text',
					);
				},
				(array) $definition['fields']
			);
		}

		return parent::resolve( $atts );
	}
}
