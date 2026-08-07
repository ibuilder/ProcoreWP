<?php
/**
 * Authentication double that returns a fixed token.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Tests\unit;

use ProcoreWP\Api\Auth\AuthInterface;

/**
 * Returns a canned bearer token so transport behaviour can be tested in
 * isolation from the OAuth exchange.
 */
final class FakeAuth implements AuthInterface {

	/**
	 * Token, or an error to return instead.
	 *
	 * @var string|\WP_Error
	 */
	private $token;

	/**
	 * Construct the double.
	 *
	 * @param string|\WP_Error $token Token or error to return.
	 */
	public function __construct( $token = 'test-token' ) {
		$this->token = $token;
	}

	/**
	 * Return the canned token.
	 *
	 * @return string|\WP_Error Token or error.
	 */
	public function access_token() {
		return $this->token;
	}

	/**
	 * Always configured.
	 *
	 * @return bool True.
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * Grant type identifier.
	 *
	 * @return string Grant type.
	 */
	public function grant_type(): string {
		return 'client_credentials';
	}

	/**
	 * Human readable name.
	 *
	 * @return string Label.
	 */
	public function label(): string {
		return 'Fake';
	}
}
