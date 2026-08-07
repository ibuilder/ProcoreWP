<?php
/**
 * End-to-end shortcode rendering.
 *
 * @package ProcoreWP
 */

declare( strict_types = 1 );

namespace ProcoreWP\Tests\unit;

use ProcoreWP\Admin\Settings;
use ProcoreWP\Frontend\Shortcodes\Registrar;

/**
 * Renders each shortcode against canned API payloads to confirm attribute
 * handling, escaping and failure behaviour.
 */
final class ShortcodeTest extends TestCase {

	/**
	 * Instantiate a shortcode handler from the registry.
	 *
	 * @param string $tag Shortcode tag.
	 * @return object Handler instance.
	 */
	private function shortcode( string $tag ) {
		$definition = Registrar::get( $tag );

		$this->assertNotNull( $definition, $tag . ' is not registered' );

		$handler = $definition['handler'];

		return new $handler( $definition );
	}

	/**
	 * The project list must render a table of records.
	 *
	 * @return void
	 */
	public function test_project_list_renders_a_table(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'         => 1,
							'name'       => 'Harbour Bridge',
							'city'       => 'Sydney',
							'state_code' => 'NSW',
							'active'     => true,
						),
						array(
							'id'         => 2,
							'name'       => 'Dock Yard',
							'city'       => 'Perth',
							'state_code' => 'WA',
							'active'     => false,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render( array( 'company_id' => '9' ) );

		$this->assertStringContainsString( 'Harbour Bridge', $html );
		$this->assertStringContainsString( 'Sydney, NSW', $html );
		$this->assertStringContainsString( 'procorewp-table', $html );
	}

	/**
	 * `active_only` must filter out inactive projects.
	 *
	 * @return void
	 */
	public function test_active_only_filters_inactive_projects(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'     => 1,
							'name'   => 'Live Site',
							'active' => true,
						),
						array(
							'id'     => 2,
							'name'   => 'Closed Site',
							'active' => false,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render(
			array(
				'company_id'  => '9',
				'active_only' => 'true',
			)
		);

		$this->assertStringContainsString( 'Live Site', $html );
		$this->assertStringNotContainsString( 'Closed Site', $html );
	}

	/**
	 * The legacy `id` attribute must still resolve to the project.
	 *
	 * ProcoreWP 1.x used `id` throughout, so existing pages depend on it.
	 *
	 * @return void
	 */
	public function test_legacy_id_attribute_still_works(): void {
		$calls = array();
		$this->client_returning(
			array(
				$this->response(
					array(
						'id'   => 55,
						'name' => 'Legacy Project',
						'city' => 'Leeds',
					)
				),
			),
			$calls
		);

		$html = $this->shortcode( 'procore_project' )->render(
			array(
				'id'         => '55',
				'company_id' => '9',
			)
		);

		$this->assertStringContainsString( '/rest/v1.0/projects/55', $calls[0]['url'] );
		$this->assertStringContainsString( 'Legacy Project', $html );
	}

	/**
	 * The 1.x `sort_by` and `sort_order` attributes must still sort.
	 *
	 * @return void
	 */
	public function test_legacy_sort_attributes_still_work(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'     => 1,
							'name'   => 'Bravo',
							'active' => true,
						),
						array(
							'id'     => 2,
							'name'   => 'Alpha',
							'active' => true,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render(
			array(
				'company_id' => '9',
				'sort_by'    => 'name',
				'sort_order' => 'desc',
			)
		);

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Bravo' ) );
	}

	/**
	 * The 1.x `show_details="false"` must still reduce the list to ID and name.
	 *
	 * @return void
	 */
	public function test_legacy_show_details_false_trims_columns(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'         => 1,
							'name'       => 'Harbour Bridge',
							'city'       => 'Sydney',
							'state_code' => 'NSW',
							'active'     => true,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render(
			array(
				'company_id'   => '9',
				'show_details' => 'false',
			)
		);

		$this->assertStringContainsString( 'Harbour Bridge', $html );
		$this->assertStringNotContainsString( 'Sydney', $html );
	}

	/**
	 * Omitting `show_details` now shows the full column set.
	 *
	 * @return void
	 */
	public function test_columns_are_complete_by_default(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'         => 1,
							'name'       => 'Harbour Bridge',
							'city'       => 'Sydney',
							'state_code' => 'NSW',
							'active'     => true,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render( array( 'company_id' => '9' ) );

		$this->assertStringContainsString( 'Sydney, NSW', $html );
	}

	/**
	 * Hostile record content must be escaped, not rendered.
	 *
	 * @return void
	 */
	public function test_record_content_is_escaped(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'     => 1,
							'name'   => '<img src=x onerror=alert(1)>',
							'active' => true,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render( array( 'company_id' => '9' ) );

		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;img', $html );
	}

	/**
	 * Team email addresses must not appear on a public page by default.
	 *
	 * @return void
	 */
	public function test_team_hides_emails_by_default(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'            => 3,
							'name'          => 'Dana Reed',
							'job_title'     => 'PM',
							'email_address' => 'dana@example.com',
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_team' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
			)
		);

		$this->assertStringContainsString( 'Dana Reed', $html );
		$this->assertStringNotContainsString( 'dana@example.com', $html );
	}

	/**
	 * A field outside the allow-list must be refused.
	 *
	 * @return void
	 */
	public function test_project_data_rejects_fields_outside_the_allow_list(): void {
		$GLOBALS['procorewp_test_can'] = true;

		$this->client_returning(
			array(
				$this->response(
					array(
						'id'      => 1,
						'name'    => 'Site',
						'company' => array( 'secret' => 'x' ),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_data' )->render(
			array(
				'id'         => '1',
				'company_id' => '9',
				'field'      => 'company',
			)
		);

		$this->assertStringContainsString( 'not available for display', $html );
	}

	/**
	 * An allow-listed field must render with an inferred format.
	 *
	 * @return void
	 */
	public function test_project_data_formats_dates(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						'id'         => 1,
						'start_date' => '2026-03-09',
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_data' )->render(
			array(
				'id'         => '1',
				'company_id' => '9',
				'field'      => 'start_date',
				'label'      => 'Start',
			)
		);

		$this->assertStringContainsString( 'Start', $html );
		$this->assertStringContainsString( '2026', $html );
	}

	/**
	 * The generic reader must refuse endpoints that are not published.
	 *
	 * @return void
	 */
	public function test_data_shortcode_refuses_unpublished_endpoints(): void {
		$GLOBALS['procorewp_test_can'] = true;

		$this->client_returning( array() );

		$html = $this->shortcode( 'procore_data' )->render(
			array(
				'endpoint'   => 'me',
				'company_id' => '9',
			)
		);

		$this->assertStringContainsString( 'not available to shortcodes', $html );
	}

	/**
	 * The generic reader must render a published endpoint.
	 *
	 * @return void
	 */
	public function test_data_shortcode_renders_a_published_endpoint(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'      => 1,
							'number'  => 'RFI-001',
							'subject' => 'Slab depth',
							'status'  => 'open',
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_data' )->render(
			array(
				'endpoint'   => 'rfis',
				'project_id' => '5',
				'company_id' => '9',
			)
		);

		$this->assertStringContainsString( 'RFI-001', $html );
		$this->assertStringContainsString( 'Slab depth', $html );
	}

	/**
	 * Only administrators may see the underlying API error message.
	 *
	 * @return void
	 */
	public function test_api_errors_are_not_leaked_to_visitors(): void {
		$this->client_returning(
			array( $this->response( array( 'message' => 'User 8891 lacks Directory permission' ), 403 ) )
		);

		$html = $this->shortcode( 'procore_project_list' )->render( array( 'company_id' => '9' ) );

		$this->assertStringNotContainsString( '8891', $html );
		$this->assertStringContainsString( 'temporarily unavailable', $html );
	}

	/**
	 * Administrators must see the underlying message so they can act on it.
	 *
	 * @return void
	 */
	public function test_administrators_see_the_real_error(): void {
		$GLOBALS['procorewp_test_can'] = true;

		$this->client_returning(
			array( $this->response( array( 'message' => 'Directory permission required' ), 403 ) )
		);

		$html = $this->shortcode( 'procore_project_list' )->render( array( 'company_id' => '9' ) );

		$this->assertStringContainsString( 'Directory permission required', $html );
	}

	/**
	 * An empty result set must render the configured empty message.
	 *
	 * @return void
	 */
	public function test_empty_results_render_a_message(): void {
		$this->client_returning( array( $this->response( array() ) ) );

		$html = $this->shortcode( 'procore_rfis' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
				'empty_text' => 'No open RFIs.',
			)
		);

		$this->assertStringContainsString( 'No open RFIs.', $html );
	}

	/**
	 * The `limit` attribute must cap the rendered rows.
	 *
	 * @return void
	 */
	public function test_limit_caps_rendered_rows(): void {
		$this->client_returning(
			array(
				$this->response(
					array(
						array(
							'id'     => 1,
							'name'   => 'One',
							'active' => true,
						),
						array(
							'id'     => 2,
							'name'   => 'Two',
							'active' => true,
						),
						array(
							'id'     => 3,
							'name'   => 'Three',
							'active' => true,
						),
					)
				),
			)
		);

		$html = $this->shortcode( 'procore_project_list' )->render(
			array(
				'company_id' => '9',
				'limit'      => '2',
			)
		);

		$this->assertStringContainsString( 'One', $html );
		$this->assertStringContainsString( 'Two', $html );
		$this->assertStringNotContainsString( 'Three', $html );
	}

	/**
	 * The default company must be used when the shortcode omits one.
	 *
	 * @return void
	 */
	public function test_falls_back_to_the_default_company(): void {
		Settings::set( 'default_company_id', 777 );

		$calls = array();
		$this->client_returning( array( $this->response( array() ) ), $calls );

		$this->shortcode( 'procore_project_list' )->render( array() );

		$this->assertSame( '777', $calls[0]['args']['headers']['Procore-Company-Id'] );
	}

	/**
	 * Every registered shortcode must be renderable without fatal errors.
	 *
	 * @return void
	 */
	public function test_every_shortcode_renders(): void {
		foreach ( array_keys( Registrar::definitions() ) as $tag ) {
			$this->client_returning( array( $this->response( array() ) ) );

			$html = $this->shortcode( $tag )->render(
				array(
					'company_id' => '9',
					'project_id' => '5',
					'endpoint'   => 'rfis',
					'field'      => 'name',
				)
			);

			$this->assertIsString( $html, $tag . ' did not render a string' );
		}
	}
}
