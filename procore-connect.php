<?php
/**
 * Plugin Name:       Procore Connect
 * Plugin URI:        https://github.com/ibuilder/ProcoreWP
 * Description:       Connect WordPress to the Procore construction management platform. Display projects, teams, drawings, RFIs and more with shortcodes, blocks and a cached REST proxy.
 * Version:           2.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            ibuilder
 * Author URI:        https://github.com/ibuilder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       procore-connect
 * Domain Path:       /languages
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect;

defined( 'ABSPATH' ) || exit;

const VERSION = '2.0.1';

define( 'PROCORE_CONNECT_VERSION', VERSION );
define( 'PROCORE_CONNECT_FILE', __FILE__ );
define( 'PROCORE_CONNECT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROCORE_CONNECT_URL', plugin_dir_url( __FILE__ ) );
define( 'PROCORE_CONNECT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 autoloader for the Procore Connect namespace.
 *
 * Avoids a Composer dependency at runtime so the distributed plugin ships
 * without a vendor directory.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function autoload( string $class_name ): void {
	$prefix = __NAMESPACE__ . '\\';

	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$path     = PROCORE_CONNECT_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

spl_autoload_register( __NAMESPACE__ . '\\autoload' );

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'instance' ), 5 );
