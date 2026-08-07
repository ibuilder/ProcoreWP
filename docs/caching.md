---
title: Caching & rate limits
layout: default
nav_order: 6
---

# Caching and rate limits

## Why this matters

Procore enforces two rate limits at once: an hourly window and a ten-second spike window.
The API returns whichever you are closer to breaching, along with three headers on every
response:

| Header | Meaning |
|---|---|
| `X-Rate-Limit-Limit` | Requests allowed in the reported window |
| `X-Rate-Limit-Remaining` | Requests left in it |
| `X-Rate-Limit-Reset` | Unix timestamp when it resets |

A page with six uncached Procore Connect shortcodes makes six or more API calls **per visitor**.
On any site with real traffic that exhausts the spike limit within minutes and the page
starts erroring for everyone.

ProcoreWP 1.x had no caching at all — its own README acknowledged the problem and left it
to the reader. Version 2 caches by default.

## How caching works

Every response lands in two places:

- a **fresh** entry with the endpoint's configured lifetime, which satisfies normal reads
- a **stale** copy with a much longer lifetime, used only when Procore is unreachable

Storage goes through the WordPress transient API, so a site running Redis or Memcached
gets that behaviour automatically with no configuration.

Cache keys include the endpoint, the request arguments, the company, the project **and the
environment** — so switching between production and a sandbox never serves data from the
other.

### Default lifetimes

| Data | Default |
|---|---|
| RFIs, submittals, punch list, observations | 10 minutes |
| Projects, project detail | 15 minutes |
| Team, vendors, daily logs, change orders, schedule | 30 minutes |
| Drawings, specifications, offices | 6 hours |
| Companies | 1 hour |

Override per shortcode with `cache="900"`, or globally via the
[`procore_connect_endpoints`](../shortcodes/#the-generic-reader) filter.

### The floor

**Procore → Cache → Minimum cache lifetime** (default 300 seconds) is a hard floor. A
shortcode may request a *longer* lifetime but never a shorter one.

This is deliberate: without it, one page author writing `cache="1"` could take the whole
site over the rate limit, and the person who has to fix it is not the person who wrote the
shortcode.

## When Procore is unavailable

Two mechanisms keep a published page from breaking.

**Stale fallback.** If a request fails and a stale copy exists, that copy is served and
the failure is logged. A Procore outage degrades the freshness of a project page rather
than blanking it.

**Circuit breaker.** After five consecutive failures, requests pause for five minutes and
cached data is served exclusively. This stops a site hammering an API that is already
struggling, and stops every page view paying a timeout. The state is shown on
**Procore → Status** and in an admin notice, and it clears itself.

## Retries and backoff

| Response | Behaviour |
|---|---|
| `429 Too Many Requests` | Wait for `Retry-After`, or `X-Rate-Limit-Reset`, then retry |
| `503 Service Unavailable` | Wait for `Retry-After`, then retry |
| `401 Unauthorized` | Discard the token and retry once with a fresh one |
| Transport failure | Exponential backoff with jitter |
| Other `4xx` | Fail immediately — retrying will not help |

At most three attempts per request, and never longer than a ten-second pause.

## Pagination

Procore accepts `page` and `per_page` (keep it at or below 2000) and returns `Total`,
`Per-Page` and `Link` headers.

With `all="true"`, Procore Connect walks the `Link: rel="next"` chain rather than incrementing
page numbers — following the links avoids the off-by-one at the end of the result set,
because the `next` link simply disappears on the final page.

Two safeguards: a hard ceiling of 20 pages, so a misconfigured shortcode cannot pull tens
of thousands of records into memory (a warning is logged when it truncates); and any
`Link` pointing at a host other than the configured API host is refused outright.

## Managing the cache

**Admin** — **Procore → Cache** shows the entry count and offers a purge button.

**WP-CLI**

```bash
wp procore-connect cache-clear
wp procore-connect cache-clear --group=rfis
wp procore-connect cache-warm
```

**Cron** — an hourly job refreshes the project list for the default company, so the first
visitor after a cache expiry does not pay for the API call. Scheduled on activation,
removed on deactivation.

**Programmatically**

```php
// Disable caching entirely — not advisable on a public site.
add_filter( 'procore_connect_cache_enabled', '__return_false' );

// Purge one endpoint group after your own sync.
ProcoreConnect\Api\Cache::flush( 'projects' );

// React to a purge.
add_action( 'procore_connect_cache_flushed', function ( string $group, int $removed ): void {
	error_log( "Procore Connect purged {$removed} entries from {$group}" );
}, 10, 2 );
```

## Monitoring

**Procore → Status** reports remaining rate-limit headroom, circuit breaker state, token
expiry, cache statistics and whether a persistent object cache is in use.

Turn on **Procore → Tools → Record API diagnostics** to keep the last 50 events. Client
secrets, tokens and authorization codes are redacted before anything is written.
