# Procore Connect

[![Plugin Check](https://github.com/ibuilder/ProcoreWP/actions/workflows/plugin-check.yml/badge.svg)](https://github.com/ibuilder/ProcoreWP/actions/workflows/plugin-check.yml)
[![Coding Standards](https://github.com/ibuilder/ProcoreWP/actions/workflows/phpcs.yml/badge.svg)](https://github.com/ibuilder/ProcoreWP/actions/workflows/phpcs.yml)
[![Tests](https://github.com/ibuilder/ProcoreWP/actions/workflows/tests.yml/badge.svg)](https://github.com/ibuilder/ProcoreWP/actions/workflows/tests.yml)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Publish live [Procore](https://www.procore.com/) construction project data on a WordPress site — with shortcodes, blocks, and a cached REST proxy.

**[Documentation](https://ibuilder.github.io/ProcoreWP/)** · [Installation](https://ibuilder.github.io/ProcoreWP/installation/) · [Authentication](https://ibuilder.github.io/ProcoreWP/authentication/) · [Shortcode reference](https://ibuilder.github.io/ProcoreWP/shortcodes/) · [Upgrading from 1.x](https://ibuilder.github.io/ProcoreWP/upgrading/)

---

## What it does

Renders Procore data on the front end of a WordPress site: project directories, project detail panels, team lists, drawings, specifications, RFIs, submittals, punch lists, observations, daily logs, change orders, schedule milestones, company vendors and offices.

Everything is **read-only**. Procore Connect never writes to Procore.

```
[procore_project_list company_id="4242" limit="10"]

[procore_project id="123"]
[procore_team id="123"]
[procore_rfis id="123" status="open" limit="5"]

[procore_data endpoint="submittals" project_id="123" columns="number,title,status,due_date"]
```

## Why version 2 exists

Version 1.x could not work against the live API. It posted its token request to `api.procore.com`, but Procore serves authentication from `login.procore.com` — a different host — so no token was ever issued. It also sent the company scope as a `company_id` query parameter where Procore requires the `Procore-Company-Id` header, stored the client secret as plaintext in an autoloaded option, ran its connection test from an unverified `$_POST`, and cached nothing at all against an API with a documented ten-second spike limit.

Version 2.0.0 is a rewrite. The full list is in [CHANGELOG.md](CHANGELOG.md).

## Highlights

| | |
|---|---|
| **Two auth modes** | Client Credentials via a Developer Managed Service Account, or Authorization Code on behalf of a Procore user. Chosen in the admin. |
| **Rate-limit aware** | Reads `X-Rate-Limit-*`, honours `Retry-After`, backs off exponentially with jitter, and trips a circuit breaker after repeated failures. |
| **Caches by default** | Per-endpoint lifetimes with a site-wide floor no page can undercut. Uses the transient API, so Redis and Memcached work automatically. |
| **Degrades gracefully** | A Procore outage serves the last good response instead of blanking a published page. |
| **Real pagination** | Follows Procore's `Link: rel="next"` headers, refusing any link that points off the configured API host. |
| **Encrypted at rest** | AES-256-GCM, keyed from the site's own salts. Or keep credentials out of the database entirely with `wp-config.php` constants. |
| **Private by default** | Email addresses are suppressed unless you opt in twice. Procore error messages reach administrators only. |
| **Diagnostics** | The connection test reports every stage and probes each endpoint, so you can see which Procore tool permissions you actually have. |

## Requirements

WordPress 6.5+ · PHP 7.4+ · A Procore account with API access

## Quick start

1. Install and activate the plugin.
2. **Procore → Connection** — enter your Client ID and Client Secret.
3. Click **Look up companies** and pick a default company.
4. Click **Test connection**. Each stage reports separately, and the endpoint probe shows which shortcodes your permissions support.
5. Add a shortcode or the **Procore** block to a page.

Getting credentials is covered step by step in [the authentication guide](https://ibuilder.github.io/ProcoreWP/authentication/).

### Keeping the secret out of the database

```php
// wp-config.php
define( 'PROCORE_CONNECT_CLIENT_ID', 'your-client-id' );
define( 'PROCORE_CONNECT_CLIENT_SECRET', 'your-client-secret' );
define( 'PROCORE_CONNECT_COMPANY_ID', 4242 );
```

These take precedence over the admin fields and are never written to an option.

## Shortcodes

| Shortcode | Shows | Procore permission |
|---|---|---|
| `[procore_project_list]` | Projects in a company | Company Admin / Project Directory |
| `[procore_project]` | One project's details | Project Admin |
| `[procore_project_data]` | One allow-listed project field | Project Admin |
| `[procore_featured_image]` | Project logo or photo | Project Admin |
| `[procore_team]` | Project directory users | Project Directory |
| `[procore_drawings]` | Drawing areas | Drawings |
| `[procore_specifications]` | Specification sections | Specifications |
| `[procore_rfis]` | RFIs | RFIs |
| `[procore_submittals]` | Submittals | Submittals |
| `[procore_punch_list]` | Punch items | Punch List |
| `[procore_observations]` | Observations | Observations |
| `[procore_daily_logs]` | Daily construction reports | Daily Log |
| `[procore_change_orders]` | Change order packages | Change Orders |
| `[procore_milestones]` | Schedule tasks | Schedule |
| `[procore_vendors]` | Company directory | Company Directory |
| `[procore_offices]` | Company offices | Company Admin |
| `[procore_project_map]` | Project locations with geo microdata | Company Admin |
| `[procore_data]` | Any published endpoint | Depends on `endpoint` |

Shared attributes: `company_id`, `project_id`, `limit`, `page`, `orderby`, `order`, `columns`, `template`, `class`, `title`, `cache`, `empty_text`, `show_email`, `all`.

The legacy `id` attribute from 1.x is still accepted as an alias for `project_id`, so existing pages keep working. A live, always-accurate reference is generated inside the plugin at **Procore → Shortcodes**.

## Blocks

A single **Procore** block registers one inserter variation per shortcode, with a settings sidebar and a live server-rendered preview. It delegates to the same renderer the shortcodes use, so markup and escaping are identical in both surfaces.

The editor script is hand-written ES5 against the global `wp.*` runtime. There is no build step and no `node_modules` — the file that ships is the file that was authored.

## REST proxy

Optional, off by default. Enable it under **Procore → Tools**.

```
GET /wp-json/procore-connect/v1/endpoints
GET /wp-json/procore-connect/v1/data/rfis?project_id=123&per_page=25
```

Read-only, served from cache, restricted to endpoints published in the registry, with access configurable between public, logged-in and editor. Credentials never reach the browser.

## WP-CLI

```bash
wp procore-connect test
wp procore-connect doctor
wp procore-connect projects --format=csv
wp procore-connect cache-clear --group=rfis
wp procore-connect cache-warm
wp procore-connect reset-token
```

## Customising output

Copy any file from `templates/` into `yourtheme/procore-connect/` and edit it there. Overrides survive plugin updates — unlike 1.x, which told you to edit CSS inside the plugin directory and lost your work on every release.

```
wp-content/plugins/procore-connect/templates/collection.php
  → wp-content/themes/your-theme/procore-connect/collection.php
```

Templates: `collection`, `record`, `field`, `image`, `map`. For styling only, use **Procore → Display → Custom CSS**.

### Filters

| Filter | Purpose |
|---|---|
| `procore_connect_endpoints` | Correct or add an endpoint after a Procore resource version bump |
| `procore_connect_shortcodes` | Register a shortcode, or change a column map |
| `procore_connect_allowed_project_fields` | Extend what `[procore_project_data]` may display |
| `procore_connect_api_host` / `procore_connect_login_host` | Point at a regional or federal zone |
| `procore_connect_redirect_uri` | Override the OAuth redirect URI |
| `procore_connect_request_args` | Adjust the HTTP arguments of an API request |
| `procore_connect_cache_enabled` | Disable caching programmatically |
| `procore_connect_template_candidates` | Change where templates are looked up |

## Development

```bash
composer install
composer run lint     # PHPCS: WordPress-Extra + WordPress-Docs
composer run test     # PHPUnit
composer run syntax   # php -l across the tree
```

Tests run against JSON fixtures through an injected HTTP transport, so no Procore credentials or network access are needed. They cover token handling, the refresh mutex, 429/503 backoff, `Link` pagination, cache keys and purging, the stale fallback, encryption round-trips, endpoint allow-list refusals, and every shortcode's attribute sanitization and output escaping.

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: [SECURITY.md](SECURITY.md).

## Upgrading from 1.x

Settings are imported automatically, all shortcode names are unchanged, and the legacy `id` attribute still works.

Two things need your attention. The main plugin file was renamed from `index.php` to `procore-connect.php`, which WordPress sees as a different plugin, so **activate Procore Connect once after updating**. And because 1.x never successfully authenticated, **re-run the connection test** before assuming a blank shortcode is a bug.

Full detail: [the upgrade guide](https://ibuilder.github.io/ProcoreWP/upgrading/).

## Third-party service

Procore Connect contacts Procore to retrieve the data you ask it to display: `login.procore.com` for authentication and `api.procore.com` for data, or the sandbox or regional hosts you configure. It sends your Client ID and Client Secret during authentication, plus the company and project identifiers you configure. No visitor data is sent to Procore.

[Procore terms](https://www.procore.com/legal/termsofservice) · [Procore privacy policy](https://www.procore.com/legal/privacy) · [Procore API docs](https://developers.procore.com/documentation/introduction)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Procore Connect is not affiliated with, endorsed by, or sponsored by Procore Technologies, Inc. "Procore" is a trademark of Procore Technologies, Inc.
