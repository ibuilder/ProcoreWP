<?php
/**
 * Shared test case.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Client;

/**
 * Resets shimmed WordPress state between tests.
 */
abstract class TestCase extends BaseTestCase {

	/**
	 * Reset every shared store before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		procore_connect_test_reset();
		Settings::flush();
		Client::set_instance( null );

		$GLOBALS['procore_connect_test_can'] = false;

		// Keep retries instant.
		add_filter(
			'procore_connect_retry_sleep',
			static function (): int {
				return 0;
			}
		);
	}

	/**
	 * Build a WordPress-shaped HTTP response array.
	 *
	 * @param mixed                 $body    Response body; arrays are JSON encoded.
	 * @param int                   $status  HTTP status code.
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, mixed> Response.
	 */
	protected function response( $body, int $status = 200, array $headers = array() ): array {
		return array(
			'response' => array( 'code' => $status ),
			'body'     => is_string( $body ) ? $body : (string) wp_json_encode( $body ),
			'headers'  => $headers,
		);
	}

	/**
	 * Build a client whose transport replays a fixed sequence of responses.
	 *
	 * @param array<int, mixed>                $responses Responses returned in order.
	 * @param array<int, array<string, mixed>> $calls Populated with the requests made.
	 * @return Client Configured client.
	 */
	protected function client_returning( array $responses, array &$calls = array() ): Client {
		$client = new Client();

		$client->set_auth( new FakeAuth() );
		$client->set_transport(
			static function ( string $url, array $args ) use ( &$responses, &$calls ) {
				$calls[] = array(
					'url'  => $url,
					'args' => $args,
				);

				if ( empty( $responses ) ) {
					return new \WP_Error( 'exhausted', 'No more canned responses.' );
				}

				return array_shift( $responses );
			}
		);

		Client::set_instance( $client );

		return $client;
	}
}
