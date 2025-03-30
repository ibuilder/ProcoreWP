<?php
/**
 * Procore Integration Activation
 *
 * This file handles the plugin activation process.
 * It ensures that all required directories and files exist.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin activation function
 * 
 * Sets up the plugin's required file structure
 */
function procore_integration_activate() {
    // Create plugin directories
    $directories = [
        'assets',
        'assets/css',
        'includes',
    ];
    
    foreach ($directories as $directory) {
        $dir_path = PROCORE_INTEGRATION_PATH . $directory;
        if (!file_exists($dir_path)) {
            wp_mkdir_p($dir_path);
        }
    }
    
    // Ensure default CSS file exists
    $default_css_path = PROCORE_INTEGRATION_PATH . 'assets/css/procore-integration-default.css';
    $css_file_path = PROCORE_INTEGRATION_PATH . 'assets/css/procore-integration.css';
    
    if (!file_exists($css_file_path) && file_exists($default_css_path)) {
        copy($default_css_path, $css_file_path);
    }
}

// Register the activation hook
register_activation_hook(PROCORE_INTEGRATION_PATH . 'index.php', 'procore_integration_activate');