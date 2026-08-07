---
title: REST API
layout: default
nav_order: 8
---

# REST proxy

An optional read-only proxy that lets your theme or front-end JavaScript read Procore
data without the browser ever seeing a Procore credential.

**Off by default.** Enable it under **Procore → Tools → Expose cached Procore data**.

## Access levels

| Level | Who can read |
|---|---|
| Anyone | Unauthenticated visitors |
| Logged-in users | Any authenticated user *(default)* |
| Users who can edit posts | Anyone with `edit_posts` |

Think about this before choosing **Anyone**. Even though only allow-listed endpoints are
reachable, a public route makes your project data machine-readable to anyone who finds
the URL. Whether that is a problem depends entirely on what is in your Procore account.

## Endpoints

### List what is available

```
GET /wp-json/procorewp/v1/endpoints
```

```json
{
  "endpoints": [
    { "slug": "projects", "label": "Projects", "scope": "company",
      "fields": [ "id", "name", "project_number", "city", "state_code", "active" ] },
    { "slug": "rfis", "label": "RFIs", "scope": "project",
      "fields": [ "id", "number", "subject", "status", "due_date" ] }
  ]
}
```

### Read data

```
GET /wp-json/procorewp/v1/data/{endpoint}
```

| Parameter | Default | Notes |
|---|---|---|
| `company_id` | Site default | |
| `project_id` | — | Required for project-scoped endpoints |
| `per_page` | `100` | 1–2000 |
| `page` | `1` | |

```
GET /wp-json/procorewp/v1/data/rfis?project_id=123&per_page=25
```

```json
{
  "endpoint": "rfis",
  "cached": true,
  "stale": false,
  "total": 42,
  "data": [ { "id": 1, "number": "RFI-001", "subject": "Slab depth", "status": "open" } ]
}
```

`cached` tells you the response came from the plugin's cache rather than a live API call;
`stale` tells you Procore was unreachable and this is the last good copy. Responses also
carry an `X-ProcoreWP-Cached` header.

## Example

```js
const response = await fetch(
	'/wp-json/procorewp/v1/data/rfis?project_id=123&per_page=25',
	{ credentials: 'same-origin', headers: { 'X-WP-Nonce': wpApiSettings.nonce } }
);

const { data, cached } = await response.json();
```

Omit the nonce only if the access level is set to **Anyone**.

## What it will not do

- **Write.** Every route is `GET`. There is no write path in the plugin at all.
- **Reach arbitrary endpoints.** Only slugs marked public in the endpoint registry
  resolve. Identity endpoints such as `me` and `companies` are not published and return
  404.
- **Leak credentials.** The Client ID, Client Secret and tokens stay server-side.
- **Bypass the cache.** Requests are served from the same cache the shortcodes use, so
  exposing the proxy cannot blow your Procore rate limit.
- **Leak error detail.** Procore's own error messages — which name accounts and
  permissions — reach administrators only. Everyone else gets a neutral message.

## Errors

| Status | Meaning |
|---|---|
| `404` | The proxy is disabled, or the endpoint is not published |
| `401` / `403` | The access level excludes this request |
| `400` | Missing required context, e.g. no `project_id` on a project endpoint |
| `502` | Procore was unreachable and no cached copy existed |

## Publishing your own endpoint

Add it to the registry with `'public' => true` — see
[the shortcode reference](../shortcodes/#the-generic-reader). It becomes reachable from
both `[procore_data]` and this proxy, so only publish endpoints whose payload is safe to
expose at the access level you have chosen.
