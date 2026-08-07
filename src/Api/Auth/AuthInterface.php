<?php
/**
 * Contract shared by the supported Procore OAuth grant types.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Api\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * A Procore authentication strategy.
 */
interface AuthInterface {

	/**
	 * Return a usable bearer token, obtaining or refreshing one if required.
	 *
	 * @return string|\WP_Error Access token, or an error describing the failure.
	 */
	public function access_token();

	/**
	 * Whether the strategy has everything it needs to authenticate.
	 *
	 * @return bool True when configured.
	 */
	public function is_configured(): bool;

	/**
	 * Grant type identifier, as sent to Procore.
	 *
	 * @return string Grant type.
	 */
	public function grant_type(): string;

	/**
	 * Human readable name for the admin screens.
	 *
	 * @return string Label.
	 */
	public function label(): string;
}
