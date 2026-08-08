<?php
/**
 * Settings sanitization and legacy migration.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Support\Encryption;

/**
 * ProcoreWP 1.x registered its option with no sanitize callback at all, so
 * these cases cover the boundary that replaced it.
 */
final class SettingsTest extends TestCase {

	/**
	 * Unknown values must fall back to a safe default rather than being stored.
	 *
	 * @return void
	 */
	public function test_rejects_unknown_enumerated_values(): void {
		$clean = Settings::sanitize(
			array(
				'auth_mode'   => 'implicit',
				'environment' => 'staging',
				'rest_access' => 'everyone',
			)
		);

		$this->assertSame( 'client_credentials', $clean['auth_mode'] );
		$this->assertSame( 'production', $clean['environment'] );
		$this->assertSame( 'logged_in', $clean['rest_access'] );
	}

	/**
	 * Numeric settings must be clamped to their documented ranges.
	 *
	 * @return void
	 */
	public function test_clamps_numeric_settings(): void {
		$clean = Settings::sanitize(
			array(
				'per_page'        => 99999,
				'cache_floor'     => 1,
				'request_timeout' => 600,
			)
		);

		$this->assertSame( 2000, $clean['per_page'] );
		$this->assertSame( 30, $clean['cache_floor'] );
		$this->assertSame( 60, $clean['request_timeout'] );
	}

	/**
	 * The secret must be encrypted before it reaches storage.
	 *
	 * @return void
	 */
	public function test_encrypts_the_client_secret(): void {
		$clean = Settings::sanitize( array( 'client_secret' => 'super-secret-value' ) );

		$this->assertNotSame( 'super-secret-value', $clean['client_secret'] );
		$this->assertSame( 'super-secret-value', Encryption::decrypt( $clean['client_secret'] ) );
	}

	/**
	 * Sanitizing must be idempotent, so a secret survives repeated saves.
	 *
	 * `register_setting()` attaches this sanitizer to `sanitize_option_{$option}`,
	 * and WordPress runs that on every `update_option()` for the option —
	 * including the plugin's own writes in `set()` and `migrate_legacy()`.
	 * Encrypting unconditionally re-encrypted the stored cipher text one layer
	 * per save until the credential was unrecoverable.
	 *
	 * @return void
	 */
	public function test_sanitize_does_not_re_encrypt_an_encrypted_secret(): void {
		$once = Settings::sanitize( array( 'client_secret' => 'super-secret-value' ) );

		$this->assertSame( 'super-secret-value', Encryption::decrypt( $once['client_secret'] ) );

		// Feed the sanitizer its own output, as update_option() does.
		$twice  = Settings::sanitize( $once );
		$thrice = Settings::sanitize( $twice );

		$this->assertSame( $once['client_secret'], $twice['client_secret'] );
		$this->assertSame( 'super-secret-value', Encryption::decrypt( $twice['client_secret'] ) );
		$this->assertSame( 'super-secret-value', Encryption::decrypt( $thrice['client_secret'] ) );
	}

	/**
	 * Writing settings programmatically must not corrupt the stored secret.
	 *
	 * @return void
	 */
	public function test_set_preserves_the_secret_through_the_sanitizer(): void {
		$stored = Settings::sanitize( array( 'client_secret' => 'keep-me' ) );
		update_option( Settings::OPTION, $stored );
		Settings::flush();

		// Simulate WordPress re-running the sanitizer on an internal write.
		for ( $i = 0; $i < 3; $i++ ) {
			$round = Settings::sanitize( Settings::all() );
			update_option( Settings::OPTION, $round );
			Settings::flush();
		}

		$this->assertSame( 'keep-me', Settings::client_secret() );
	}

	/**
	 * An empty secret field must leave the stored secret untouched, because the
	 * form renders a mask rather than the real value.
	 *
	 * @return void
	 */
	public function test_blank_secret_preserves_the_stored_value(): void {
		update_option( Settings::OPTION, array( 'client_secret' => Encryption::encrypt( 'existing' ) ) );
		Settings::flush();

		$clean = Settings::sanitize( array( 'client_secret' => '' ) );

		$this->assertSame( 'existing', Encryption::decrypt( $clean['client_secret'] ) );
	}

	/**
	 * An explicit clear request must remove the stored secret.
	 *
	 * @return void
	 */
	public function test_secret_can_be_cleared_explicitly(): void {
		update_option( Settings::OPTION, array( 'client_secret' => Encryption::encrypt( 'existing' ) ) );
		Settings::flush();

		$clean = Settings::sanitize( array( 'clear_client_secret' => '1' ) );

		$this->assertSame( '', $clean['client_secret'] );
	}

	/**
	 * Custom hosts must be restricted to HTTPS URLs.
	 *
	 * @return void
	 */
	public function test_custom_hosts_must_be_https(): void {
		$clean = Settings::sanitize(
			array(
				'custom_api_url'   => 'http://api.internal.test',
				'custom_login_url' => 'javascript:alert(1)',
			)
		);

		$this->assertSame( '', $clean['custom_api_url'] );
		$this->assertSame( '', $clean['custom_login_url'] );
	}

	/**
	 * Custom CSS must never be able to inject markup.
	 *
	 * @return void
	 */
	public function test_custom_css_is_stripped_of_tags(): void {
		$clean = Settings::sanitize( array( 'custom_css' => '.a{color:red}</style><script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>', $clean['custom_css'] );
		$this->assertStringNotContainsString( '</style>', $clean['custom_css'] );
	}

	/**
	 * Email suppression must stay on unless explicitly turned off.
	 *
	 * @return void
	 */
	public function test_email_suppression_defaults_to_on(): void {
		$this->assertTrue( Settings::defaults()['suppress_emails'] );
	}

	/**
	 * Settings written by ProcoreWP 1.x must be imported and re-encrypted.
	 *
	 * @return void
	 */
	public function test_migrates_legacy_settings(): void {
		update_option(
			Settings::LEGACY_OPTION,
			array(
				'client_id'          => 'legacy-id',
				'client_secret'      => 'legacy-secret',
				'default_company_id' => '4242',
				'token'              => 'stale-token-from-the-wrong-host',
			)
		);

		$this->assertTrue( Settings::migrate_legacy() );
		$this->assertSame( 'legacy-id', Settings::client_id() );
		$this->assertSame( 'legacy-secret', Settings::client_secret() );
		$this->assertSame( 4242, Settings::default_company_id() );

		// The plaintext option must be gone, and the stale token must not carry over.
		$this->assertFalse( get_option( Settings::LEGACY_OPTION ) );
		$this->assertArrayNotHasKey( 'token', Settings::all() );
	}
}
