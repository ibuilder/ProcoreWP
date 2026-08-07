<?php
/**
 * Block editor integration.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Blocks;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Frontend\Shortcodes\Registrar as ShortcodeRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one block type with a variation per shortcode.
 *
 * A single block plus variations, rather than eighteen near-identical block
 * types, keeps the inserter populated while leaving exactly one place where
 * rendering happens. The block delegates to the shortcode handler, so markup,
 * escaping and caching behave identically in both editing surfaces.
 *
 * The editor script is hand-written ES5 with no build step, so the file that
 * ships is the file that was authored — no compiled bundle without source.
 */
final class Registrar {

	/**
	 * Block type name.
	 */
	public const BLOCK = 'procore-connect/procore';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize' ) );
	}

	/**
	 * Register the block type from its metadata.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$metadata = PROCORE_CONNECT_PATH . 'blocks/procore';

		if ( ! is_readable( $metadata . '/block.json' ) ) {
			return;
		}

		register_block_type(
			$metadata,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render the block by delegating to the matching shortcode.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered markup.
	 */
	public function render( array $attributes ): string {
		$tag = isset( $attributes['shortcode'] ) ? sanitize_key( (string) $attributes['shortcode'] ) : '';

		if ( '' === $tag || null === ShortcodeRegistrar::get( $tag ) ) {
			return '';
		}

		$atts  = array();
		$given = isset( $attributes['atts'] ) && is_array( $attributes['atts'] ) ? $attributes['atts'] : array();

		foreach ( $given as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			$atts[] = sprintf( '%s="%s"', sanitize_key( (string) $key ), esc_attr( (string) $value ) );
		}

		$shortcode = sprintf( '[%s %s]', $tag, implode( ' ', $atts ) );

		return do_shortcode( $shortcode );
	}

	/**
	 * Expose the shortcode registry to the editor script.
	 *
	 * @return void
	 */
	public function localize(): void {
		if ( ! Settings::get( 'enable_blocks', true ) ) {
			return;
		}

		$handle = generate_block_asset_handle( self::BLOCK, 'editorScript' );

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		$variations = array();

		foreach ( ShortcodeRegistrar::definitions() as $tag => $definition ) {
			$handler = new $definition['handler']( $definition );

			$variations[] = array(
				'name'        => str_replace( 'procore_', '', $tag ),
				'shortcode'   => $tag,
				'title'       => $this->variation_title( $tag, $definition ),
				'description' => (string) $definition['description'],
				'atts'        => array_keys( $handler->defaults() ),
			);
		}

		wp_localize_script(
			$handle,
			'procoreConnectBlocks',
			array(
				'variations' => $variations,
				'restUrl'    => rest_url( 'procore-connect/v1' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( \ProcoreConnect\Admin\Ajax::NONCE ),
			)
		);

		wp_set_script_translations( $handle, 'procore-connect', PROCORE_CONNECT_PATH . 'languages' );
	}

	/**
	 * Build a human readable variation title.
	 *
	 * @param string               $tag        Shortcode tag.
	 * @param array<string, mixed> $definition Shortcode definition.
	 * @return string Title.
	 */
	private function variation_title( string $tag, array $definition ): string {
		if ( '' !== (string) ( $definition['title'] ?? '' ) ) {
			return sprintf(
				/* translators: %s: shortcode display name, e.g. "RFIs". */
				__( 'Procore: %s', 'procore-connect' ),
				(string) $definition['title']
			);
		}

		return sprintf(
			/* translators: %s: shortcode display name. */
			__( 'Procore: %s', 'procore-connect' ),
			ucwords( str_replace( array( 'procore_', '_' ), array( '', ' ' ), $tag ) )
		);
	}
}
