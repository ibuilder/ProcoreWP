<?php
/**
 * Admin notices.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Admin;

use ProcoreConnect\Api\Client;
use ProcoreConnect\Api\Environment;
use ProcoreConnect\Support\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Surfaces conditions an administrator needs to know about.
 */
final class Notices {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print any applicable notices.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->oauth_result();
		$this->migration_notice();
		$this->secret_repair_notice();

		if ( ! $this->on_plugin_screen() ) {
			return;
		}

		$this->encryption_notice();
		$this->environment_notice();
		$this->circuit_notice();
	}

	/**
	 * Report the outcome of an Authorization Code round trip.
	 *
	 * @return void
	 */
	private function oauth_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by our own redirect.
		$status = isset( $_GET['procore_connect_oauth'] ) ? sanitize_key( wp_unslash( $_GET['procore_connect_oauth'] ) ) : '';

		if ( '' === $status ) {
			return;
		}

		if ( 'connected' === $status ) {
			$this->notice( 'success', __( 'Connected to Procore.', 'procore-connect' ) );

			return;
		}

		$stored_error = (string) get_transient( 'procore_connect_oauth_error' );

		$messages = array(
			'denied'              => __( 'The Procore authorization request was declined.', 'procore-connect' ),
			'missing_credentials' => __( 'Enter a Client ID and Client Secret before connecting.', 'procore-connect' ),
			'failed'              => '' !== $stored_error ? $stored_error : __( 'The Procore connection could not be completed.', 'procore-connect' ),
		);

		delete_transient( 'procore_connect_oauth_error' );

		if ( isset( $messages[ $status ] ) ) {
			$this->notice( 'error', $messages[ $status ] );
		}
	}

	/**
	 * Point administrators at the settings screen after a 1.x upgrade.
	 *
	 * @return void
	 */
	private function migration_notice(): void {
		if ( '1.x' !== get_option( 'procore_connect_migrated_from' ) ) {
			return;
		}

		$this->notice(
			'warning',
			sprintf(
				/* translators: %s: settings screen URL. */
				__( 'Procore Connect imported your settings from version 1.x. Version 1.x authenticated against the wrong Procore host, so you need to <a href="%s">re-run the connection test</a> before shortcodes will return data.', 'procore-connect' ),
				esc_url( admin_url( 'admin.php?page=procore-connect' ) )
			),
			true
		);

		delete_option( 'procore_connect_migrated_from' );
	}

	/**
	 * Report the outcome of repairing a secret damaged by 2.0.0 or 2.0.1.
	 *
	 * @return void
	 */
	private function secret_repair_notice(): void {
		$state = (string) get_option( 'procore_connect_secret_repair', '' );

		if ( '' === $state ) {
			return;
		}

		delete_option( 'procore_connect_secret_repair' );

		if ( 'repaired' === $state ) {
			$this->notice(
				'success',
				__( 'Procore Connect repaired your stored Client Secret. Versions 2.0.0 and 2.0.1 re-encrypted it on every settings save, which eventually made it unreadable. Nothing further is needed, though it is worth running Procore → Connection → Test connection to confirm.', 'procore-connect' )
			);

			return;
		}

		$this->notice(
			'error',
			sprintf(
				/* translators: %s: settings screen URL. */
				__( 'Procore Connect could not recover your stored Client Secret. Versions 2.0.0 and 2.0.1 re-encrypted it on every settings save until it became unreadable, so it has been cleared. Please <a href="%s">enter it again</a>; this cannot happen on 2.0.2 or later.', 'procore-connect' ),
				esc_url( admin_url( 'admin.php?page=procore-connect' ) )
			),
			true
		);
	}

	/**
	 * Warn when credentials cannot be encrypted at rest.
	 *
	 * @return void
	 */
	private function encryption_notice(): void {
		if ( Encryption::is_strong() || Settings::credentials_are_constants() ) {
			return;
		}

		$this->notice(
			'warning',
			__( 'OpenSSL is not available on this server, so your Procore Client Secret cannot be encrypted in the database. Define PROCORE_CONNECT_CLIENT_SECRET in wp-config.php instead.', 'procore-connect' )
		);
	}

	/**
	 * Make a non-production environment obvious.
	 *
	 * @return void
	 */
	private function environment_notice(): void {
		if ( Environment::is_production() ) {
			return;
		}

		$this->notice(
			'info',
			sprintf(
				/* translators: 1: environment label, 2: API host. */
				__( 'Procore Connect is pointed at %1$s (%2$s). Front-end shortcodes are showing sandbox data.', 'procore-connect' ),
				Environment::choices()[ Environment::current() ],
				Environment::api_host()
			)
		);
	}

	/**
	 * Report an open circuit breaker.
	 *
	 * @return void
	 */
	private function circuit_notice(): void {
		$state = Client::circuit_state();

		if ( $state['open_until'] <= time() ) {
			return;
		}

		$this->notice(
			'error',
			sprintf(
				/* translators: %s: human readable time difference. */
				__( 'Procore requests are paused after repeated failures and will resume in %s. Cached data is being served in the meantime.', 'procore-connect' ),
				human_time_diff( time(), $state['open_until'] )
			)
		);
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @return bool True on a Procore Connect screen.
	 */
	private function on_plugin_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen instanceof \WP_Screen && false !== strpos( (string) $screen->id, 'procore-connect' );
	}

	/**
	 * Print a notice.
	 *
	 * @param string $type      Notice type: success, warning, error or info.
	 * @param string $message   Message text.
	 * @param bool   $allow_html Whether the message contains a permitted link.
	 * @return void
	 */
	private function notice( string $type, string $message, bool $allow_html = false ): void {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $type ),
			$allow_html
				? wp_kses( $message, array( 'a' => array( 'href' => array() ) ) )
				: esc_html( $message )
		);
	}
}
