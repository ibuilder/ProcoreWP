<?php
/**
 * Array helpers used when reading sparse Procore payloads.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Safe accessors for deeply nested, frequently incomplete API responses.
 *
 * Procore omits fields the authenticated account cannot see, so every read
 * against a response body has to tolerate missing keys.
 */
final class Arr {

	/**
	 * Read a value using dot notation, returning a default when absent.
	 *
	 * @param mixed  $subject Array or object to read from.
	 * @param string $path    Dot-delimited path, e.g. `project.address.city`.
	 * @param mixed  $default_value Value returned when the path is missing.
	 * @return mixed Located value or the default.
	 */
	public static function get( $subject, string $path, $default_value = null ) {
		if ( '' === $path ) {
			return $subject;
		}

		$segments = explode( '.', $path );
		$current  = $subject;

		foreach ( $segments as $segment ) {
			if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
				$current = $current[ $segment ];
				continue;
			}

			if ( is_object( $current ) && isset( $current->$segment ) ) {
				$current = $current->$segment;
				continue;
			}

			return $default_value;
		}

		return $current;
	}

	/**
	 * Read a value and coerce it to a display-ready string.
	 *
	 * @param mixed  $subject Array or object to read from.
	 * @param string $path    Dot-delimited path.
	 * @param string $default_value Value returned when the path is missing or empty.
	 * @return string Display string.
	 */
	public static function str( $subject, string $path, string $default_value = '' ): string {
		$value = self::get( $subject, $path );

		return self::stringify( $value, $default_value );
	}

	/**
	 * Convert an arbitrary API value into a display string.
	 *
	 * @param mixed  $value         Raw value.
	 * @param string $default_value Value returned when empty.
	 * @return string Display string.
	 */
	public static function stringify( $value, string $default_value = '' ): string {
		if ( null === $value || '' === $value ) {
			return $default_value;
		}

		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'connect-for-procore' ) : __( 'No', 'connect-for-procore' );
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) ) {
			// Common Procore shape: a related record exposed as an object with a label.
			foreach ( array( 'name', 'label', 'title', 'full_name', 'login' ) as $label_key ) {
				if ( isset( $value[ $label_key ] ) && is_scalar( $value[ $label_key ] ) ) {
					return (string) $value[ $label_key ];
				}
			}

			$parts = array();

			foreach ( $value as $item ) {
				$rendered = self::stringify( $item );

				if ( '' !== $rendered ) {
					$parts[] = $rendered;
				}
			}

			return empty( $parts ) ? $default_value : implode( ', ', $parts );
		}

		return $default_value;
	}

	/**
	 * Sort a list of associative rows by a column, tolerating missing keys.
	 *
	 * `array_multisort()` on `array_column()` output — the approach used by
	 * ProcoreWP 1.x — silently reorders rows when any record lacks the column.
	 *
	 * Records with no value for the column always sort last, in both
	 * directions, so reversing the order never promotes empty rows to the top.
	 *
	 * @param array<int, mixed> $rows      Rows to sort.
	 * @param string            $column    Column key, dot notation supported.
	 * @param string            $direction `asc` or `desc`.
	 * @return array<int, mixed> Sorted rows.
	 */
	public static function sort_by( array $rows, string $column, string $direction = 'asc' ): array {
		if ( '' === $column ) {
			return $rows;
		}

		$descending = 'desc' === strtolower( $direction );

		usort(
			$rows,
			static function ( $a, $b ) use ( $column, $descending ) {
				$left  = self::get( $a, $column );
				$right = self::get( $b, $column );

				$left_empty  = ( null === $left || '' === $left );
				$right_empty = ( null === $right || '' === $right );

				if ( $left_empty || $right_empty ) {
					if ( $left_empty && $right_empty ) {
						return 0;
					}

					return $left_empty ? 1 : -1;
				}

				if ( is_numeric( $left ) && is_numeric( $right ) ) {
					$result = ( (float) $left ) <=> ( (float) $right );
				} else {
					$result = strcasecmp( self::stringify( $left ), self::stringify( $right ) );
				}

				return $descending ? -$result : $result;
			}
		);

		return $rows;
	}

	/**
	 * Reduce each row to an allow-listed set of fields.
	 *
	 * @param array<int, mixed>  $rows   Rows to project.
	 * @param array<int, string> $fields Field paths to retain.
	 * @return array<int, array<string, mixed>> Projected rows.
	 */
	public static function pluck_fields( array $rows, array $fields ): array {
		if ( empty( $fields ) ) {
			return $rows;
		}

		$projected = array();

		foreach ( $rows as $row ) {
			$item = array();

			foreach ( $fields as $field ) {
				$item[ $field ] = self::get( $row, $field );
			}

			$projected[] = $item;
		}

		return $projected;
	}

	/**
	 * Determine whether a value looks like a list of records.
	 *
	 * @param mixed $value Value to test.
	 * @return bool True for a zero-indexed array.
	 */
	public static function is_list( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
