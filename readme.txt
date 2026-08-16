=== Connect for Procore ===
Contributors: ibuilder
Tags: procore, construction, project management, shortcode, api
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish live Procore project data on your WordPress site with shortcodes and blocks, cached and rate-limit aware.

== Description ==

Connect for Procore connects your WordPress site to the Procore construction management platform and publishes project data on the front end: project directories, team members, drawings, specifications, RFIs, submittals, punch lists, daily logs, change orders and more.

Data is read-only. Connect for Procore never writes to Procore.

= Built for the way Procore actually works =

* **Two authentication modes.** Connect with a Developer Managed Service Account (the OAuth 2.0 Client Credentials grant), which keeps working when staff change; or with the Authorization Code grant, which acts on behalf of a signed-in Procore user.
* **Caching that respects the rate limit.** Procore enforces both an hourly and a ten-second spike limit. Every response is cached, with a per-endpoint lifetime you control and a site-wide floor that no single page can undercut.
* **It degrades, it doesn't break.** If Procore is unreachable, the last good response is served instead of an error. Repeated failures trip a circuit breaker that pauses requests and resumes automatically.
* **Real pagination.** Result sets are assembled by following Procore's `Link` headers, not by guessing page numbers.
* **Diagnostics that tell you something.** The connection test reports each stage separately and probes every endpoint, so you can see exactly which Procore tool permissions your credentials have — the usual cause of an empty shortcode.

= Privacy first =

Procore project directories contain personal data. Connect for Procore will not publish email addresses unless you turn off the global suppression setting **and** opt in on the individual shortcode. Error messages from Procore, which routinely name accounts and permissions, are shown to administrators only.

= Shortcodes =

Projects and company:

* `[procore_project_list]` — a table of projects
* `[procore_project id="123"]` — a single project detail panel
* `[procore_project_data id="123" field="start_date"]` — one allow-listed field
* `[procore_featured_image id="123"]` — the project logo or photo
* `[procore_team id="123"]` — the project team
* `[procore_vendors]` — the company directory
* `[procore_offices]` — company offices
* `[procore_project_map]` — project locations with geo microdata

Documents:

* `[procore_drawings id="123"]`
* `[procore_specifications id="123"]`

Project tools:

* `[procore_rfis id="123"]`
* `[procore_submittals id="123"]`
* `[procore_punch_list id="123"]`
* `[procore_observations id="123"]`
* `[procore_daily_logs id="123"]`
* `[procore_change_orders id="123"]`
* `[procore_milestones id="123"]`

Generic:

* `[procore_data endpoint="rfis" project_id="123" columns="number,subject,status"]`

Every shortcode also accepts `company_id`, `project_id`, `limit`, `page`, `orderby`, `order`, `columns`, `template`, `class`, `title`, `cache` and `empty_text`. The full reference is generated inside the plugin at **Procore → Shortcodes**.

= Blocks, REST and WP-CLI =

A **Procore** block provides every shortcode as an inserter entry with a live preview and a settings sidebar. An optional read-only REST proxy at `/wp-json/procore-connect/v1/` lets themes and JavaScript read cached Procore data without ever seeing your credentials. WP-CLI commands cover connection testing, cache management and project listing.

= Third-party service =

This plugin sends requests to Procore, a third-party service, in order to retrieve the data you ask it to display. Requests are made to `login.procore.com` (authentication) and `api.procore.com` (data), or to the sandbox or regional hosts you configure.

Data sent: your Procore Client ID and Client Secret during authentication, and the company and project identifiers you configure in settings or shortcodes. No visitor data is sent to Procore.

* Procore terms of service: https://www.procore.com/legal/termsofservice
* Procore privacy policy: https://www.procore.com/legal/privacy
* Procore API documentation: https://developers.procore.com/documentation/introduction

== Installation ==

1. Upload the plugin to `/wp-content/plugins/connect-for-procore/` or install it through the Plugins screen.
2. Activate it.
3. Go to **Procore → Connection** and enter your Client ID and Client Secret.
4. Choose a default company, then click **Test connection**.
5. Add a shortcode or the Procore block to any page.

To obtain credentials, create an application in the [Procore Developer Portal](https://developers.procore.com/). For the service account mode, add an OAuth component with the `client_credentials` grant type, declare the tool permissions you need in the Permissions Builder, and have a Procore company administrator install the app. For the user mode, register the redirect URI shown on the Connection screen.

For the strongest credential protection, define the secret in `wp-config.php` instead of storing it in the database:

`define( 'PROCORE_CONNECT_CLIENT_ID', 'your-client-id' );`
`define( 'PROCORE_CONNECT_CLIENT_SECRET', 'your-client-secret' );`

== Frequently Asked Questions ==

= My shortcode shows nothing. What's wrong? =

Run **Procore → Connection → Test connection**. It reports each stage separately and probes every endpoint. The most common cause is that the service account has no read permission on that particular Procore tool — the probe table tells you which ones it can reach.

= Which authentication mode should I use? =

The service account (Client Credentials) mode for almost every site. Its credentials belong to a service account installed by a company administrator, not to a named person, so the integration survives staff changes. Use the user (Authorization Code) mode only when you specifically need the connection scoped to one individual's permissions.

= Will this hit Procore's rate limit? =

Not with caching on, which is the default. Responses are cached per endpoint, page authors cannot request a lifetime shorter than the site-wide floor, and the remaining rate-limit headroom is shown on the Status screen.

= Can I change the markup? =

Yes. Copy any file from the plugin's `templates/` directory into `yourtheme/procore-connect/` and edit it there. It survives plugin updates. For styling only, use **Procore → Display → Custom CSS**.

= Does it write anything back to Procore? =

No. Every request is a read.

= Are email addresses published? =

No, not by default. Publishing them requires turning off the global suppression setting under **Display** *and* adding `show_email="true"` to the individual shortcode. Even then addresses are obfuscated against harvesters.

= I am upgrading from version 1.x. What changes? =

Your settings are imported automatically and every 1.x shortcode name and the legacy `id` attribute still work. Because the main plugin file was renamed, WordPress treats 2.0.0 as a new plugin, so you need to activate it once. Version 1.x authenticated against the wrong Procore host and could never obtain a token, so re-run the connection test after upgrading.

= Does it work with a page caching or object caching plugin? =

Yes. Caching goes through the transient API, so a persistent object cache such as Redis or Memcached is used automatically when present.

== Changelog ==

= 3.0.1 =

* Documented why the encryption class uses base64: AES-256-GCM produces raw bytes that cannot be stored in a WordPress option as-is, so base64 is the transport encoding applied after encryption. No behaviour change.

= 3.0.0 =

* Renamed from "Procore Connect" to "Connect for Procore". WordPress.org does not permit a plugin name or slug to begin with someone else's trademark, and Procore is a registered trademark of Procore Technologies, Inc. The new name follows the format WordPress.org prescribes for integrations built by non-employees.
* **You need to activate the plugin once after updating.** WordPress identifies a plugin by its directory, and the directory changed, so it sees a new plugin.
* Your settings, template overrides and custom CSS are all preserved. Option names, the REST namespace `/wp-json/procore-connect/v1/`, the `yourtheme/procore-connect/` override directory, the CSS classes, the block and the `wp procore-connect` CLI command are all deliberately unchanged.
* Fixed: the REST proxy sent a cache-status header named `X-Procore Connect-Cached`, which contains a space and is not a valid HTTP header name. It is now `X-Connect-For-Procore-Cached`.
* Fixed: the User-Agent contained a space inside its product token; it is now `ConnectForProcore/3.0.0`.

= 2.0.6 =

* Fixed: a column you named in `columns=` could be dropped. 2.0.5 applied its empty-column filter to hand-written column lists too, so `[procore_team columns="name,email_address"]` rendered only Name. An explicit list is now rendered exactly as written; only the default column set is trimmed.
* Fixed: a column no record fills in is now genuinely hidden. 2.0.5 said it did this but could not — a missing value renders as a dash rather than as nothing, so only a suppressed email address was ever hidden and every other empty column showed as a full column of dashes.
* Fixed: whether a column appeared no longer depends on the other columns. `columns="email_address"` kept the Email column while `columns="name,email_address"` dropped it.

= 2.0.5 =

* Fixed: `[procore_team]` showed an empty Email column. Email output is suppressed by default, so the column appeared with a header and blank cells on every default install. The Email column is now hidden when it is blank for every row. (This entry also claimed to hide any column with no value in any row; that did not work until 2.0.6.)

= 2.0.4 =

* Security documentation no longer recommends 2.0.0 or 2.0.1, the versions that damage the stored Client Secret. Both are now marked unsupported with the remedy stated plainly.
* Added test coverage for log redaction, which keeps secrets out of debug.log, and for the theme template override chain.

= 2.0.3 =

* **Repairs credentials damaged by 2.0.0 or 2.0.1.** Those versions re-encrypted the stored Client Secret on every settings save until it could no longer be read, leaving the site unable to authenticate with no explanation. 2.0.2 stopped the damage; 2.0.3 undoes it. The upgrade unwraps the value, re-stores it correctly, and tells you what it did. If it cannot be recovered it is cleared and you are asked to enter it again.

= 2.0.2 =

* **Fixed a credential-corrupting bug.** WordPress runs a registered setting's sanitize callback on every update to that option, including the plugin's own internal writes. Because the sanitizer encrypted the client secret unconditionally, each write re-encrypted the stored value until it could no longer be decrypted and the site silently lost its Procore connection. Encryption is now idempotent. If your connection stopped working after saving settings, re-enter the Client Secret once on 2.0.2 and it will stay valid.
* Added 18 tests covering both OAuth grants, CSRF state handling and refresh-token rotation.
* Added WordPress.org banner and icon assets.
* Removed a Screenshots section that referenced files which did not exist.

= 2.0.1 =

Fixes found by running the plugin inside a real WordPress install.

* Fixed: pagination parameters were sent to single-record endpoints such as `/projects/{id}`, which have no pages. This was noise on the wire and varied the cache key for identical requests.
* Fixed: the REST proxy ignored the configured default company, so every request failed with a missing-company error unless company_id was passed explicitly.
* Fixed: translations were loaded before `init`, which triggers a `_load_textdomain_just_in_time` notice on WordPress 6.7+ and silently drops translations.
* Fixed: WP-CLI subcommands were registered with underscores while the documentation advertised hyphens. `wp procore-connect cache-clear`, `cache-warm` and `reset-token` now work as documented.

= 2.0.0 =

A complete rewrite. See the upgrade notice below before updating.

* Fixed: authentication now posts to `login.procore.com`, Procore's actual token host. Version 1.x posted to the API host and could never obtain a token.
* Fixed: the company scope is now sent as the required `Procore-Company-Id` header rather than a `company_id` query parameter, which most endpoints reject.
* Fixed: result sets are paginated by following Procore's `Link` headers. Version 1.x silently returned only the first page.
* Fixed: sorting no longer misaligns rows when a record is missing the sort column.
* Fixed: missing fields in a Procore payload no longer produce PHP warnings.
* Security: the client secret and tokens are encrypted at rest with AES-256-GCM, stored in a non-autoloaded option, and never rendered back into a form field.
* Security: credentials may be defined as `wp-config.php` constants and kept out of the database entirely.
* Security: every admin action is nonce-verified and capability-checked. Version 1.x ran its connection test from an unverified POST.
* Security: settings are sanitized through a `sanitize_callback`, which version 1.x omitted.
* Security: email addresses are suppressed by default, and Procore error messages are shown to administrators only.
* Added: response caching with per-endpoint lifetimes, a site-wide floor, group purging and an optional cron warmer.
* Added: rate-limit awareness, exponential backoff with jitter on 429 and 503, and a circuit breaker.
* Added: a stale-cache fallback so a Procore outage degrades a page rather than blanking it.
* Added: the Authorization Code grant alongside Client Credentials, with a refresh mutex so concurrent requests cannot invalidate the refresh token.
* Added: sandbox and custom regional environment support.
* Added: eleven new shortcodes covering RFIs, submittals, punch lists, observations, daily logs, change orders, schedule tasks, vendors, offices, project locations, and a generic allow-listed endpoint reader.
* Added: a Procore block with a variation per shortcode and a live server-rendered preview.
* Added: an optional read-only REST proxy and WP-CLI commands.
* Added: theme template overrides via `yourtheme/procore-connect/`, replacing the previous advice to edit files inside the plugin directory.
* Added: a connection diagnostic that reports each stage and probes every endpoint for tool permissions.
* Changed: the plugin is fully translatable, passes Plugin Check with no errors or warnings, and passes PHPCS `WordPress-Extra` and `WordPress-Docs`.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 3.0.1 =
Documentation only. No functional change from 3.0.0.

= 3.0.0 =
The plugin is now called Connect for Procore, to comply with WordPress.org's trademark naming rule. Activate it once after updating — WordPress sees the renamed directory as a new plugin. Your settings, template overrides and custom CSS are preserved.

= 2.0.6 =
Corrects the 2.0.5 column-hiding fix, which dropped columns you had asked for by name and did not drop the empty ones it claimed to. Recommended for anyone on 2.0.5 who sets `columns=` on a shortcode.

= 2.0.5 =
Cosmetic fix: the team table no longer shows an empty Email column when email output is suppressed, which is the default.

= 2.0.4 =
Documentation and test coverage only. No functional change from 2.0.3.

= 2.0.3 =
Repairs a Client Secret damaged by 2.0.0 or 2.0.1, which re-encrypted it on every save until it stopped working. Upgrading restores it automatically and reports the result. Recommended for anyone who ran 2.0.0 or 2.0.1.

= 2.0.2 =
Important fix: repeated settings saves could re-encrypt the stored Client Secret until it became unrecoverable, silently breaking the Procore connection. Update, then re-enter your Client Secret once if the connection had stopped working.

= 2.0.1 =
Bug fixes from real-world integration testing: pagination on single-record endpoints, the REST proxy ignoring the default company, translations loading too early, and WP-CLI subcommand names. No action required.

= 2.0.0 =
Rewrite. Fixes authentication, adds caching and encrypts credentials. Settings migrate and all 1.x shortcodes still work, but the main file was renamed: activate the plugin once, re-run the connection test, and rotate your client secret.
