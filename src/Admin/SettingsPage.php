<?php
/**
 * The plugin's admin screens.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

namespace ProcoreConnect\Admin;

use ProcoreConnect\Api\Cache;
use ProcoreConnect\Api\Client;
use ProcoreConnect\Api\Endpoints;
use ProcoreConnect\Api\Environment;
use ProcoreConnect\Api\TokenStore;
use ProcoreConnect\Frontend\Shortcodes\Registrar;
use ProcoreConnect\Support\Encryption;
use ProcoreConnect\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the tabbed Procore settings screen.
 *
 * The client secret is never written back into the form: the field shows a
 * masked placeholder and an empty submission leaves the stored value untouched.
 */
final class SettingsPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'procore-connect';

	/**
	 * Capability required to view and change settings.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the top-level menu entry.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Connect for Procore', 'connect-for-procore' ),
			__( 'Procore', 'connect-for-procore' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-building',
			80
		);
	}

	/**
	 * Register the settings option with its sanitize callback.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Enqueue the admin script on this plugin's screens only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'procore-connect-admin',
			PROCORE_CONNECT_URL . 'assets/js/admin.js',
			array( 'wp-i18n' ),
			PROCORE_CONNECT_VERSION,
			true
		);

		wp_localize_script(
			'procore-connect-admin',
			'procoreConnectAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'strings' => array(
					'testing'   => __( 'Running checks…', 'connect-for-procore' ),
					'clearing'  => __( 'Clearing…', 'connect-for-procore' ),
					'loading'   => __( 'Loading…', 'connect-for-procore' ),
					'failed'    => __( 'The request failed. Check your connection and try again.', 'connect-for-procore' ),
					'copied'    => __( 'Copied', 'connect-for-procore' ),
					'confirmed' => __( 'Disconnect this site from Procore?', 'connect-for-procore' ),
					'skipped'   => __( 'Skipped', 'connect-for-procore' ),
				),
			)
		);

		wp_enqueue_style(
			'procore-connect-admin',
			PROCORE_CONNECT_URL . 'assets/css/admin.css',
			array(),
			PROCORE_CONNECT_VERSION
		);
	}

	/**
	 * Render the current tab.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'connect-for-procore' ), '', array( 'response' => 403 ) );
		}

		$tabs = array(
			'connection' => __( 'Connection', 'connect-for-procore' ),
			'display'    => __( 'Display', 'connect-for-procore' ),
			'cache'      => __( 'Cache', 'connect-for-procore' ),
			'shortcodes' => __( 'Shortcodes', 'connect-for-procore' ),
			'status'     => __( 'Status', 'connect-for-procore' ),
			'tools'      => __( 'Tools', 'connect-for-procore' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection';
		$active = array_key_exists( $active, $tabs ) ? $active : 'connection';

		echo '<div class="wrap procore-connect-admin">';
		echo '<h1>' . esc_html__( 'Connect for Procore', 'connect-for-procore' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $slug ) ),
				$slug === $active ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';

		switch ( $active ) {
			case 'display':
				$this->render_form( array( $this, 'fields_display' ) );
				break;

			case 'cache':
				$this->render_form( array( $this, 'fields_cache' ) );
				break;

			case 'shortcodes':
				$this->render_shortcodes();
				break;

			case 'status':
				$this->render_status();
				break;

			case 'tools':
				$this->render_form( array( $this, 'fields_tools' ) );
				break;

			default:
				$this->render_connection();
				break;
		}//end switch

		echo '</div>';
	}

	/**
	 * Wrap a set of fields in the options form.
	 *
	 * @param callable $fields Callback that prints the table rows.
	 * @return void
	 */
	private function render_form( callable $fields ): void {
		echo '<form method="post" action="options.php">';
		settings_fields( Settings::GROUP );
		echo '<table class="form-table" role="presentation"><tbody>';
		call_user_func( $fields );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Connection tab.
	 *
	 * @return void
	 */
	private function render_connection(): void {
		$this->render_form( array( $this, 'fields_connection' ) );

		$auth_mode = (string) Settings::get( 'auth_mode', 'client_credentials' );

		if ( 'authorization_code' === $auth_mode ) {
			echo '<hr>';
			echo '<h2>' . esc_html__( 'Authorize this site', 'connect-for-procore' ) . '</h2>';
			echo '<p>' . esc_html__( 'Register this exact redirect URI in the Procore Developer Portal before connecting:', 'connect-for-procore' ) . '</p>';

			printf(
				'<p><code class="procore-connect-copy" data-procore-connect-copy="%1$s">%1$s</code> <button type="button" class="button button-small" data-procore-connect-copy-button>%2$s</button></p>',
				esc_attr( Environment::redirect_uri() ),
				esc_html__( 'Copy', 'connect-for-procore' )
			);

			echo '<p>';

			if ( '' !== TokenStore::refresh_token() ) {
				printf(
					'<button type="button" class="button" id="procore-connect-disconnect">%s</button>',
					esc_html__( 'Disconnect from Procore', 'connect-for-procore' )
				);
			} else {
				printf(
					'<a class="button button-primary" href="%s">%s</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=procore_connect_oauth_connect' ), OAuthController::NONCE ) ),
					esc_html__( 'Connect to Procore', 'connect-for-procore' )
				);
			}

			echo '</p>';
		}//end if

		echo '<hr>';
		echo '<h2>' . esc_html__( 'Verify the connection', 'connect-for-procore' ) . '</h2>';
		echo '<p>' . esc_html__( 'Runs through authentication, company access and every endpoint the shortcodes use, so you can see exactly which Procore tool permissions your credentials have.', 'connect-for-procore' ) . '</p>';
		printf(
			'<p><button type="button" class="button button-primary" id="procore-connect-test">%s</button></p>',
			esc_html__( 'Test connection', 'connect-for-procore' )
		);
		echo '<div id="procore-connect-test-results" aria-live="polite"></div>';
	}

	/**
	 * Print the Connection tab fields.
	 *
	 * @return void
	 */
	private function fields_connection(): void {
		$settings   = Settings::all();
		$constants  = Settings::credentials_are_constants();
		$has_secret = '' !== Settings::client_secret();

		$this->row(
			__( 'Authentication', 'connect-for-procore' ),
			function () use ( $settings ) {
				$modes = array(
					'client_credentials' => __( 'Service Account (Client Credentials) — recommended', 'connect-for-procore' ),
					'authorization_code' => __( 'User Account (Authorization Code)', 'connect-for-procore' ),
				);

				foreach ( $modes as $value => $label ) {
					printf(
						'<label style="display:block;margin-bottom:.35rem"><input type="radio" name="%1$s[auth_mode]" value="%2$s" %3$s> %4$s</label>',
						esc_attr( Settings::OPTION ),
						esc_attr( $value ),
						checked( $settings['auth_mode'], $value, false ),
						esc_html( $label )
					);
				}

				echo '<p class="description">' . esc_html__( 'A service account keeps working when staff change and is installed by a Procore company administrator from the App Marketplace. A user connection inherits one person\'s permissions and breaks if their access is revoked.', 'connect-for-procore' ) . '</p>';
			}
		);

		$this->row(
			__( 'Environment', 'connect-for-procore' ),
			function () use ( $settings ) {
				printf( '<select name="%s[environment]">', esc_attr( Settings::OPTION ) );

				foreach ( Environment::choices() as $value => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						selected( $settings['environment'], $value, false ),
						esc_html( $label )
					);
				}

				echo '</select>';
				echo '<p class="description">' . esc_html__( 'Each environment needs its own credentials; tokens are not shared between them.', 'connect-for-procore' ) . '</p>';

				printf(
					'<p><label>%1$s<br><input type="url" class="regular-text code" name="%2$s[custom_login_url]" value="%3$s" placeholder="https://login.procore.com"></label></p>',
					esc_html__( 'Custom login host', 'connect-for-procore' ),
					esc_attr( Settings::OPTION ),
					esc_attr( (string) $settings['custom_login_url'] )
				);

				printf(
					'<p><label>%1$s<br><input type="url" class="regular-text code" name="%2$s[custom_api_url]" value="%3$s" placeholder="https://api.procore.com"></label></p>',
					esc_html__( 'Custom API host', 'connect-for-procore' ),
					esc_attr( Settings::OPTION ),
					esc_attr( (string) $settings['custom_api_url'] )
				);

				echo '<p class="description">' . esc_html__( 'Only used when the environment is set to Custom, for regional and federal zones.', 'connect-for-procore' ) . '</p>';
			}
		);

		if ( $constants ) {
			$this->row(
				__( 'Credentials', 'connect-for-procore' ),
				function () {
					echo '<p>' . esc_html__( 'Credentials are supplied by PROCORE_CONNECT_CLIENT_ID and PROCORE_CONNECT_CLIENT_SECRET in wp-config.php and are not stored in the database. Remove those constants to manage credentials here.', 'connect-for-procore' ) . '</p>';
				}
			);
		} else {
			$this->row(
				__( 'Client ID', 'connect-for-procore' ),
				function () use ( $settings ) {
					printf(
						'<input type="text" class="regular-text code" name="%1$s[client_id]" value="%2$s" autocomplete="off" spellcheck="false">',
						esc_attr( Settings::OPTION ),
						esc_attr( (string) $settings['client_id'] )
					);
				}
			);

			$this->row(
				__( 'Client Secret', 'connect-for-procore' ),
				function () use ( $has_secret ) {
					printf(
						'<input type="password" class="regular-text code" name="%1$s[client_secret]" value="" autocomplete="new-password" spellcheck="false" placeholder="%2$s">',
						esc_attr( Settings::OPTION ),
						esc_attr( $has_secret ? Encryption::mask( Settings::client_secret() ) : __( 'Not set', 'connect-for-procore' ) )
					);

					echo '<p class="description">' . esc_html__( 'The stored secret is never shown. Leave this blank to keep the current value.', 'connect-for-procore' ) . '</p>';

					if ( $has_secret ) {
						printf(
							'<p><label><input type="checkbox" name="%1$s[clear_client_secret]" value="1"> %2$s</label></p>',
							esc_attr( Settings::OPTION ),
							esc_html__( 'Delete the stored secret', 'connect-for-procore' )
						);
					}

					echo '<p class="description">' . esc_html__( 'For the strongest protection, define PROCORE_CONNECT_CLIENT_SECRET in wp-config.php instead of storing it here.', 'connect-for-procore' ) . '</p>';
				}
			);
		}//end if

		$this->row(
			__( 'Default company', 'connect-for-procore' ),
			function () use ( $settings ) {
				printf(
					'<input type="number" min="0" step="1" class="small-text" name="%1$s[default_company_id]" value="%2$d" id="procore-connect-company-id">',
					esc_attr( Settings::OPTION ),
					absint( $settings['default_company_id'] )
				);

				printf(
					' <button type="button" class="button button-small" id="procore-connect-load-companies">%s</button>',
					esc_html__( 'Look up companies', 'connect-for-procore' )
				);

				echo '<div id="procore-connect-company-list"></div>';
				echo '<p class="description">' . esc_html__( 'Used whenever a shortcode omits company_id. Also visible in your Procore URL: app.procore.com/companies/XXXX/.', 'connect-for-procore' ) . '</p>';
			}
		);

		$this->row(
			__( 'Default project', 'connect-for-procore' ),
			function () use ( $settings ) {
				printf(
					'<input type="number" min="0" step="1" class="small-text" name="%1$s[default_project_id]" value="%2$d">',
					esc_attr( Settings::OPTION ),
					absint( $settings['default_project_id'] )
				);

				echo '<p class="description">' . esc_html__( 'Optional. Used whenever a project-scoped shortcode omits project_id.', 'connect-for-procore' ) . '</p>';
			}
		);
	}

	/**
	 * Print the Display tab fields.
	 *
	 * @return void
	 */
	private function fields_display(): void {
		$settings = Settings::all();

		$this->checkbox(
			__( 'Privacy', 'connect-for-procore' ),
			'suppress_emails',
			(bool) $settings['suppress_emails'],
			__( 'Never output email addresses on the front end', 'connect-for-procore' ),
			__( 'Recommended. Procore project directories contain personal data; publishing them exposes staff and subcontractors to harvesting. When this is off, addresses are still obfuscated and each shortcode must additionally opt in with show_email="true".', 'connect-for-procore' )
		);

		$this->text(
			__( 'Date format', 'connect-for-procore' ),
			'date_format',
			(string) $settings['date_format'],
			__( 'Leave blank to use the site date format.', 'connect-for-procore' ),
			(string) get_option( 'date_format', 'F j, Y' )
		);

		$this->text(
			__( 'Currency symbol', 'connect-for-procore' ),
			'currency_symbol',
			(string) $settings['currency_symbol'],
			__( 'Prefixed to monetary values such as change order totals.', 'connect-for-procore' ),
			'$'
		);

		$this->checkbox(
			__( 'Stylesheet', 'connect-for-procore' ),
			'load_styles',
			(bool) $settings['load_styles'],
			__( 'Load the Connect for Procore stylesheet', 'connect-for-procore' ),
			__( 'The stylesheet loads only on pages that actually contain Connect for Procore output. Turn it off entirely if your theme styles the markup itself.', 'connect-for-procore' )
		);

		$this->row(
			__( 'Custom CSS', 'connect-for-procore' ),
			function () use ( $settings ) {
				printf(
					'<textarea name="%1$s[custom_css]" rows="10" class="large-text code" spellcheck="false">%2$s</textarea>',
					esc_attr( Settings::OPTION ),
					esc_textarea( (string) $settings['custom_css'] )
				);

				echo '<p class="description">' . esc_html__( 'Added inline after the plugin stylesheet. This survives plugin updates — editing files inside the plugin directory does not.', 'connect-for-procore' ) . '</p>';
			}
		);

		$this->row(
			__( 'Template overrides', 'connect-for-procore' ),
			function () {
				echo '<p>' . esc_html__( 'To change the markup, copy a file from the plugin\'s templates directory into your theme:', 'connect-for-procore' ) . '</p>';
				printf(
					'<p><code>%s</code> &rarr; <code>%s</code></p>',
					esc_html( 'wp-content/plugins/connect-for-procore/templates/collection.php' ),
					esc_html( 'wp-content/themes/' . get_stylesheet() . '/procore-connect/collection.php' )
				);
				echo '<p class="description">' . esc_html__( 'Available templates: collection, record, field, image, map.', 'connect-for-procore' ) . '</p>';
			}
		);
	}

	/**
	 * Print the Cache tab fields.
	 *
	 * @return void
	 */
	private function fields_cache(): void {
		$settings = Settings::all();

		$this->checkbox(
			__( 'Response cache', 'connect-for-procore' ),
			'enable_cache',
			(bool) $settings['enable_cache'],
			__( 'Cache Procore responses', 'connect-for-procore' ),
			__( 'Strongly recommended. Procore enforces both an hourly and a ten-second rate limit; an uncached page will exhaust the quota quickly. When caching is on, a Procore outage serves the last good response instead of an error.', 'connect-for-procore' )
		);

		$this->number(
			__( 'Minimum cache lifetime', 'connect-for-procore' ),
			'cache_floor',
			(int) $settings['cache_floor'],
			30,
			DAY_IN_SECONDS,
			__( 'Seconds. Shortcodes may request a longer lifetime but never a shorter one, so a single page cannot be configured into a rate-limit breach.', 'connect-for-procore' )
		);

		$this->number(
			__( 'Records per request', 'connect-for-procore' ),
			'per_page',
			(int) $settings['per_page'],
			1,
			2000,
			__( 'Procore recommends keeping this at or below 2000.', 'connect-for-procore' )
		);

		$this->number(
			__( 'Request timeout', 'connect-for-procore' ),
			'request_timeout',
			(int) $settings['request_timeout'],
			5,
			60,
			__( 'Seconds to wait for Procore before giving up.', 'connect-for-procore' )
		);

		$this->row(
			__( 'Cached responses', 'connect-for-procore' ),
			function () {
				$stats = Cache::stats();

				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %d: number of cached responses. */
							_n( '%d cached response.', '%d cached responses.', (int) $stats['total'], 'connect-for-procore' ),
							(int) $stats['total']
						)
					)
				);

				printf(
					'<p><button type="button" class="button" id="procore-connect-clear-cache">%s</button> <span id="procore-connect-cache-message"></span></p>',
					esc_html__( 'Clear cache now', 'connect-for-procore' )
				);
			}
		);
	}

	/**
	 * Print the Tools tab fields.
	 *
	 * @return void
	 */
	private function fields_tools(): void {
		$settings = Settings::all();

		$this->checkbox(
			__( 'REST proxy', 'connect-for-procore' ),
			'enable_rest',
			(bool) $settings['enable_rest'],
			__( 'Expose cached Procore data at /wp-json/procore-connect/v1/', 'connect-for-procore' ),
			__( 'Lets themes and JavaScript read Procore data without ever seeing your credentials. Only endpoints published in the registry are reachable.', 'connect-for-procore' )
		);

		$this->row(
			__( 'REST access', 'connect-for-procore' ),
			function () use ( $settings ) {
				$levels = array(
					'public'    => __( 'Anyone', 'connect-for-procore' ),
					'logged_in' => __( 'Logged-in users', 'connect-for-procore' ),
					'editor'    => __( 'Users who can edit posts', 'connect-for-procore' ),
				);

				printf( '<select name="%s[rest_access]">', esc_attr( Settings::OPTION ) );

				foreach ( $levels as $value => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						selected( $settings['rest_access'], $value, false ),
						esc_html( $label )
					);
				}

				echo '</select>';
			}
		);

		$this->checkbox(
			__( 'Block editor', 'connect-for-procore' ),
			'enable_blocks',
			(bool) $settings['enable_blocks'],
			__( 'Register Connect for Procore blocks', 'connect-for-procore' ),
			__( 'Adds a block for each shortcode with a live preview. Shortcodes keep working either way.', 'connect-for-procore' )
		);

		$this->checkbox(
			__( 'Diagnostics', 'connect-for-procore' ),
			'enable_logging',
			(bool) $settings['enable_logging'],
			__( 'Record API diagnostics', 'connect-for-procore' ),
			__( 'Keeps the last 50 events for the Status tab. Credentials and tokens are redacted before anything is written.', 'connect-for-procore' )
		);

		$this->checkbox(
			__( 'Uninstall', 'connect-for-procore' ),
			'keep_data',
			(bool) $settings['keep_data'],
			__( 'Keep settings and cached data when the plugin is deleted', 'connect-for-procore' ),
			__( 'Off by default, so deleting the plugin removes its credentials, tokens and cache.', 'connect-for-procore' )
		);
	}

	/**
	 * Render the Shortcodes reference tab.
	 *
	 * @return void
	 */
	private function render_shortcodes(): void {
		echo '<p>' . esc_html__( 'Every shortcode below accepts the shared attributes company_id, project_id, limit, page, orderby, order, columns, template, class, title, cache and empty_text. The legacy id attribute is still accepted as an alias for project_id.', 'connect-for-procore' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Shortcode', 'connect-for-procore' ) . '</th>';
		echo '<th>' . esc_html__( 'What it shows', 'connect-for-procore' ) . '</th>';
		echo '<th>' . esc_html__( 'Procore permission needed', 'connect-for-procore' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( Registrar::definitions() as $tag => $definition ) {
			$endpoint   = Endpoints::get( (string) $definition['endpoint'] );
			$permission = null !== $endpoint ? (string) $endpoint['permission'] : __( 'Depends on the endpoint attribute', 'connect-for-procore' );

			echo '<tr>';
			printf(
				'<td><code class="procore-connect-copy" data-procore-connect-copy="[%1$s]">[%1$s]</code></td>',
				esc_html( $tag )
			);
			printf( '<td>%s</td>', esc_html( (string) $definition['description'] ) );
			printf( '<td>%s</td>', esc_html( $permission ) );
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Available endpoints for [procore_data]', 'connect-for-procore' ) . '</h2>';
		echo '<p><code>' . esc_html( implode( ', ', array_keys( Endpoints::public_endpoints() ) ) ) . '</code></p>';
	}

	/**
	 * Render the Status tab.
	 *
	 * @return void
	 */
	private function render_status(): void {
		$tokens  = TokenStore::all();
		$limit   = Client::rate_limit();
		$circuit = Client::circuit_state();

		$rows = array(
			__( 'Plugin version', 'connect-for-procore' )  => PROCORE_CONNECT_VERSION,
			__( 'WordPress', 'connect-for-procore' )       => get_bloginfo( 'version' ),
			__( 'PHP', 'connect-for-procore' )             => PHP_VERSION,
			__( 'Environment', 'connect-for-procore' )     => Environment::choices()[ Environment::current() ],
			__( 'API host', 'connect-for-procore' )        => Environment::api_host(),
			__( 'Login host', 'connect-for-procore' )      => Environment::login_host(),
			__( 'Redirect URI', 'connect-for-procore' )    => Environment::redirect_uri(),
			__( 'Authentication', 'connect-for-procore' )  => Client::instance()->auth()->label(),
			__( 'Credential storage', 'connect-for-procore' ) => Settings::credentials_are_constants()
				? __( 'wp-config.php constants', 'connect-for-procore' )
				: ( Encryption::is_strong() ? __( 'Encrypted (AES-256-GCM)', 'connect-for-procore' ) : __( 'Unencrypted — OpenSSL unavailable', 'connect-for-procore' ) ),
			__( 'Token expires', 'connect-for-procore' )   => $tokens['expires_at'] > 0
				? wp_date( 'Y-m-d H:i:s', $tokens['expires_at'] )
				: __( 'No token stored', 'connect-for-procore' ),
			__( 'Rate limit', 'connect-for-procore' )      => $limit['limit'] > 0
				? sprintf( '%d / %d', $limit['remaining'], $limit['limit'] )
				: __( 'Not reported yet', 'connect-for-procore' ),
			__( 'Circuit breaker', 'connect-for-procore' ) => $circuit['open_until'] > time()
				? sprintf(
					/* translators: %s: human readable time difference. */
					__( 'Open — resumes in %s', 'connect-for-procore' ),
					human_time_diff( time(), $circuit['open_until'] )
				)
				: __( 'Closed', 'connect-for-procore' ),
			__( 'Cached responses', 'connect-for-procore' ) => (string) Cache::stats()['total'],
			__( 'Object cache', 'connect-for-procore' )    => wp_using_ext_object_cache() ? __( 'Persistent', 'connect-for-procore' ) : __( 'Database transients', 'connect-for-procore' ),
		);

		echo '<table class="widefat striped"><tbody>';

		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%1$s</th><td><code>%2$s</code></td></tr>', esc_html( (string) $label ), esc_html( (string) $value ) );
		}

		echo '</tbody></table>';

		$entries = Logger::entries();

		echo '<h2>' . esc_html__( 'Recent diagnostics', 'connect-for-procore' ) . '</h2>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing recorded. Turn on diagnostics under Tools to start collecting events.', 'connect-for-procore' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'connect-for-procore' ) . '</th>';
		echo '<th>' . esc_html__( 'Level', 'connect-for-procore' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'connect-for-procore' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
				esc_html( wp_date( 'Y-m-d H:i:s', (int) $entry['time'] ) ),
				esc_html( (string) $entry['level'] ),
				esc_html( (string) $entry['message'] )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Print a settings table row.
	 *
	 * @param string   $label   Row label.
	 * @param callable $control Callback that prints the control markup.
	 * @return void
	 */
	private function row( string $label, callable $control ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		call_user_func( $control );
		echo '</td></tr>';
	}

	/**
	 * Print a checkbox row.
	 *
	 * @param string $label       Row label.
	 * @param string $key         Setting key.
	 * @param bool   $checked     Current value.
	 * @param string $control_text Checkbox label.
	 * @param string $description Help text.
	 * @return void
	 */
	private function checkbox( string $label, string $key, bool $checked, string $control_text, string $description = '' ): void {
		$this->row(
			$label,
			function () use ( $key, $checked, $control_text, $description ) {
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
					esc_attr( Settings::OPTION ),
					esc_attr( $key ),
					checked( $checked, true, false ),
					esc_html( $control_text )
				);

				if ( '' !== $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			}
		);
	}

	/**
	 * Print a text input row.
	 *
	 * @param string $label       Row label.
	 * @param string $key         Setting key.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 * @param string $placeholder Placeholder text.
	 * @return void
	 */
	private function text( string $label, string $key, string $value, string $description = '', string $placeholder = '' ): void {
		$this->row(
			$label,
			function () use ( $key, $value, $description, $placeholder ) {
				printf(
					'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s">',
					esc_attr( Settings::OPTION ),
					esc_attr( $key ),
					esc_attr( $value ),
					esc_attr( $placeholder )
				);

				if ( '' !== $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			}
		);
	}

	/**
	 * Print a number input row.
	 *
	 * @param string $label       Row label.
	 * @param string $key         Setting key.
	 * @param int    $value       Current value.
	 * @param int    $min         Minimum accepted value.
	 * @param int    $max         Maximum accepted value.
	 * @param string $description Help text.
	 * @return void
	 */
	private function number( string $label, string $key, int $value, int $min, int $max, string $description = '' ): void {
		$this->row(
			$label,
			function () use ( $key, $value, $min, $max, $description ) {
				printf(
					'<input type="number" class="small-text" name="%1$s[%2$s]" value="%3$s" min="%4$s" max="%5$s" step="1">',
					esc_attr( Settings::OPTION ),
					esc_attr( $key ),
					esc_attr( (string) $value ),
					esc_attr( (string) $min ),
					esc_attr( (string) $max )
				);

				if ( '' !== $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			}
		);
	}
}
