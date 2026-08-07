---
title: Authentication
layout: default
nav_order: 3
---

# Authentication

Procore Connect supports both of Procore's OAuth 2.0 grants. Which one you want depends on
whether the connection should belong to your organisation or to a person.

## Which mode?

| | Service Account (Client Credentials) | User Account (Authorization Code) |
|---|---|---|
| Acts as | A Developer Managed Service Account | A signed-in Procore user |
| Permissions | Declared in your app manifest, applied at install | Whatever that user can see |
| Survives staff changes | Yes | **No** — breaks if their access is revoked |
| Needs a redirect URI | No | Yes |
| Refresh token | None issued; a new token is simply requested | Issued, and rotated on every use |
| Best for | Almost every website | Cases needing one person's exact scope |

**Recommendation: use the service account.** A public marketing or project site should
not depend on one employee's Procore login remaining active.

---

## Service Account (Client Credentials)

This is Procore's Developer Managed Service Account, or DMSA. The service account user is
created automatically inside the Procore company directory when a company administrator
installs your app.

### 1. Create the app

1. Sign in to the [Procore Developer Portal](https://developers.procore.com/).
2. Go to **My Apps** and create a new app.
3. Add an **OAuth** component and set the grant type to `client_credentials`.

### 2. Declare permissions

Use the Permissions Builder to declare every tool your shortcodes will read. If you skip
one, the endpoint returns 403 at runtime and the shortcode renders nothing.

| If you use | Declare |
|---|---|
| `[procore_project_list]`, `[procore_project]` | Company Admin / Project Admin — Read Only |
| `[procore_team]` | Project Directory — Read Only |
| `[procore_vendors]` | Company Directory — Read Only |
| `[procore_offices]` | Company Admin — Read Only |
| `[procore_drawings]` | Drawings — Read Only |
| `[procore_specifications]` | Specifications — Read Only |
| `[procore_rfis]` | RFIs — Read Only |
| `[procore_submittals]` | Submittals — Read Only |
| `[procore_punch_list]` | Punch List — Read Only |
| `[procore_observations]` | Observations — Read Only |
| `[procore_daily_logs]` | Daily Log — Read Only |
| `[procore_change_orders]` | Change Orders — Read Only |
| `[procore_milestones]` | Schedule — Read Only |

Request **read only**, and no more than you need. Procore specifically advises against
granting the service account admin access to the company Directory tool, because that
level of access permits changes across every project in the account.

### 3. Install it

A Procore company administrator installs the app — from the App Marketplace, or as a
custom install. During installation they choose which projects the app may access. The
service account user is provisioned automatically and the declared permissions are
applied.

Permitted projects can be changed later under **App Management → Permissions** in
Procore. Avoid adjusting the service account's permissions by hand in the Directory tool;
that creates inconsistencies with the manifest.

### 4. Connect WordPress

Paste the DMSA Client ID and Client Secret into **Procore → Connection**, pick a default
company, and click **Test connection**.

Tokens are valid for 90 minutes. No refresh token is issued for this grant — Procore Connect
simply requests a new one when the old one nears expiry, with a two-minute safety margin.

---

## User Account (Authorization Code)

### 1. Create the app

In the Developer Portal, create an app and register the **redirect URI** exactly as shown
on **Procore → Connection** in WordPress. There is a copy button next to it. It looks
like:

```
https://example.com/wp-admin/admin-post.php?action=procore_connect_oauth_callback
```

It must match character for character, including the scheme and any `www`.

### 2. Connect

1. Set the authentication mode to **User Account** and save.
2. Enter the Client ID and Client Secret and save.
3. Click **Connect to Procore**, authorise the app, and you will be returned to WordPress.

### How the round trip is protected

Procore Connect generates a single-use `state` value, stores it in a short-lived transient, and
refuses any callback whose `state` does not match. The outbound leg additionally requires
a WordPress nonce and the `manage_options` capability. This is what stops someone
grafting their own Procore account onto your site by feeding you a crafted callback URL.

### Refresh tokens rotate

Procore invalidates a refresh token the moment it is exchanged and issues a new one.
Two concurrent requests both refreshing would leave one holding a dead token and lock the
site out of the API entirely.

Procore Connect guards every refresh with a lock. A request that loses the race waits briefly
and re-reads the token the winner stored, rather than refreshing again. If a refresh token
is ever rejected outright, the connection is cleared and the admin is told to reconnect —
there is no recovery from a rotated-away refresh token.

---

## Where credentials are stored

| | |
|---|---|
| At rest | AES-256-GCM, keyed from the site's WordPress salts |
| Option | Non-autoloaded, so it is not read on every page request |
| In the admin | Masked — the real secret is never rendered into a form field |
| Best practice | `PROCORE_CONNECT_CLIENT_SECRET` in `wp-config.php`, never in the database |

A blank Client Secret field on save means "leave the stored value alone". To remove a
stored secret, tick **Delete the stored secret**.

If OpenSSL is unavailable, Procore Connect says so in an admin notice and falls back to
unencrypted storage — use the `wp-config.php` constants on such a host.

---

## Finding your company ID

Click **Look up companies** on the Connection screen and pick from the list. Failing that,
it is in your Procore URL:

```
https://app.procore.com/companies/4242/...
                                 ^^^^
```

Project IDs appear the same way: `https://app.procore.com/projects/123/...`

---

## Verifying it works

**Procore → Connection → Test connection** runs through:

1. **Configuration** — credentials present, encryption available, which hosts are in use
2. **Access token** — obtained, via which grant, and how long it remains valid
3. **Authenticated account** — who Procore thinks you are
4. **Company access** — which companies the credentials can reach
5. **Default company** — whether projects are readable, and how many
6. **Rate limit** — remaining headroom and when the window resets

Then it probes every registered endpoint and reports each as readable, failed or skipped,
alongside the Procore permission that endpoint needs.

That probe table is the fastest way to diagnose an empty shortcode: a valid token whose
service account lacks read permission on one specific tool is by far the most common
real-world failure.
