<?php
/**
 * At-rest encryption for stored Procore credentials and tokens.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts short secrets using AES-256-GCM.
 *
 * The key is derived from the site's own WordPress salts, so a database dump
 * without `wp-config.php` does not disclose credentials. If OpenSSL is not
 * available the payload is stored base64-encoded and `is_strong()` reports
 * false, which the admin screen surfaces as a warning.
 *
 * On the use of base64: AES-256-GCM produces raw bytes — an initialisation
 * vector, an authentication tag and cipher text — which cannot be stored in a
 * `wp_option` as-is. base64 is the transport encoding that makes those bytes
 * text-safe, applied *after* encryption and reversed *before* decryption. It is
 * not hiding anything: every call is on a value that is either already cipher
 * text or is about to be, and the plugin ships no encoded source, no encoded
 * payloads and no encoded URLs.
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
	 * Whether a value has already been through `encrypt()`.
	 *
	 * Callers use this to stay idempotent. `register_setting()` attaches the
	 * settings sanitizer to `sanitize_option_{$option}`, and WordPress runs
	 * that filter on *every* `update_option()` for the option — including the
	 * plugin's own internal writes. Encrypting unconditionally would therefore
	 * re-encrypt the stored cipher text on each save until the credential could
	 * no longer be recovered.
	 *
	 * @param string $value Candidate value.
	 * @return bool True when the value is already cipher text.
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strpos( $value, self::PREFIX ) || 0 === strpos( $value, self::FALLBACK_PREFIX );
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
	 * Peel every layer of encryption off a value.
	 *
	 * Versions 2.0.0 and 2.0.1 re-encrypted the stored client secret on each
	 * settings write, leaving multi-layered cipher text that a single
	 * `decrypt()` cannot recover. This unwraps repeatedly until the result is
	 * no longer cipher text, so an affected install can be repaired in place
	 * rather than forcing the operator to find their credentials again.
	 *
	 * @param string $value  Stored value, possibly encrypted more than once.
	 * @param int    $layers Safety ceiling on unwrapping passes.
	 * @return string Plain text value, or an empty string when unrecoverable.
	 */
	public static function decrypt_deep( string $value, int $layers = 12 ): string {
		$current = $value;

		for ( $i = 0; $i < $layers; $i++ ) {
			if ( ! self::is_encrypted( $current ) ) {
				return $current;
			}

			$next = self::decrypt( $current );

			// A layer that will not unwrap means the value is unrecoverable.
			if ( '' === $next || $next === $current ) {
				return '';
			}

			$current = $next;
		}

		// Still cipher text after the ceiling: treat as unrecoverable.
		return self::is_encrypted( $current ) ? '' : $current;
	}

	/**
	 * How many times a value has been encrypted.
	 *
	 * @param string $value  Stored value.
	 * @param int    $layers Safety ceiling on counting passes.
	 * @return int Number of encryption layers; 0 for plain text.
	 */
	public static function depth( string $value, int $layers = 12 ): int {
		$current = $value;
		$depth   = 0;

		for ( $i = 0; $i < $layers; $i++ ) {
			if ( ! self::is_encrypted( $current ) ) {
				break;
			}

			$next = self::decrypt( $current );

			if ( '' === $next || $next === $current ) {
				return $depth + 1;
			}

			$current = $next;
			++$depth;
		}

		return $depth;
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

		return hash( 'sha256', 'procore-connect|' . $material, true );
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
