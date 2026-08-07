<?php
/**
 * Renders a single field from a Procore project.
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
 * Powers `[procore_project_data]`.
 *
 * The requested field is checked against an allow-list before it is read, so a
 * page author cannot surface arbitrary parts of a Procore payload — several of
 * which contain internal identifiers or personal data.
 */
final class FieldShortcode extends AbstractShortcode {

	/**
	 * Fields that may be surfaced.
	 *
	 * @return array<int, string> Allow-listed field paths.
	 */
	public static function allowed_fields(): array {
		$fields = array(
			'name',
			'display_name',
			'project_number',
			'description',
			'address',
			'city',
			'state_code',
			'zip',
			'country_code',
			'county',
			'latitude',
			'longitude',
			'phone',
			'time_zone',
			'stage',
			'active',
			'start_date',
			'completion_date',
			'actual_start_date',
			'projected_finish_date',
			'total_value',
			'estimated_value',
			'square_feet',
			'store_number',
			'department',
			'project_type.name',
			'office.name',
			'owners_project_id',
			'created_at',
			'updated_at',
		);

		/**
		 * Filters the fields `[procore_project_data]` may display.
		 *
		 * @since 2.0.0
		 *
		 * @param array<int, string> $fields Allow-listed field paths.
		 */
		return array_values( array_unique( (array) apply_filters( 'procorewp_allowed_project_fields', $fields ) ) );
	}

	/**
	 * Attribute defaults.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			array(
				'field'  => '',
				'label'  => '',
				'format' => '',
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
		$field = (string) $atts['field'];

		if ( '' === $field ) {
			return $this->error( new \WP_Error( 'procorewp_missing_field', __( 'The field attribute is required.', 'procorewp' ) ) );
		}

		if ( ! in_array( $field, self::allowed_fields(), true ) ) {
			return $this->error(
				new \WP_Error(
					'procorewp_field_not_allowed',
					sprintf(
						/* translators: %s: requested field name. */
						__( 'The field "%s" is not available for display. Add it with the procorewp_allowed_project_fields filter.', 'procorewp' ),
						$field
					)
				)
			);
		}

		$raw = Arr::get( $data, $field );

		if ( null === $raw || '' === $raw ) {
			return Renderer::notice( $this->empty_text( $atts ), 'empty' );
		}

		$value = $this->format_value( $raw, (string) $atts['format'], $field );

		return Renderer::render(
			$this->template( $atts ),
			array(
				'label' => '' !== $atts['label'] ? (string) $atts['label'] : Format::label( $field ),
				'value' => $value,
				'field' => $field,
				'class' => Format::classes( 'procorewp procorewp-field', (string) $atts['class'] ),
				'atts'  => $atts,
				'tag'   => $this->tag,
			)
		);
	}

	/**
	 * Format a raw value for display.
	 *
	 * @param mixed  $raw    Raw value.
	 * @param string $format Explicit format hint from the shortcode.
	 * @param string $field  Field name, used to infer a format.
	 * @return string Display value.
	 */
	private function format_value( $raw, string $format, string $field ): string {
		if ( '' === $format ) {
			if ( preg_match( '/(_date|_at)$/', $field ) ) {
				$format = 'date';
			} elseif ( preg_match( '/(value|budget|total|amount)$/', $field ) ) {
				$format = 'currency';
			}
		}

		switch ( $format ) {
			case 'date':
				return Format::date( $raw );

			case 'currency':
				return Format::currency( $raw );

			case 'number':
				return is_numeric( $raw ) ? number_format_i18n( (float) $raw ) : Arr::stringify( $raw );

			default:
				return Arr::stringify( $raw );
		}
	}

	/**
	 * The empty-state message for a missing field value.
	 *
	 * @param array<string, mixed> $atts Sanitized attributes.
	 * @return string Message.
	 */
	protected function empty_text( array $atts ): string {
		return '' !== $atts['empty_text']
			? (string) $atts['empty_text']
			: __( 'Not available.', 'procorewp' );
	}
}
