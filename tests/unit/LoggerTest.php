<?php
/**
 * Diagnostic logging, and the redaction that keeps secrets out of it.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Support\Logger;

/**
 * The logger is the one place the plugin deliberately writes API context to
 * disk, so its redaction is what stands between a debug session and a client
 * secret in `debug.log`. It is worth asserting on directly.
 */
final class LoggerTest extends TestCase {

	/**
	 * Turn logging on before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Settings::set( 'enable_logging', true );
	}

	/**
	 * Nothing is recorded while logging is switched off.
	 *
	 * @return void
	 */
	public function test_logging_is_off_by_default(): void {
		Settings::set( 'enable_logging', false );

		Logger::error( 'should not be recorded' );

		$this->assertSame( array(), Logger::entries() );
	}

	/**
	 * An enabled logger records the message and its level.
	 *
	 * @return void
	 */
	public function test_records_message_and_level(): void {
		Logger::warning( 'throttled' );

		$entries = Logger::entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'warning', $entries[0]['level'] );
		$this->assertSame( 'throttled', $entries[0]['message'] );
	}

	/**
	 * Credentials passed as context must never be written.
	 *
	 * @return void
	 */
	public function test_redacts_credentials_from_context(): void {
		Logger::error(
			'auth failed',
			array(
				'client_secret' => 'sk_live_should_never_appear',
				'access_token'  => 'tok_should_never_appear',
				'refresh_token' => 'ref_should_never_appear',
				'code'          => 'authcode_should_never_appear',
				'password'      => 'pw_should_never_appear',
				'status'        => 401,
			)
		);

		$dump = (string) wp_json_encode( Logger::entries() );

		foreach ( array( 'sk_live_should_never_appear', 'tok_should_never_appear', 'ref_should_never_appear', 'authcode_should_never_appear', 'pw_should_never_appear' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $dump );
		}

		// Non-sensitive context is still useful and must survive.
		$this->assertStringContainsString( '401', $dump );
	}

	/**
	 * Redaction must reach credentials nested inside arrays.
	 *
	 * @return void
	 */
	public function test_redacts_nested_credentials(): void {
		Logger::error(
			'request failed',
			array(
				'request' => array(
					'headers' => array( 'Authorization' => 'Bearer leaked_token_value' ),
					'body'    => array( 'client_secret' => 'nested_secret_value' ),
				),
			)
		);

		$dump = (string) wp_json_encode( Logger::entries() );

		$this->assertStringNotContainsString( 'leaked_token_value', $dump );
		$this->assertStringNotContainsString( 'nested_secret_value', $dump );
		$this->assertStringContainsString( '[redacted]', $dump );
	}

	/**
	 * Long context values are truncated so the log cannot balloon.
	 *
	 * @return void
	 */
	public function test_truncates_very_long_context_values(): void {
		Logger::info( 'big', array( 'body' => str_repeat( 'x', 5000 ) ) );

		$entries = Logger::entries();

		$this->assertLessThanOrEqual( 500, strlen( (string) $entries[0]['context']['body'] ) );
	}

	/**
	 * The ring buffer must stay bounded, newest first.
	 *
	 * @return void
	 */
	public function test_ring_buffer_is_bounded_and_newest_first(): void {
		for ( $i = 0; $i < 60; $i++ ) {
			Logger::info( 'entry ' . $i );
		}

		$entries = Logger::entries();

		$this->assertCount( 50, $entries );
		$this->assertSame( 'entry 59', $entries[0]['message'] );
	}

	/**
	 * Clearing must empty the buffer.
	 *
	 * @return void
	 */
	public function test_clear_empties_the_buffer(): void {
		Logger::info( 'something' );
		Logger::clear();

		$this->assertSame( array(), Logger::entries() );
	}
}
