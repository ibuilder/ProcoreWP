<?php
/**
 * Template resolution and rendering.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Locates and renders the partials that produce shortcode and block output.
 *
 * Markup lives in `templates/` and can be overridden by copying a file into
 * `yourtheme/procore-connect/`. This replaces the ProcoreWP 1.x advice to edit files
 * inside the plugin directory, which lost every customisation on update.
 */
final class Renderer {

	/**
	 * Directory inside a theme that holds overrides.
	 */
	private const THEME_DIR = 'procore-connect';

	/**
	 * Render a template to a string.
	 *
	 * @param string               $template Template name without the `.php` extension.
	 * @param array<string, mixed> $data     Variables exposed to the template as `$data`.
	 * @return string Rendered markup.
	 */
	public static function render( string $template, array $data = array() ): string {
		$path = self::locate( $template );

		if ( '' === $path ) {
			return '';
		}

		/**
		 * Filters the data passed to a Connect for Procore template.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, mixed> $data     Template data.
		 * @param string               $template Template name.
		 */
		$data = (array) apply_filters( 'procore_connect_template_data', $data, $template );

		ob_start();

		// The template reads from $data.
		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * Resolve a template name to a file path.
	 *
	 * Child theme, then parent theme, then the plugin's own defaults.
	 *
	 * @param string $template Template name without the `.php` extension.
	 * @return string Absolute path, or an empty string when no template exists.
	 */
	public static function locate( string $template ): string {
		$template = str_replace( array( '..', "\0" ), '', $template );
		$template = trim( preg_replace( '/[^a-z0-9_\-\/]/i', '', $template ) ?? '', '/' );

		if ( '' === $template ) {
			return '';
		}

		$file = $template . '.php';

		$candidates = array(
			trailingslashit( get_stylesheet_directory() ) . self::THEME_DIR . '/' . $file,
			trailingslashit( get_template_directory() ) . self::THEME_DIR . '/' . $file,
			PROCORE_CONNECT_PATH . 'templates/' . $file,
		);

		/**
		 * Filters the candidate paths considered when locating a template.
		 *
		 * @since 2.0.0
		 *
		 * @param array<int, string> $candidates Absolute candidate paths, in priority order.
		 * @param string             $template   Template name.
		 */
		$candidates = (array) apply_filters( 'procore_connect_template_candidates', $candidates, $template );

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return (string) $candidate;
			}
		}

		return '';
	}

	/**
	 * Render a user-facing notice in place of missing or failed content.
	 *
	 * The underlying API message is deliberately withheld from the front end:
	 * Procore errors routinely name accounts, projects and permissions.
	 *
	 * @param string $message Public message.
	 * @param string $type    Notice type, used as a modifier class.
	 * @return string Rendered markup.
	 */
	public static function notice( string $message, string $type = 'info' ): string {
		return sprintf(
			'<div class="procore-connect-notice procore-connect-notice--%1$s">%2$s</div>',
			esc_attr( sanitize_html_class( $type ) ),
			esc_html( $message )
		);
	}
}
