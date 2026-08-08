# Security Policy

## Supported versions

| Version | Supported | Notes |
|---------|-----------|-------|
| 2.0.3   | ✅ | Current |
| 2.0.2   | ⚠️ | Safe, but does not repair a credential already damaged by 2.0.0 or 2.0.1 |
| 2.0.0 – 2.0.1 | ❌ | **Destroy the stored client secret.** See below |
| 1.x     | ❌ | Stores credentials in plaintext. See below |

### 2.0.0 and 2.0.1 — stored credential destroyed

These versions attached the settings sanitizer to `sanitize_option_{$option}` and
encrypted the client secret unconditionally. WordPress runs that filter on *every*
`update_option()` for the option, including the plugin's own internal writes, so the
stored secret gained an encryption layer on each write until it could no longer be
decrypted. The site then failed to authenticate with nothing on screen to explain why.

This is a data-integrity fault rather than a disclosure: the value was over-encrypted,
never exposed. Upgrade to **2.0.3**, which unwraps and restores the value automatically
and reports what it did. If it cannot be recovered the secret is cleared and you are
asked to re-enter it.

### 1.x — credentials stored in plaintext

Version 1.x stored the Procore client secret, access token and refresh token as plaintext
in an **autoloaded** WordPress option, read into memory on every page request and present
in every database backup. It also ran its connection test from an unverified `$_POST` key
and registered its settings with no sanitize callback, and it could not authenticate
against the live API at all.

Upgrade to 2.0.3 and **rotate any credentials 1.x stored**, on the assumption they were
exposed.

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
