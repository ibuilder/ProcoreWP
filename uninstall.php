<?php
/**
 * Removes every trace of ProcoreWP when the plugin is deleted.
 *
 * Runs only when an administrator deletes the plugin from the Plugins screen,
 * not on deactivation. Credentials and tokens are removed unless the site has
 * explicitly opted to keep its data.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$procorewp_settings = get_option( 'procorewp_settings', array() );

if ( is_array( $procorewp_settings ) && ! empty( $procorewp_settings['keep_data'] ) ) {
	return;
}

$procorewp_options = array(
	'procorewp_settings',
	'procorewp_tokens',
	'procorewp_cache_index',
	'procorewp_rate_limit',
	'procorewp_circuit',
	'procorewp_log',
	'procorewp_version',
	'procorewp_migrated_from',
	// Written by ProcoreWP 1.x.
	'procore_integration_settings',
);

foreach ( $procorewp_options as $procorewp_option ) {
	delete_option( $procorewp_option );

	if ( is_multisite() ) {
		delete_site_option( $procorewp_option );
	}
}

wp_clear_scheduled_hook( 'procorewp_warm_cache' );

/*
 * Cached responses and OAuth state live in transients. The purge index has just
 * been deleted, so remove them directly; the LIKE patterns are constrained to
 * this plugin's own prefixes.
 */
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off cleanup on uninstall; there is no Core API for bulk transient deletion.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_procorewp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_procorewp_' ) . '%',
		$wpdb->esc_like( '_site_transient_procorewp_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_procorewp_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

wp_cache_flush();
