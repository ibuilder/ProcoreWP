<?php
/**
 * Authorization Code flow entry and callback handling.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Admin;

use ProcoreConnect\Api\Auth\AuthorizationCode;

defined( 'ABSPATH' ) || exit;

/**
 * Drives the "Connect to Procore" round trip.
 *
 * Both routes require `manage_options` and a nonce on the outbound leg; the
 * inbound leg is verified against the one-time `state` value registered when
 * the flow started, which is what prevents an attacker from grafting their own
 * Procore account onto this site.
 */
final class OAuthController {

	/**
	 * Nonce action guarding the outbound redirect.
	 */
	public const NONCE = 'procore_connect_oauth_connect';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_procore_connect_oauth_connect', array( $this, 'connect' ) );
		add_action( 'admin_post_procore_connect_oauth_callback', array( $this, 'callback' ) );
	}

	/**
	 * Redirect the administrator to Procore's authorization screen.
	 *
	 * @return void
	 */
	public function connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to connect this site to Procore.', 'connect-for-procore' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE );

		$auth = new AuthorizationCode();

		if ( ! $auth->is_configured() ) {
			$this->redirect_back( 'missing_credentials' );
		}

		/*
		 * wp_safe_redirect() is deliberately not used: the destination is
		 * Procore's own authorization screen on an external host, which the
		 * safe-redirect allow-list would block. The URL is built entirely from
		 * the configured environment, never from request input.
		 */
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External OAuth provider, URL built from trusted settings.
		wp_redirect( $auth->authorization_url() );
		exit;
	}

	/**
	 * Handle Procore's redirect back to this site.
	 *
	 * @return void
	 */
	public function callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to complete this connection.', 'connect-for-procore' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified via the one-time OAuth state parameter below.
		if ( isset( $_GET['error'] ) ) {
			$this->redirect_back( 'denied' );
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = ( new AuthorizationCode() )->exchange_code( $code, $state );

		if ( is_wp_error( $result ) ) {
			set_transient( 'procore_connect_oauth_error', $result->get_error_message(), MINUTE_IN_SECONDS * 5 );
			$this->redirect_back( 'failed' );
		}

		$this->redirect_back( 'connected' );
	}

	/**
	 * Return to the settings screen carrying a status flag.
	 *
	 * @param string $status Status slug appended to the URL.
	 * @return void
	 */
	private function redirect_back( string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'procore-connect',
					'procore_connect_oauth' => $status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
