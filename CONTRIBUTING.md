# Contributing to ProcoreWP

Thanks for helping. This document covers how to get set up and what the review will look
for.

## Getting started

```bash
git clone https://github.com/ibuilder/ProcoreWP.git
cd ProcoreWP
composer install
```

There is no Node toolchain. The block editor script is hand-written ES5 against the
global `wp.*` runtime, deliberately, so that the file that ships is the file that was
authored — no compiled bundle without source.

## Checks

Run all three before opening a pull request. CI runs the same commands.

```bash
composer run syntax   # php -l across the tree
composer run lint     # PHPCS: WordPress-Extra + WordPress-Docs
composer run test     # PHPUnit
```

`composer run lint:fix` applies the auto-fixable subset.

Both the standards check and the test suite are expected to be completely clean. If a
sniff is genuinely wrong for a given line, add a `phpcs:ignore` with a specific sniff
name and a one-line reason — never a bare ignore, and never a broad file-level disable.

## Tests

Tests run against JSON fixtures through an injected HTTP transport
(`Client::set_transport()`), so no Procore credentials and no network access are needed.
WordPress functions are shimmed in `tests/wp-shims.php`; add to it only what the code
under test actually calls.

Please add a test for any behaviour change. The suite already covers the things that are
easy to regress:

- the `Procore-Company-Id` header being present on every request
- requests going to the API host and never the login host
- `Link`-header pagination, and refusing links that point off-host
- 429 and 503 backoff
- cache hits, group purging, and the stale-cache fallback
- encryption round-trips and secret masking
- endpoint allow-list refusals
- every shortcode's attribute sanitization and output escaping

## Adding an endpoint

Endpoints live in one place: `src/Api/Endpoints.php`. Add an entry with its path,
version, scope, default cache lifetime, required Procore permission and the fields it can
render. Setting `'public' => true` makes it reachable from `[procore_data]` and the REST
proxy, so only do that for endpoints whose payload is safe to publish.

Nothing outside that registry is callable. That is the boundary that keeps the generic
shortcode and the public proxy safe, so please do not route around it.

## Adding a shortcode

Shortcodes live in `src/Frontend/Shortcodes/Registrar.php`. Most need no new class — pick
`CollectionShortcode` or `RecordShortcode` and supply an endpoint, a template and a
column map. The admin reference screen, the block variations and the documentation all
read this registry, so a correct entry documents itself.

## Escaping

Templates get all cell content from `Format::cell()`, which escapes everything it
returns. Keeping that single choke point is what makes the escaping guarantee auditable —
please do not echo record values directly in a template.

Procore error messages name accounts, projects and permissions. They go to
administrators and the diagnostic log, never to a public page.

## Commits and pull requests

- One logical change per pull request.
- Explain *why* in the description, not just what.
- Update `CHANGELOG.md` under an `## [Unreleased]` heading.
- If you change behaviour that `readme.txt` or the `docs/` site describes, update those
  too. Both are part of the deliverable.

## Security

Do not open a public issue for a security problem. See [SECURITY.md](SECURITY.md).
