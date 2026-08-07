<?php
/**
 * At-rest encryption for stored Procore credentials and tokens.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts short secrets using AES-256-GCM.
 *
 * The key is derived from the site's own WordPress salts, so a database dump
 * without `wp-config.php` does not disclose credentials. If OpenSSL is not
 * available the payload is stored base64-encoded and `is_strong()` reports
 * false, which the admin screen surfaces as a warning.
 */
final class Encryption {

	/**
	 * Cipher used for all payloads.
	 */
	private const CIPHER = 'aes-256-gcm';

	/**
	 * Marker prefix identifying an encrypted payload.
	 */
	private const PREFIX = 'pwp2:';

	/**
	 * Marker prefix identifying an unencrypted fallback payload.
	 */
	private const FALLBACK_PREFIX = 'pwp0:';

	/**
	 * Length of the GCM authentication tag in bytes.
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Whether real encryption is available on this host.
	 *
	 * @return bool True when OpenSSL exposes the AES-256-GCM cipher.
	 */
	public static function is_strong(): bool {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& in_array( self::CIPHER, (array) openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt a value for storage.
	 *
	 * @param string $value Plain text value.
	 * @return string Portable, prefixed cipher text. Empty input yields an empty string.
	 */
	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( ! self::is_strong() ) {
			return self::FALLBACK_PREFIX . base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding, not obfuscation.
		}

		$iv_length = (int) openssl_cipher_iv_length( self::CIPHER );
		$iv        = self::random_bytes( $iv_length );
		$tag       = '';

		$cipher_text = openssl_encrypt(
			$value,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if ( false === $cipher_text ) {
			return self::FALLBACK_PREFIX . base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding, not obfuscation.
		}

		return self::PREFIX . base64_encode( $iv . $tag . $cipher_text ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding, not obfuscation.
	}

	/**
	 * Decrypt a stored value.
	 *
	 * Values that carry no known prefix are returned unchanged so credentials
	 * saved by ProcoreWP 1.x keep working until they are re-saved.
	 *
	 * @param string $value Stored value.
	 * @return string Plain text value, or an empty string when the payload cannot be read.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, self::FALLBACK_PREFIX ) ) {
			$decoded = base64_decode( substr( $value, strlen( self::FALLBACK_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding, not obfuscation.

			return false === $decoded ? '' : $decoded;
		}

		if ( 0 !== strpos( $value, self::PREFIX ) ) {
			// Legacy plaintext from ProcoreWP 1.x.
			return $value;
		}

		if ( ! self::is_strong() ) {
			return '';
		}

		$raw = base64_decode( substr( $value, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding, not obfuscation.

		if ( false === $raw ) {
			return '';
		}

		$iv_length = (int) openssl_cipher_iv_length( self::CIPHER );

		if ( strlen( $raw ) <= $iv_length + self::TAG_LENGTH ) {
			return '';
		}

		$iv          = substr( $raw, 0, $iv_length );
		$tag         = substr( $raw, $iv_length, self::TAG_LENGTH );
		$cipher_text = substr( $raw, $iv_length + self::TAG_LENGTH );

		$plain = openssl_decrypt(
			$cipher_text,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return false === $plain ? '' : $plain;
	}

	/**
	 * Mask a secret for display, revealing only the final four characters.
	 *
	 * @param string $value Secret value.
	 * @return string Masked representation.
	 */
	public static function mask( string $value ): string {
		$length = strlen( $value );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		return str_repeat( '•', min( 24, $length - 4 ) ) . substr( $value, -4 );
	}

	/**
	 * Derive the 256-bit encryption key from the site's salts.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function key(): string {
		$material = wp_salt( 'secure_auth' ) . wp_salt( 'auth' );

		return hash( 'sha256', 'procorewp|' . $material, true );
	}

	/**
	 * Generate cryptographically secure random bytes.
	 *
	 * @param int $length Number of bytes.
	 * @return string Raw bytes.
	 */
	private static function random_bytes( int $length ): string {
		try {
			return random_bytes( $length );
		} catch ( \Exception $e ) {
			return substr( hash( 'sha256', wp_generate_password( 64, true, true ), true ), 0, $length );
		}
	}
}
