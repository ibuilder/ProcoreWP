<?php
/**
 * Encryption, array access and value formatting.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Support\Arr;
use ProcoreConnect\Support\Encryption;
use ProcoreConnect\Support\Format;

/**
 * Covers the helpers that guard credentials and escape output.
 */
final class SupportTest extends TestCase {

	/**
	 * A secret must survive an encrypt/decrypt round trip.
	 *
	 * @return void
	 */
	public function test_encryption_round_trip(): void {
		$secret = 'sk_live_1234567890abcdefghij';

		$cipher = Encryption::encrypt( $secret );

		$this->assertNotSame( $secret, $cipher );
		$this->assertStringNotContainsString( $secret, $cipher );
		$this->assertSame( $secret, Encryption::decrypt( $cipher ) );
	}

	/**
	 * Encrypting the same value twice must not produce the same cipher text.
	 *
	 * @return void
	 */
	public function test_encryption_uses_a_fresh_iv(): void {
		$this->assertNotSame( Encryption::encrypt( 'same' ), Encryption::encrypt( 'same' ) );
	}

	/**
	 * Plaintext left behind by ProcoreWP 1.x must still be readable.
	 *
	 * @return void
	 */
	public function test_decrypt_passes_through_legacy_plaintext(): void {
		$this->assertSame( 'legacy-secret', Encryption::decrypt( 'legacy-secret' ) );
	}

	/**
	 * Masking must reveal no more than the last four characters.
	 *
	 * @return void
	 */
	public function test_mask_hides_the_secret(): void {
		$masked = Encryption::mask( 'abcdefghijklmnop' );

		$this->assertStringEndsWith( 'mnop', $masked );
		$this->assertStringNotContainsString( 'abcdefghijkl', $masked );
	}

	/**
	 * Dot-path reads must tolerate missing keys.
	 *
	 * @return void
	 */
	public function test_arr_get_handles_missing_paths(): void {
		$record = array( 'vendor' => array( 'name' => 'Acme' ) );

		$this->assertSame( 'Acme', Arr::get( $record, 'vendor.name' ) );
		$this->assertNull( Arr::get( $record, 'vendor.city' ) );
		$this->assertSame( 'n/a', Arr::get( $record, 'office.name', 'n/a' ) );
	}

	/**
	 * Related records must render via their label field.
	 *
	 * @return void
	 */
	public function test_stringify_uses_a_label_field(): void {
		$this->assertSame(
			'Acme',
			Arr::stringify(
				array(
					'id'   => 3,
					'name' => 'Acme',
				)
			)
		);
		$this->assertSame( 'Yes', Arr::stringify( true ) );
		$this->assertSame( '', Arr::stringify( null ) );
	}

	/**
	 * Sorting must not drop or reorder rows that lack the sort column.
	 *
	 * ProcoreWP 1.x used array_multisort() over array_column(), which silently
	 * misaligns rows as soon as any record is missing the key.
	 *
	 * @return void
	 */
	public function test_sort_by_tolerates_missing_keys(): void {
		$rows = array(
			array(
				'id'   => 1,
				'name' => 'Bravo',
			),
			array( 'id' => 2 ),
			array(
				'id'   => 3,
				'name' => 'Alpha',
			),
		);

		$sorted = Arr::sort_by( $rows, 'name' );

		// No row is dropped, values stay attached to their own record, and the
		// record with no name sorts last rather than displacing the others.
		$this->assertCount( 3, $sorted );
		$this->assertSame( array( 3, 1, 2 ), array_column( $sorted, 'id' ) );
		$this->assertSame( 'Alpha', $sorted[0]['name'] );
		$this->assertSame( 'Bravo', $sorted[1]['name'] );
		$this->assertArrayNotHasKey( 'name', $sorted[2] );

		// Reversing must not promote the empty row to the top.
		$reversed = Arr::sort_by( $rows, 'name', 'desc' );

		$this->assertSame( array( 1, 3, 2 ), array_column( $reversed, 'id' ) );
	}

	/**
	 * Every cell value must be escaped.
	 *
	 * @return void
	 */
	public function test_cell_escapes_html(): void {
		$record = array( 'name' => '<script>alert(1)</script>' );

		$rendered = Format::cell(
			$record,
			array(
				'key'    => 'name',
				'format' => 'text',
			)
		);

		$this->assertStringNotContainsString( '<script>', $rendered );
		$this->assertStringContainsString( '&lt;script&gt;', $rendered );
	}

	/**
	 * Email output must be suppressed by default.
	 *
	 * @return void
	 */
	public function test_emails_are_suppressed_by_default(): void {
		$record = array( 'email_address' => 'someone@example.com' );

		$this->assertSame(
			'',
			Format::cell(
				$record,
				array(
					'key'    => 'email_address',
					'format' => 'email',
				),
				true
			)
		);
	}

	/**
	 * Email output requires both the global setting and the shortcode opt-in.
	 *
	 * @return void
	 */
	public function test_emails_require_both_opt_ins(): void {
		Settings::set( 'suppress_emails', false );

		$record = array( 'email_address' => 'someone@example.com' );

		$this->assertSame(
			'',
			Format::cell(
				$record,
				array(
					'key'    => 'email_address',
					'format' => 'email',
				),
				false
			)
		);
		$this->assertStringContainsString(
			'someone@example.com',
			Format::cell(
				$record,
				array(
					'key'    => 'email_address',
					'format' => 'email',
				),
				true
			)
		);
	}

	/**
	 * A javascript: URL must never be rendered as a link.
	 *
	 * @return void
	 */
	public function test_url_cells_reject_dangerous_schemes(): void {
		$record = array( 'website' => 'javascript:alert(1)' );

		$this->assertSame(
			'—',
			Format::cell(
				$record,
				array(
					'key'    => 'website',
					'format' => 'url',
				)
			)
		);
	}

	/**
	 * Class attributes must be reduced to tokens that cannot break out of the
	 * attribute, however hostile the supplied value is.
	 *
	 * @return void
	 */
	public function test_classes_are_sanitized(): void {
		$result = Format::classes( 'procore-connect', 'my-class "onerror=alert(1) <img>' );

		$this->assertStringStartsWith( 'procore-connect my-class', $result );

		foreach ( array( '"', "'", '=', '(', ')', '<', '>', '/' ) as $character ) {
			$this->assertStringNotContainsString( $character, $result );
		}
	}
}
