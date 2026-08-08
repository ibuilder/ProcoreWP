<?php
/**
 * Plugin bootstrap and hook registration.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect;

use ProcoreConnect\Admin\Ajax;
use ProcoreConnect\Admin\Notices;
use ProcoreConnect\Admin\OAuthController;
use ProcoreConnect\Admin\SettingsPage;
use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Api\Cache;
use ProcoreConnect\Api\TokenStore;
use ProcoreConnect\Blocks\Registrar as BlockRegistrar;
use ProcoreConnect\Cli\Commands;
use ProcoreConnect\Frontend\Assets;
use ProcoreConnect\Frontend\Shortcodes\Registrar as ShortcodeRegistrar;
use ProcoreConnect\Rest\Controller as RestController;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components into WordPress.
 */
final class Plugin {

	/**
	 * Option storing the installed version, used to trigger upgrade routines.
	 */
	public const VERSION_OPTION = 'procore_connect_version';

	/**
	 * Cron hook used by the optional cache warmer.
	 */
	public const CRON_HOOK = 'procore_connect_warm_cache';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the shared plugin instance, booting it on first call.
	 *
	 * @return self Plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function boot(): void {
		add_action( 'init', array( $this, 'maybe_upgrade' ) );

		( new ShortcodeRegistrar() )->register();
		( new Assets() )->register();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new Ajax() )->register();
			( new Notices() )->register();
		}

		( new OAuthController() )->register();

		if ( Settings::get( 'enable_blocks', true ) ) {
			( new BlockRegistrar() )->register();
		}

		if ( Settings::get( 'enable_rest', false ) ) {
			( new RestController() )->register();
		}

		add_action( self::CRON_HOOK, array( $this, 'warm_cache' ) );
		add_filter( 'plugin_action_links_' . PROCORE_CONNECT_BASENAME, array( $this, 'action_links' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register();
		}
	}

	/*
	 * No load_plugin_textdomain() call: WordPress has loaded translations
	 * just-in-time since 4.6 for any plugin whose text domain matches its
	 * directory name, and calling it explicitly is now flagged by Plugin Check.
	 */

	/**
	 * Run upgrade routines when the stored version is behind the running one.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '' );

		if ( PROCORE_CONNECT_VERSION === $installed ) {
			return;
		}

		if ( '' === $installed ) {
			Settings::migrate_legacy();
		}

		if ( '' !== $installed && version_compare( $installed, '2.0.0', '<' ) ) {
			Cache::flush();
		}

		/*
		 * 2.0.0 and 2.0.1 re-encrypted the client secret on every settings
		 * write, so an install upgrading from either may hold a secret it can
		 * no longer decrypt. Repair it here rather than leaving the site with
		 * an unexplained authentication failure.
		 */
		if ( '' !== $installed && version_compare( $installed, '2.0.2', '<' ) ) {
			$repair = Settings::repair_client_secret();

			if ( 'ok' !== $repair ) {
				update_option( 'procore_connect_secret_repair', $repair, false );
				TokenStore::clear();
			}
		}

		update_option( self::VERSION_OPTION, PROCORE_CONNECT_VERSION, false );
	}

	/**
	 * Refresh cached responses for the configured default company.
	 *
	 * @return void
	 */
	public function warm_cache(): void {
		$company = Settings::default_company_id();

		if ( $company <= 0 ) {
			return;
		}

		Api\Client::instance()->fetch(
			'projects',
			array(),
			array(
				'company_id'   => $company,
				'bypass_cache' => true,
			)
		);
	}

	/**
	 * Add a Settings link to the plugins list row.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string> Modified action links.
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=procore-connect' ) ),
			esc_html__( 'Settings', 'procore-connect' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Activation handler.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Settings::migrate_legacy();

		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}

		update_option( self::VERSION_OPTION, PROCORE_CONNECT_VERSION, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Deactivation handler.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		Cache::flush();
	}
}
