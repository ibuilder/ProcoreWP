=== ProcoreWP ===
Contributors: ibuilder
Tags: procore, construction, project management, shortcode, api
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish live Procore project data on your WordPress site with shortcodes and blocks, cached and rate-limit aware.

== Description ==

ProcoreWP connects your WordPress site to the Procore construction management platform and publishes project data on the front end: project directories, team members, drawings, specifications, RFIs, submittals, punch lists, daily logs, change orders and more.

Data is read-only. ProcoreWP never writes to Procore.

= Built for the way Procore actually works =

* **Two authentication modes.** Connect with a Developer Managed Service Account (the OAuth 2.0 Client Credentials grant), which keeps working when staff change; or with the Authorization Code grant, which acts on behalf of a signed-in Procore user.
* **Caching that respects the rate limit.** Procore enforces both an hourly and a ten-second spike limit. Every response is cached, with a per-endpoint lifetime you control and a site-wide floor that no single page can undercut.
* **It degrades, it doesn't break.** If Procore is unreachable, the last good response is served instead of an error. Repeated failures trip a circuit breaker that pauses requests and resumes automatically.
* **Real pagination.** Result sets are assembled by following Procore's `Link` headers, not by guessing page numbers.
* **Diagnostics that tell you something.** The connection test reports each stage separately and probes every endpoint, so you can see exactly which Procore tool permissions your credentials have — the usual cause of an empty shortcode.

= Privacy first =

Procore project directories contain personal data. ProcoreWP will not publish email addresses unless you turn off the global suppression setting **and** opt in on the individual shortcode. Error messages from Procore, which routinely name accounts and permissions, are shown to administrators only.

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

A **Procore** block provides every shortcode as an inserter entry with a live preview and a settings sidebar. An optional read-only REST proxy at `/wp-json/procorewp/v1/` lets themes and JavaScript read cached Procore data without ever seeing your credentials. WP-CLI commands cover connection testing, cache management and project listing.

= Third-party service =

This plugin sends requests to Procore, a third-party service, in order to retrieve the data you ask it to display. Requests are made to `login.procore.com` (authentication) and `api.procore.com` (data), or to the sandbox or regional hosts you configure.

Data sent: your Procore Client ID and Client Secret during authentication, and the company and project identifiers you configure in settings or shortcodes. No visitor data is sent to Procore.

* Procore terms of service: https://www.procore.com/legal/termsofservice
* Procore privacy policy: https://www.procore.com/legal/privacy
* Procore API documentation: https://developers.procore.com/documentation/introduction

== Installation ==

1. Upload the plugin to `/wp-content/plugins/procorewp/` or install it through the Plugins screen.
2. Activate it.
3. Go to **Procore → Connection** and enter your Client ID and Client Secret.
4. Choose a default company, then click **Test connection**.
5. Add a shortcode or the Procore block to any page.

To obtain credentials, create an application in the [Procore Developer Portal](https://developers.procore.com/). For the service account mode, add an OAuth component with the `client_credentials` grant type, declare the tool permissions you need in the Permissions Builder, and have a Procore company administrator install the app. For the user mode, register the redirect URI shown on the Connection screen.

For the strongest credential protection, define the secret in `wp-config.php` instead of storing it in the database:

`define( 'PROCOREWP_CLIENT_ID', 'your-client-id' );`
`define( 'PROCOREWP_CLIENT_SECRET', 'your-client-secret' );`

== Frequently Asked Questions ==

= My shortcode shows nothing. What's wrong? =

Run **Procore → Connection → Test connection**. It reports each stage separately and probes every endpoint. The most common cause is that the service account has no read permission on that particular Procore tool — the probe table tells you which ones it can reach.

= Which authentication mode should I use? =

The service account (Client Credentials) mode for almost every site. Its credentials belong to a service account installed by a company administrator, not to a named person, so the integration survives staff changes. Use the user (Authorization Code) mode only when you specifically need the connection scoped to one individual's permissions.

= Will this hit Procore's rate limit? =

Not with caching on, which is the default. Responses are cached per endpoint, page authors cannot request a lifetime shorter than the site-wide floor, and the remaining rate-limit headroom is shown on the Status screen.

= Can I change the markup? =

Yes. Copy any file from the plugin's `templates/` directory into `yourtheme/procorewp/` and edit it there. It survives plugin updates. For styling only, use **Procore → Display → Custom CSS**.

= Does it write anything back to Procore? =

No. Every request is a read.

= Are email addresses published? =

No, not by default. Publishing them requires turning off the global suppression setting under **Display** *and* adding `show_email="true"` to the individual shortcode. Even then addresses are obfuscated against harvesters.

= I am upgrading from version 1.x. What changes? =

Your settings are imported automatically and every 1.x shortcode name and the legacy `id` attribute still work. Because the main plugin file was renamed, WordPress treats 2.0.0 as a new plugin, so you need to activate it once. Version 1.x authenticated against the wrong Procore host and could never obtain a token, so re-run the connection test after upgrading.

= Does it work with a page caching or object caching plugin? =

Yes. Caching goes through the transient API, so a persistent object cache such as Redis or Memcached is used automatically when present.

== Screenshots ==

1. The Connection screen, with authentication mode, environment and credentials.
2. The connection test, reporting each stage and probing every endpoint for tool permissions.
3. The generated shortcode reference.
4. The Status screen, showing rate-limit headroom and cache statistics.
5. A project list rendered on the front end.
6. The Procore block in the editor, with a live preview.

== Changelog ==

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
* Added: theme template overrides via `yourtheme/procorewp/`, replacing the previous advice to edit files inside the plugin directory.
* Added: a connection diagnostic that reports each stage and probes every endpoint for tool permissions.
* Changed: the plugin is fully translatable, passes Plugin Check with no errors or warnings, and passes PHPCS `WordPress-Extra` and `WordPress-Docs`.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Rewrite. Fixes authentication, adds caching and encrypts credentials. Settings migrate and all 1.x shortcodes still work, but the main file was renamed: activate the plugin once, re-run the connection test, and rotate your client secret.
