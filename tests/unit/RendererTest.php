<?php
/**
 * Template resolution, including the theme override chain.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Frontend\Renderer;

/**
 * Copying a template into a theme is a documented headline feature — it is how
 * users are told to customise markup safely instead of editing the plugin. It
 * is also a path resolver taking a user-supplied name, so both the precedence
 * and the traversal defence are worth asserting.
 */
final class RendererTest extends TestCase {

	/**
	 * Child theme directory used by the shims.
	 *
	 * @var string
	 */
	private $child;

	/**
	 * Parent theme directory used by the shims.
	 *
	 * @var string
	 */
	private $parent;

	/**
	 * Create throwaway theme directories.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->child  = get_stylesheet_directory() . '/procore-connect';
		$this->parent = get_template_directory() . '/procore-connect';

		foreach ( array( $this->child, $this->parent ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
		}

		$this->remove_overrides();
	}

	/**
	 * Remove anything a test wrote into the theme directories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->remove_overrides();

		parent::tearDown();
	}

	/**
	 * Delete the override files used by these tests.
	 *
	 * @return void
	 */
	private function remove_overrides(): void {
		foreach ( array( $this->child, $this->parent ) as $dir ) {
			$files = glob( $dir . '/*.php' );

			foreach ( is_array( $files ) ? $files : array() as $file ) {
				unlink( $file );
			}
		}
	}

	/**
	 * With no override present, the plugin's own template is used.
	 *
	 * @return void
	 */
	public function test_falls_back_to_the_plugin_template(): void {
		$located = Renderer::locate( 'collection' );

		$this->assertStringContainsString( 'templates/collection.php', str_replace( '\\', '/', $located ) );
		$this->assertFileExists( $located );
	}

	/**
	 * A parent theme override beats the plugin default.
	 *
	 * @return void
	 */
	public function test_parent_theme_overrides_the_plugin(): void {
		file_put_contents( $this->parent . '/collection.php', '<?php echo "parent";' );

		$this->assertSame(
			str_replace( '\\', '/', $this->parent . '/collection.php' ),
			str_replace( '\\', '/', Renderer::locate( 'collection' ) )
		);
	}

	/**
	 * A child theme override beats the parent theme.
	 *
	 * @return void
	 */
	public function test_child_theme_wins_over_parent(): void {
		file_put_contents( $this->parent . '/collection.php', '<?php echo "parent";' );
		file_put_contents( $this->child . '/collection.php', '<?php echo "child";' );

		$this->assertSame(
			str_replace( '\\', '/', $this->child . '/collection.php' ),
			str_replace( '\\', '/', Renderer::locate( 'collection' ) )
		);
	}

	/**
	 * An override actually renders, and receives the template data.
	 *
	 * @return void
	 */
	public function test_override_renders_and_receives_data(): void {
		file_put_contents( $this->child . '/collection.php', '<?php echo "OVERRIDE:" . $data["title"];' );

		$this->assertSame( 'OVERRIDE:Projects', Renderer::render( 'collection', array( 'title' => 'Projects' ) ) );
	}

	/**
	 * A directory traversal attempt must not escape the template directories.
	 *
	 * @return void
	 */
	public function test_refuses_directory_traversal(): void {
		foreach ( array( '../../wp-config', '..\\..\\wp-config', 'collection/../../../secret' ) as $attempt ) {
			$located = Renderer::locate( $attempt );

			$this->assertStringNotContainsString( 'wp-config', $located );
			$this->assertStringNotContainsString( 'secret', $located );
		}
	}

	/**
	 * An unknown template resolves to nothing rather than erroring.
	 *
	 * @return void
	 */
	public function test_unknown_template_resolves_to_nothing(): void {
		$this->assertSame( '', Renderer::locate( 'does-not-exist' ) );
		$this->assertSame( '', Renderer::render( 'does-not-exist', array() ) );
	}

	/**
	 * An empty name must not resolve to a directory.
	 *
	 * @return void
	 */
	public function test_empty_template_name_resolves_to_nothing(): void {
		$this->assertSame( '', Renderer::locate( '' ) );
	}

	/**
	 * Public notices must be escaped and carry a sanitized modifier class.
	 *
	 * @return void
	 */
	public function test_notice_escapes_its_message(): void {
		$html = Renderer::notice( '<script>alert(1)</script>', 'error' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'procore-connect-notice--error', $html );
	}
}
