# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 2.0.x   | ✅ |
| 1.x     | ❌ — see the note below |

Version 1.x is not supported and should not be used. It stored the Procore client secret
and access token as plaintext in an autoloaded WordPress option, ran its connection test
from an unverified `$_POST` key, and registered its settings with no sanitize callback.
It also could not authenticate against the live API. Upgrade to 2.0.0 and rotate any
credentials that were stored by 1.x, on the assumption they were exposed.

## Reporting a vulnerability

Please **do not** open a public GitHub issue.

Use GitHub's private reporting instead:
[Report a vulnerability](https://github.com/ibuilder/ProcoreWP/security/advisories/new)

Helpful things to include: affected version, a description of the impact, reproduction
steps, and any proof-of-concept. Please do not include real Procore credentials — redact
them.

You can expect an acknowledgement within a few days and a fix or a plan within 30 days
for confirmed issues. Credit is given in the changelog unless you prefer otherwise.

## How credentials are handled

- The client secret, access token and refresh token are encrypted with AES-256-GCM before
  storage, keyed from the site's own WordPress salts, so a database dump without
  `wp-config.php` does not disclose them.
- They are stored in a non-autoloaded option and never rendered into a form field; the
  admin shows a mask.
- Sites may instead define `PROCORE_CONNECT_CLIENT_ID` and `PROCORE_CONNECT_CLIENT_SECRET` in
  `wp-config.php`. These take precedence and never reach the database. This is the
  recommended production setup.
- If OpenSSL is unavailable the plugin warns in the admin and recommends the constants.

## Boundaries worth knowing about

- Every Procore path the plugin can reach is declared in `src/Api/Endpoints.php`. The
  generic `[procore_data]` shortcode and the REST proxy resolve against that registry
  and cannot reach anything outside it.
- The REST proxy is off by default, is read-only, and never exposes credentials.
- `[procore_project_data]` reads from a field allow-list, not the whole payload.
- Email addresses are suppressed by default and require two independent opt-ins.
- Procore error messages are shown to users with `manage_options` only.
- `Link` headers are followed only when they point at the configured API host.

## Scope

In scope: this plugin's code. Out of scope: vulnerabilities in WordPress core, in
third-party plugins or themes, or in the Procore API itself — report those to their
respective maintainers.
