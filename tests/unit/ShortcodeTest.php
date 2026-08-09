<?php
/**
 * End-to-end shortcode rendering.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Tests\unit;

use ProcoreConnect\Admin\Settings;
use ProcoreConnect\Frontend\Shortcodes\Registrar;
use ProcoreConnect\Support\Format;

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
		$this->assertStringContainsString( 'procore-connect-table', $html );
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
	 * A column that renders nothing for every row must not be shown.
	 *
	 * Email suppression is on by default, so `[procore_team]` used to emit an
	 * Email header and a blank cell for every member.
	 *
	 * @return void
	 */
	public function test_drops_a_column_that_is_empty_for_every_row(): void {
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
		$this->assertStringNotContainsString( '>Email<', $html );
	}

	/**
	 * A column with data must survive the empty-column filter.
	 *
	 * @return void
	 */
	public function test_keeps_columns_that_carry_a_value(): void {
		Settings::set( 'suppress_emails', false );

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
				'show_email' => 'true',
			)
		);

		$this->assertStringContainsString( '>Email<', $html );
		$this->assertStringContainsString( 'dana@example.com', $html );
	}

	/**
	 * A column the author named must be rendered even when it is empty.
	 *
	 * The empty-column filter exists to tidy a default column set nobody chose.
	 * An explicit `columns=` is a request, and silently dropping part of it
	 * leaves the author with no header, no cell and no explanation.
	 *
	 * @return void
	 */
	public function test_keeps_an_empty_column_the_author_asked_for(): void {
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

		// Email suppression is on, so this column renders blank for every row.
		$html = $this->shortcode( 'procore_team' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
				'columns'    => 'name,email_address',
			)
		);

		$this->assertStringContainsString( '>Email<', $html );
		$this->assertStringNotContainsString( 'dana@example.com', $html );
	}

	/**
	 * A column no record populates must drop, not render a wall of dashes.
	 *
	 * A missing value renders as a placeholder dash rather than as nothing, so
	 * a filter that tested for an empty string would keep the column and only
	 * ever be able to drop a suppressed email address.
	 *
	 * @return void
	 */
	public function test_drops_a_default_column_no_record_populates(): void {
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

		$html = $this->shortcode( 'procore_rfis' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
			)
		);

		$this->assertStringContainsString( 'RFI-001', $html );
		$this->assertStringNotContainsString( '>Due<', $html );
		$this->assertStringNotContainsString( Format::PLACEHOLDER, $html );
	}

	/**
	 * The same column must survive or drop regardless of its neighbours.
	 *
	 * Requesting one empty column used to keep it, because the never-empty
	 * fallback restored the whole list; adding a populated column alongside it
	 * dropped it again. Identical data, opposite outcome.
	 *
	 * @return void
	 */
	public function test_column_visibility_does_not_depend_on_other_columns(): void {
		$member = array(
			'id'            => 3,
			'name'          => 'Dana Reed',
			'job_title'     => 'PM',
			'email_address' => 'dana@example.com',
		);

		$this->client_returning( array( $this->response( array( $member ) ) ) );

		$alone = $this->shortcode( 'procore_team' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
				'columns'    => 'email_address',
			)
		);

		$this->client_returning( array( $this->response( array( $member ) ) ) );

		$paired = $this->shortcode( 'procore_team' )->render(
			array(
				'project_id' => '5',
				'company_id' => '9',
				'columns'    => 'name,email_address',
			)
		);

		$this->assertStringContainsString( '>Email<', $alone );
		$this->assertStringContainsString( '>Email<', $paired );
	}

	/**
	 * A field outside the allow-list must be refused.
	 *
	 * @return void
	 */
	public function test_project_data_rejects_fields_outside_the_allow_list(): void {
		$GLOBALS['procore_connect_test_can'] = true;

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
		$GLOBALS['procore_connect_test_can'] = true;

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
		$GLOBALS['procore_connect_test_can'] = true;

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
