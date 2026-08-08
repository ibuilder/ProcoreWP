<?php
/**
 * OAuth grant behaviour: token exchange, CSRF state and refresh rotation.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Auth\AuthorizationCode;
use ProcoreConnect\Api\Auth\ClientCredentials;
use ProcoreConnect\Api\TokenStore;
use ProcoreConnect\Support\Encryption;

/**
 * Covers the credential-handling paths.
 *
 * These are the parts that can lock a site out of Procore permanently or, in
 * the Authorization Code flow, let an attacker graft their own Procore account
 * onto the site, so they are worth asserting on directly.
 */
final class AuthTest extends TestCase {

	/**
	 * Requests captured from the token endpoint.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $requests = array();

	/**
	 * Configure credentials before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->requests = array();

		Settings::set( 'client_id', 'test-client-id' );
		Settings::set( 'client_secret', Encryption::encrypt( 'test-client-secret' ) );
	}

	/**
	 * Queue canned responses for the token endpoint.
	 *
	 * @param array<int, array<string, mixed>> $responses Responses returned in order.
	 * @return void
	 */
	private function token_endpoint_returns( array $responses ): void {
		add_filter(
			'procore_connect_test_http',
			function ( $default_value, $url, $args ) use ( &$responses ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				if ( empty( $responses ) ) {
					return $default_value;
				}

				return array_shift( $responses );
			}
		);
	}

	/**
	 * Build a token-endpoint response.
	 *
	 * @param array<string, mixed> $body   Response body.
	 * @param int                  $status HTTP status code.
	 * @return array<string, mixed> Response.
	 */
	private function token_response( array $body, int $status = 200 ): array {
		return array(
			'response' => array( 'code' => $status ),
			'body'     => (string) wp_json_encode( $body ),
			'headers'  => array(),
		);
	}

	/* -- Client Credentials ------------------------------------------------ */

	/**
	 * The token request must go to the login host, not the API host.
	 *
	 * ProcoreWP 1.x posted to the API host, which is why it could never
	 * authenticate on any install.
	 *
	 * @return void
	 */
	public function test_client_credentials_posts_to_the_login_host(): void {
		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'access_token' => 'abc',
						'expires_in'   => 5400,
					)
				),
			)
		);

		$token = ( new ClientCredentials() )->access_token();

		$this->assertSame( 'abc', $token );
		$this->assertSame( 'https://login.procore.com/oauth/token', $this->requests[0]['url'] );
		$this->assertSame( 'client_credentials', $this->requests[0]['args']['body']['grant_type'] );
		$this->assertSame( 'test-client-id', $this->requests[0]['args']['body']['client_id'] );
		$this->assertSame( 'test-client-secret', $this->requests[0]['args']['body']['client_secret'] );
	}

	/**
	 * A stored, unexpired token must be reused rather than re-requested.
	 *
	 * @return void
	 */
	public function test_client_credentials_reuses_a_valid_token(): void {
		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'access_token' => 'abc',
						'expires_in'   => 5400,
					)
				),
			)
		);

		$auth = new ClientCredentials();
		$auth->access_token();
		$auth->access_token();

		$this->assertCount( 1, $this->requests );
	}

	/**
	 * Missing credentials must fail before any network call is attempted.
	 *
	 * @return void
	 */
	public function test_client_credentials_requires_credentials(): void {
		Settings::set( 'client_id', '' );
		Settings::set( 'client_secret', '' );

		$result = ( new ClientCredentials() )->access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_missing_credentials', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Procore's own rejection message must reach the administrator.
	 *
	 * @return void
	 */
	public function test_client_credentials_surfaces_the_rejection_reason(): void {
		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'error'             => 'invalid_client',
						'error_description' => 'Client authentication failed.',
					),
					401
				),
			)
		);

		$result = ( new ClientCredentials() )->access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'Client authentication failed.', $result->get_error_message() );
	}

	/* -- Authorization Code ------------------------------------------------ */

	/**
	 * The authorize URL must carry everything Procore requires.
	 *
	 * @return void
	 */
	public function test_authorization_url_is_well_formed(): void {
		$url = ( new AuthorizationCode() )->authorization_url();

		$this->assertStringStartsWith( 'https://login.procore.com/oauth/authorize', $url );
		$this->assertStringContainsString( 'client_id=test-client-id', $url );
		$this->assertStringContainsString( 'response_type=code', $url );
		$this->assertStringContainsString( 'redirect_uri=', $url );
		$this->assertStringContainsString( 'state=', $url );
	}

	/**
	 * A callback whose state was never issued must be refused.
	 *
	 * This is what stops an attacker grafting their own Procore account onto
	 * the site by feeding an administrator a crafted callback URL.
	 *
	 * @return void
	 */
	public function test_exchange_refuses_an_unknown_state(): void {
		$result = ( new AuthorizationCode() )->exchange_code( 'some-code', 'never-issued' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_bad_state', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * A callback with no state at all must be refused.
	 *
	 * @return void
	 */
	public function test_exchange_refuses_an_empty_state(): void {
		$result = ( new AuthorizationCode() )->exchange_code( 'some-code', '' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_bad_state', $result->get_error_code() );
	}

	/**
	 * A valid state with no code must be refused.
	 *
	 * @return void
	 */
	public function test_exchange_refuses_a_missing_code(): void {
		$auth  = new AuthorizationCode();
		$state = $this->issue_state( $auth );

		$result = $auth->exchange_code( '', $state );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_missing_code', $result->get_error_code() );
	}

	/**
	 * A valid exchange must store both tokens.
	 *
	 * @return void
	 */
	public function test_exchange_stores_both_tokens(): void {
		$auth  = new AuthorizationCode();
		$state = $this->issue_state( $auth );

		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'access_token'  => 'access-1',
						'refresh_token' => 'refresh-1',
						'expires_in'    => 5400,
					)
				),
			)
		);

		$result = $auth->exchange_code( 'the-code', $state );

		$this->assertTrue( $result );
		$this->assertSame( 'authorization_code', $this->requests[0]['args']['body']['grant_type'] );
		$this->assertSame( 'the-code', $this->requests[0]['args']['body']['code'] );
		$this->assertSame( 'access-1', TokenStore::access_token() );
		$this->assertSame( 'refresh-1', TokenStore::refresh_token() );
		$this->assertTrue( $auth->is_connected() );
	}

	/**
	 * A state value must not be replayable.
	 *
	 * @return void
	 */
	public function test_state_is_single_use(): void {
		$auth  = new AuthorizationCode();
		$state = $this->issue_state( $auth );

		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'access_token'  => 'a',
						'refresh_token' => 'r',
						'expires_in'    => 5400,
					)
				),
				$this->token_response(
					array(
						'access_token'  => 'b',
						'refresh_token' => 's',
						'expires_in'    => 5400,
					)
				),
			)
		);

		$this->assertTrue( $auth->exchange_code( 'code-1', $state ) );

		$replay = $auth->exchange_code( 'code-2', $state );

		$this->assertTrue( is_wp_error( $replay ) );
		$this->assertSame( 'procore_connect_bad_state', $replay->get_error_code() );
	}

	/**
	 * An expired access token must be refreshed, and the rotated refresh token
	 * must replace the old one.
	 *
	 * Procore invalidates a refresh token the instant it is exchanged, so
	 * failing to persist the replacement locks the site out permanently.
	 *
	 * @return void
	 */
	public function test_refresh_persists_the_rotated_refresh_token(): void {
		TokenStore::store(
			array(
				'access_token'  => 'stale',
				'refresh_token' => 'refresh-old',
				'expires_in'    => 0,
			),
			'authorization_code'
		);

		$this->token_endpoint_returns(
			array(
				$this->token_response(
					array(
						'access_token'  => 'access-new',
						'refresh_token' => 'refresh-new',
						'expires_in'    => 5400,
					)
				),
			)
		);

		$token = ( new AuthorizationCode() )->access_token();

		$this->assertSame( 'access-new', $token );
		$this->assertSame( 'refresh_token', $this->requests[0]['args']['body']['grant_type'] );
		$this->assertSame( 'refresh-old', $this->requests[0]['args']['body']['refresh_token'] );
		$this->assertSame( 'refresh-new', TokenStore::refresh_token() );
	}

	/**
	 * A rejected refresh token must clear the connection and say so.
	 *
	 * There is no recovery from a rotated-away refresh token, so leaving the
	 * dead one in place would retry forever.
	 *
	 * @return void
	 */
	public function test_rejected_refresh_token_resets_the_connection(): void {
		TokenStore::store(
			array(
				'access_token'  => 'stale',
				'refresh_token' => 'refresh-dead',
				'expires_in'    => 0,
			),
			'authorization_code'
		);

		$this->token_endpoint_returns(
			array( $this->token_response( array( 'error' => 'invalid_grant' ), 400 ) )
		);

		$result = ( new AuthorizationCode() )->access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_reconnect_required', $result->get_error_code() );
		$this->assertSame( '', TokenStore::refresh_token() );
		$this->assertSame( '', TokenStore::access_token() );
	}

	/**
	 * With no refresh token at all the site must be told to connect.
	 *
	 * @return void
	 */
	public function test_unconnected_site_is_told_to_connect(): void {
		$result = ( new AuthorizationCode() )->access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'procore_connect_not_connected', $result->get_error_code() );
		$this->assertFalse( ( new AuthorizationCode() )->is_connected() );
	}

	/* -- Token storage ------------------------------------------------------ */

	/**
	 * Stored tokens must be encrypted and expire early of the real deadline.
	 *
	 * @return void
	 */
	public function test_tokens_are_encrypted_with_an_expiry_margin(): void {
		TokenStore::store(
			array(
				'access_token'  => 'plain-access',
				'refresh_token' => 'plain-refresh',
				'expires_in'    => 5400,
			),
			'client_credentials'
		);

		$raw = get_option( TokenStore::OPTION );

		$this->assertStringNotContainsString( 'plain-access', (string) $raw['access_token'] );
		$this->assertStringNotContainsString( 'plain-refresh', (string) $raw['refresh_token'] );
		$this->assertSame( 'plain-access', TokenStore::access_token() );

		// Two minutes of head-room are subtracted so a token cannot expire mid-flight.
		$this->assertLessThan( time() + 5400, TokenStore::all()['expires_at'] );
		$this->assertGreaterThan( time() + 5000, TokenStore::all()['expires_at'] );
	}

	/**
	 * A token obtained against one environment must not be used in another.
	 *
	 * @return void
	 */
	public function test_tokens_do_not_leak_between_environments(): void {
		TokenStore::store(
			array(
				'access_token' => 'prod',
				'expires_in'   => 5400,
			),
			'client_credentials'
		);

		$this->assertTrue( TokenStore::has_valid_token() );

		Settings::set( 'environment', 'sandbox' );

		$this->assertFalse( TokenStore::has_valid_token() );
	}

	/**
	 * An expired token must not be treated as usable.
	 *
	 * @return void
	 */
	public function test_expired_tokens_are_not_valid(): void {
		TokenStore::store(
			array(
				'access_token' => 'old',
				'expires_in'   => 0,
			),
			'client_credentials'
		);

		$this->assertFalse( TokenStore::has_valid_token() );
	}

	/**
	 * The refresh lock must be exclusive, then reusable once released.
	 *
	 * @return void
	 */
	public function test_refresh_lock_is_exclusive(): void {
		$this->assertTrue( TokenStore::acquire_lock() );
		$this->assertFalse( TokenStore::acquire_lock() );

		TokenStore::release_lock();

		$this->assertTrue( TokenStore::acquire_lock() );
	}

	/**
	 * Clearing must remove both tokens and the lock.
	 *
	 * @return void
	 */
	public function test_clear_removes_everything(): void {
		TokenStore::store(
			array(
				'access_token'  => 'a',
				'refresh_token' => 'r',
				'expires_in'    => 5400,
			),
			'authorization_code'
		);

		TokenStore::clear();

		$this->assertSame( '', TokenStore::access_token() );
		$this->assertSame( '', TokenStore::refresh_token() );
		$this->assertFalse( TokenStore::has_valid_token() );
		$this->assertTrue( TokenStore::acquire_lock() );
	}

	/**
	 * Start an Authorization Code flow and return its issued state value.
	 *
	 * @param AuthorizationCode $auth Strategy under test.
	 * @return string State value.
	 */
	private function issue_state( AuthorizationCode $auth ): string {
		$url = $auth->authorization_url();

		preg_match( '/state=([^&]+)/', $url, $matches );

		return rawurldecode( $matches[1] ?? '' );
	}
}
