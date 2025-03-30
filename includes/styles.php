<?php
/**
 * Procore Integration Styles
 *
 * This file manages the CSS styles for the Procore Integration plugin.
 * It registers and enqueues the styles for the frontend.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register and enqueue the plugin's stylesheets
 */
function procore_integration_enqueue_styles() {
    // First, register the stylesheet
    wp_register_style(
        'procore-integration-styles',
        PROCORE_INTEGRATION_URL . 'assets/css/procore-integration.css',
        [],
        PROCORE_INTEGRATION_VERSION
    );
    
    // Then enqueue it
    wp_enqueue_style('procore-integration-styles');
}

// Add the styles to WordPress
add_action('wp_enqueue_scripts', 'procore_integration_enqueue_styles');

/**
 * Create the CSS file on plugin activation
 */
function procore_integration_create_styles() {
    // Create assets directory if it doesn't exist
    $css_dir = PROCORE_INTEGRATION_PATH . 'assets/css';
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }

    // Create default CSS file
    $css_file = $css_dir . '/procore-integration.css';
    if (!file_exists($css_file)) {
        $css_content = file_get_contents(PROCORE_INTEGRATION_PATH . 'assets/css/procore-integration-default.css');
        file_put_contents($css_file, $css_content);
    }
}

// Run this function on plugin activation
register_activation_hook(PROCORE_INTEGRATION_PATH . 'index.php', 'procore_integration_create_styles');