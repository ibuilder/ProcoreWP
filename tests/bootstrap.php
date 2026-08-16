<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads a minimal set of WordPress function shims so the plugin's units can be
 * exercised without a WordPress installation. Only the functions the code under
 * test actually calls are defined, and each behaves like its Core counterpart
 * closely enough for the assertion it supports.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-shims.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'PROCORE_CONNECT_VERSION', '2.0.0' );
define( 'PROCORE_CONNECT_FILE', dirname( __DIR__ ) . '/connect-for-procore.php' );
define( 'PROCORE_CONNECT_PATH', dirname( __DIR__ ) . '/' );
define( 'PROCORE_CONNECT_URL', 'https://example.test/wp-content/plugins/connect-for-procore/' );
define( 'PROCORE_CONNECT_BASENAME', 'connect-for-procore/connect-for-procore.php' );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'ProcoreConnect\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );

		if ( 0 === strpos( $relative, 'Tests\\' ) ) {
			$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $relative, strlen( 'Tests\\' ) ) ) . '.php';
		} else {
			$path = PROCORE_CONNECT_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		}

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
