<?php
/**
 * Front-end asset loading.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Frontend;

use ProcoreWP\Admin\Settings;
use ProcoreWP\Frontend\Shortcodes\Registrar;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin stylesheet only on pages that actually use it.
 *
 * ProcoreWP 1.x enqueued its stylesheet on every front-end request regardless
 * of content. Here the queued post objects are scanned up front so the style
 * lands in the document head where possible, with a footer fallback for output
 * produced by widgets, blocks or template calls that the scan cannot see.
 */
final class Assets {

	/**
	 * Registered handle for the plugin stylesheet.
	 */
	public const HANDLE = 'procorewp';

	/**
	 * Whether something on this request has rendered plugin output.
	 *
	 * @var bool
	 */
	private static $needed = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );
		add_action( 'wp_footer', array( $this, 'late_enqueue' ), 1 );
	}

	/**
	 * Flag that plugin output has been rendered on this request.
	 *
	 * @return void
	 */
	public static function mark_needed(): void {
		self::$needed = true;
	}

	/**
	 * Register, and where possible enqueue, the stylesheet.
	 *
	 * @return void
	 */
	public function register_styles(): void {
		if ( ! Settings::get( 'load_styles', true ) ) {
			return;
		}

		wp_register_style(
			self::HANDLE,
			PROCOREWP_URL . 'assets/css/procorewp.css',
			array(),
			PROCOREWP_VERSION
		);

		$custom_css = trim( (string) Settings::get( 'custom_css', '' ) );

		if ( '' !== $custom_css ) {
			wp_add_inline_style( self::HANDLE, wp_strip_all_tags( $custom_css ) );
		}

		if ( $this->queried_content_uses_plugin() ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/**
	 * Enqueue the stylesheet late when output appeared after the head was sent.
	 *
	 * @return void
	 */
	public function late_enqueue(): void {
		if ( ! self::$needed || ! Settings::get( 'load_styles', true ) ) {
			return;
		}

		if ( wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE );
	}

	/**
	 * Whether any queried post contains a ProcoreWP shortcode or block.
	 *
	 * @return bool True when plugin output is expected.
	 */
	private function queried_content_uses_plugin(): bool {
		global $wp_query;

		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return false;
		}

		$tags = array_keys( Registrar::definitions() );

		foreach ( $wp_query->posts as $post ) {
			if ( ! isset( $post->post_content ) || ! is_string( $post->post_content ) ) {
				continue;
			}

			if ( false !== strpos( $post->post_content, 'wp:procorewp/' ) ) {
				return true;
			}

			foreach ( $tags as $tag ) {
				if ( has_shortcode( $post->post_content, $tag ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
