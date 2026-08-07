# Changelog

All notable changes to Procore Connect are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] — 2026-08-07

A complete rewrite. Version 1.x could not authenticate against the live Procore API, so
every install was non-functional regardless of configuration.

### ⚠️ Action required when upgrading

- **The plugin is now called Procore Connect**, with the slug `procore-connect`. The
  previous name contained "wp", which wordpress.org disallows in both a plugin name and
  a slug, making the directory listing impossible. The GitHub repository stays at
  `ibuilder/ProcoreWP`.
- The main plugin file was renamed from `index.php` to `procore-connect.php`. WordPress
  treats this as a different plugin, so **Procore Connect must be activated once** after
  updating. Settings are imported automatically by the upgrade routine.
- Because 1.x never obtained a token, any stored token is discarded. **Re-run
  Procore → Connection → Test connection** after upgrading.
- All 1.x shortcode tags are unchanged, and the legacy `id` attribute is still accepted
  as an alias for `project_id`. Existing pages keep working.

### Fixed

- **Authentication targeted the wrong host.** Token requests went to
  `api.procore.com/oauth/token`. Procore serves authentication from
  `login.procore.com/oauth/token`, a separate origin, so a token was never issued.
- **Company scope was sent the wrong way.** The company was appended as a `company_id`
  query parameter. Procore requires the `Procore-Company-Id` request header, without
  which most endpoints return 401 or 403.
- **Pagination did not exist.** `get_projects()` returned only the first page while
  `limit="0"` advertised "show all".
- **Sorting corrupted result sets.** `array_multisort()` over `array_column()` output
  misaligns rows as soon as any record lacks the sort column. Records with no value now
  sort last, in both directions, and stay attached to their own row.
- **Sparse payloads produced PHP warnings.** Procore omits fields the account cannot see;
  every read is now null-safe.
- **Activation referenced a file that was never in the repository.**
  `assets/css/procore-integration-default.css` did not exist, so `file_get_contents()`
  warned and `copy()` silently did nothing.
- **The stylesheet loaded on every page** whether or not the plugin rendered anything.
- **No request timeout was set**, leaving requests on the 5-second default with no user
  agent and no explicit SSL verification.

### Security

- The client secret, access token and refresh token are encrypted at rest with
  AES-256-GCM, keyed from the site's own salts, and stored in a non-autoloaded option.
  1.x stored all three as plaintext in an option loaded on every request site-wide.
- The secret is never rendered back into a form field. The input shows a masked
  placeholder; submitting it blank leaves the stored value untouched.
- Credentials may be defined as `PROCORE_CONNECT_CLIENT_ID` / `PROCORE_CONNECT_CLIENT_SECRET` /
  `PROCORE_CONNECT_COMPANY_ID` in `wp-config.php`, taking precedence and never reaching the
  database.
- Every admin action verifies a nonce and the `manage_options` capability. 1.x ran its
  connection test from an unverified `$_POST` key.
- `register_setting()` now has a `sanitize_callback`. 1.x had none, so an arbitrary
  array could be written to the option.
- Email addresses are suppressed by default and require two separate opt-ins to publish;
  when published they pass through `antispambot()`.
- Procore error messages, which routinely name accounts and permissions, are shown to
  administrators only. Visitors see a neutral notice.
- `[procore_project_data]` reads from an allow-list rather than any field in the payload.
- `Link` headers pointing off the configured API host are refused.
- The OAuth flow binds a one-time `state` value to a nonce-protected transient.

### Added

- **Client Credentials (DMSA) and Authorization Code** grants, selectable in the admin,
  with a refresh mutex so concurrent front-end requests cannot invalidate a refresh
  token — Procore revokes the old one the instant it is exchanged.
- **Response caching** with per-endpoint lifetimes, a site-wide floor that page authors
  cannot undercut, group purging, and an optional hourly cron warmer. Backed by the
  transient API, so persistent object caches are used automatically.
- **Rate-limit handling**: `X-Rate-Limit-*` headers are recorded and surfaced, `429` and
  `503` are retried with exponential backoff plus jitter honouring `Retry-After`, and a
  circuit breaker pauses requests after repeated failures.
- **Stale-cache fallback**, so a Procore outage degrades a page rather than blanking it.
- **Pagination** by following `Link: rel="next"`, with a page ceiling.
- **Sandbox and custom regional environments**, each with their own credentials.
- **Eleven new shortcodes**: `procore_rfis`, `procore_submittals`, `procore_punch_list`,
  `procore_observations`, `procore_daily_logs`, `procore_change_orders`,
  `procore_milestones`, `procore_vendors`, `procore_offices`, `procore_project_map`, and
  the generic allow-listed `procore_data`.
- **A Procore block** with one inserter variation per shortcode, a settings sidebar and a
  live server-rendered preview. No build step; the shipped editor script is the source.
- **An optional read-only REST proxy** at `/wp-json/procore-connect/v1/`, serving cached data
  from allow-listed endpoints with configurable access.
- **WP-CLI commands**: `test`, `doctor`, `projects`, `cache-clear`, `cache-warm`,
  `reset-token`.
- **Theme template overrides** via `yourtheme/procore-connect/`, replacing the 1.x advice to
  edit files inside the plugin directory — which lost customisations on every update.
- **A connection diagnostic** that reports each stage separately and probes every
  registered endpoint, revealing exactly which Procore tool permissions the credentials
  hold. 1.x reported only "successful" or "failed".
- **A Status screen** with rate-limit headroom, circuit state, token expiry, cache
  statistics and a redacted diagnostic log.
- **A generated shortcode reference** built from the same registry the shortcodes use, so
  the documentation cannot drift from the code.
- **Filters** for the endpoint registry, shortcode registry, allow-listed project fields,
  API and login hosts, redirect URI, request arguments, cache toggle and template lookup.

### Changed

- Full internationalisation. 1.x declared a text domain but contained no translation
  calls at all.
- Passes Plugin Check with no errors or warnings, and PHPCS `WordPress-Extra` plus
  `WordPress-Docs`.
- Plugin headers completed: `License URI`, `Requires at least`, `Requires PHP`,
  `Domain Path`, `Update URI`, and real `Plugin URI` and `Author` values in place of
  `https://example.com` and `Your Name`.
- Namespaced (`ProcoreConnect\`) and consistently prefixed (`procore_connect_`, `PROCORE_CONNECT_`).
- Added `uninstall.php`, which removes credentials, tokens, cache and options unless the
  site opts to keep its data.
- Removed all direct filesystem writes into the plugin directory.
- Unit tests run against fixtures through an injected transport, needing no credentials
  or network access.

## [1.0.0]

- Initial release.

[2.0.0]: https://github.com/ibuilder/ProcoreWP/releases/tag/v2.0.0
[1.0.0]: https://github.com/ibuilder/ProcoreWP/releases/tag/v1.0.0
