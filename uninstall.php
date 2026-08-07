<?php
/**
 * Removes every trace of Procore Connect when the plugin is deleted.
 *
 * Runs only when an administrator deletes the plugin from the Plugins screen,
 * not on deactivation. Credentials and tokens are removed unless the site has
 * explicitly opted to keep its data.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$procore_connect_settings = get_option( 'procore_connect_settings', array() );

if ( is_array( $procore_connect_settings ) && ! empty( $procore_connect_settings['keep_data'] ) ) {
	return;
}

$procore_connect_options = array(
	'procore_connect_settings',
	'procore_connect_tokens',
	'procore_connect_cache_index',
	'procore_connect_rate_limit',
	'procore_connect_circuit',
	'procore_connect_log',
	'procore_connect_version',
	'procore_connect_migrated_from',
	// Written by ProcoreWP 1.x.
	'procore_integration_settings',
);

foreach ( $procore_connect_options as $procore_connect_option ) {
	delete_option( $procore_connect_option );

	if ( is_multisite() ) {
		delete_site_option( $procore_connect_option );
	}
}

wp_clear_scheduled_hook( 'procore_connect_warm_cache' );

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
		$wpdb->esc_like( '_transient_procore_connect_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_procore_connect_' ) . '%',
		$wpdb->esc_like( '_site_transient_procore_connect_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_procore_connect_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

wp_cache_flush();
