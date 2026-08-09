<?php
/**
 * Presentation helpers shared by templates and shortcodes.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Support;

use ProcoreConnect\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Formats Procore values for human display using the site's own conventions.
 */
final class Format {

	/**
	 * Stands in for a value Procore did not supply.
	 *
	 * An em dash reads as "no value here" in a table without leaving the cell
	 * looking broken. Because it is what an absent value renders as, anything
	 * deciding whether a cell carries information has to recognise it — see
	 * `is_blank()`.
	 *
	 * @var string
	 */
	public const PLACEHOLDER = '—';

	/**
	 * Whether a rendered cell carries no information.
	 *
	 * A cell is blank when it is empty or when it is nothing but the
	 * placeholder. Both mean Procore returned no value; the difference is only
	 * whether the column's format prints a dash for it.
	 *
	 * @param string $cell Output of `cell()`.
	 * @return bool True when the cell conveys nothing.
	 */
	public static function is_blank( string $cell ): bool {
		$trimmed = trim( $cell );

		return '' === $trimmed || self::PLACEHOLDER === $trimmed;
	}

	/**
	 * Format an ISO-8601 or `Y-m-d` date using the configured format.
	 *
	 * @param mixed  $value         Raw date value.
	 * @param string $default_value Returned when the value is empty or unparsable.
	 * @return string Formatted date.
	 */
	public static function date( $value, string $default_value = '' ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return $default_value;
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return $default_value;
		}

		$format = (string) Settings::get( 'date_format', '' );

		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'F j, Y' );
		}

		return wp_date( $format, $timestamp );
	}

	/**
	 * Format a monetary value using the configured currency symbol.
	 *
	 * @param mixed  $value         Raw amount.
	 * @param string $default_value Returned when the value is not numeric.
	 * @return string Formatted amount.
	 */
	public static function currency( $value, string $default_value = '' ): string {
		if ( ! is_numeric( $value ) ) {
			return $default_value;
		}

		$symbol = (string) Settings::get( 'currency_symbol', '$' );

		return $symbol . number_format_i18n( (float) $value, 2 );
	}

	/**
	 * Turn a snake_case API field name into a human label.
	 *
	 * @param string $field Field name.
	 * @return string Title-cased label.
	 */
	public static function label( string $field ): string {
		$field = str_replace( array( '_', '.' ), ' ', $field );

		return ucwords( trim( $field ) );
	}

	/**
	 * Render an email address subject to the site's privacy settings.
	 *
	 * Returns an empty string when email output is globally disabled, which is
	 * the default. Otherwise the address is obfuscated against harvesters.
	 *
	 * @param mixed $value        Raw email address.
	 * @param bool  $shortcode_ok Whether the calling shortcode opted in.
	 * @return string Safe email string, or an empty string when suppressed.
	 */
	public static function email( $value, bool $shortcode_ok ): string {
		if ( ! is_string( $value ) || '' === $value || ! is_email( $value ) ) {
			return '';
		}

		if ( ! $shortcode_ok || Settings::get( 'suppress_emails', true ) ) {
			return '';
		}

		return antispambot( $value );
	}

	/**
	 * Build a comma-separated location string from a record's address parts.
	 *
	 * @param mixed $record Project or vendor record.
	 * @return string Location string.
	 */
	public static function location( $record ): string {
		$parts = array();

		foreach ( array( 'city', 'state_code', 'country_code' ) as $key ) {
			$value = Arr::str( $record, $key );

			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Render one column of one record as an escaped HTML fragment.
	 *
	 * This is the only place templates obtain cell content, which keeps the
	 * escaping guarantee in a single auditable location.
	 *
	 * @param mixed                 $record     Record being rendered.
	 * @param array<string, string> $column     Column definition with `key` and `format`.
	 * @param bool                  $show_email Whether the shortcode opted in to email output.
	 * @return string Escaped HTML.
	 */
	public static function cell( $record, array $column, bool $show_email = false ): string {
		$key    = (string) ( $column['key'] ?? '' );
		$format = (string) ( $column['format'] ?? 'text' );

		if ( '__location' === $key ) {
			return esc_html( self::location( $record ) );
		}

		$raw = Arr::get( $record, $key );

		switch ( $format ) {
			case 'date':
				return esc_html( self::date( $raw, self::PLACEHOLDER ) );

			case 'currency':
				return esc_html( self::currency( $raw, self::PLACEHOLDER ) );

			case 'percent':
				return is_numeric( $raw )
					? esc_html( number_format_i18n( (float) $raw, 0 ) . '%' )
					: self::PLACEHOLDER;

			case 'status':
				$active = null === $raw ? null : (bool) $raw;

				if ( null === $active ) {
					return self::PLACEHOLDER;
				}

				return sprintf(
					'<span class="procore-connect-status procore-connect-status--%1$s">%2$s</span>',
					$active ? 'active' : 'inactive',
					esc_html( $active ? __( 'Active', 'procore-connect' ) : __( 'Inactive', 'procore-connect' ) )
				);

			case 'email':
				$email = self::email( $raw, $show_email );

				if ( '' === $email ) {
					return '';
				}

				return sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( 'mailto:' . $email ),
					esc_html( $email )
				);

			case 'url':
				$url = esc_url_raw( Arr::stringify( $raw ), array( 'http', 'https' ) );

				if ( '' === $url ) {
					return self::PLACEHOLDER;
				}

				$host = wp_parse_url( $url, PHP_URL_HOST );

				return sprintf(
					'<a href="%1$s" rel="nofollow noopener external">%2$s</a>',
					esc_url( $url ),
					esc_html( is_string( $host ) && '' !== $host ? $host : $url )
				);

			default:
				$value = Arr::stringify( $raw );

				return '' === $value ? self::PLACEHOLDER : esc_html( $value );
		}//end switch
	}

	/**
	 * Compose a CSS class attribute from a base class and user-supplied extras.
	 *
	 * @param string $base  Base class list.
	 * @param string $extra Additional classes from a shortcode attribute.
	 * @return string Sanitized class list.
	 */
	public static function classes( string $base, string $extra = '' ): string {
		$classes = preg_split( '/\s+/', $base . ' ' . $extra, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $classes ) ) {
			return $base;
		}

		$classes = array_map( 'sanitize_html_class', $classes );
		$classes = array_filter( array_unique( $classes ) );

		return implode( ' ', $classes );
	}
}
