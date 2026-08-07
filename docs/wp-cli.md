---
title: WP-CLI
layout: default
nav_order: 9
---

# WP-CLI

All commands live under `wp procore-connect` and are available whenever WP-CLI is.

## Verify the connection

```bash
wp procore-connect test
```

Runs the same diagnostic as the admin screen: configuration, token, identity, company
access, default company, rate limit — then probes every endpoint.

```
+------------------------+--------+------------------------------------------------+
| check                  | result | detail                                         |
+------------------------+--------+------------------------------------------------+
| Configuration          | OK     | Ready. Environment: Production (api.procore..) |
| Access token           | OK     | Obtained via Service Account. Valid for 1 hour |
| Authenticated account  | OK     | Connected as Service Account (dmsa@...)        |
| Company access         | OK     | 1 company available: Example Construction (42) |
| Default company        | OK     | 37 projects visible in company 4242            |
| Rate limit             | OK     | 3211 of 3600 requests remaining                |
+------------------------+--------+------------------------------------------------+

Endpoint permissions:
+------------------------+---------+-------------+--------------------------------+
| slug                   | status  | message     | permission                     |
+------------------------+---------+-------------+--------------------------------+
| projects               | ok      | Readable.   | Company Admin: Read Only       |
| rfis                   | ok      | Readable.   | RFIs: Read Only                |
| submittals             | failed  | HTTP 403    | Submittals: Read Only          |
+------------------------+---------+-------------+--------------------------------+
```

A `failed` row almost always means the service account was not granted that tool
permission at install time. Fix it in Procore's App Management, not here.

Exits non-zero when a check fails, so it works as a deploy gate:

```bash
wp procore-connect test || exit 1
```

## Configuration summary

```bash
wp procore-connect doctor
```

Prints environment, hosts, redirect URI, auth mode, whether credentials are set, default
company, cache state and circuit breaker state. Secrets are reported as `yes`/`no`, never
printed — safe to paste into a support ticket.

## List projects

```bash
wp procore-connect projects
wp procore-connect projects --company=4242 --format=csv
wp procore-connect projects --format=json > projects.json
```

Follows pagination, so this returns every project rather than the first page.

| Option | Default | Notes |
|---|---|---|
| `--company=<id>` | Site default | |
| `--format=<format>` | `table` | `table`, `csv`, `json`, `count` |

## Cache

```bash
wp procore-connect cache-clear
wp procore-connect cache-clear --group=rfis
wp procore-connect cache-warm
```

`cache-warm` fetches every project for the default company and caches the result, so the
first visitor after a deploy does not pay for the API call. Useful in a deploy script:

```bash
wp procore-connect cache-clear
wp procore-connect cache-warm
```

## Reset the token

```bash
wp procore-connect reset-token
```

Discards stored tokens and resets the circuit breaker. The next request re-authenticates.

For the service account grant this is harmless — a new token is simply requested. For the
user grant it clears the refresh token too, so an administrator has to click **Connect to
Procore** again.

## In multisite

```bash
wp procore-connect test --url=https://example.com/site-two
```

Settings, credentials and cache are per-site.
