---
title: Troubleshooting
layout: default
nav_order: 10
---

# Troubleshooting

## Start here

**Procore → Connection → Test connection.**

It reports six stages separately and then probes every endpoint. Nearly every problem
below is identified by that screen in one click, so run it before reading further. The
same thing is available as `wp procorewp test`.

---

## "My shortcode shows nothing"

Work down this list.

### 1. The service account lacks that tool permission

By far the most common cause. A perfectly valid token can read projects and still get 403
on RFIs, because the app manifest never declared the RFIs permission.

The endpoint probe table shows `failed` against that endpoint. Fix it in Procore: update
the app's Permissions Builder declaration and have a company administrator reinstall or
update the app. Adjusting the service account by hand in the Directory tool creates
inconsistencies and is not a fix.

### 2. Nothing to show

Not every project has drawings, specifications or open RFIs. Check in Procore directly. A
`0 records` result and a permission failure look identical from the front end — but the
probe reports the first as `ok`.

### 3. Wrong project or company ID

They come from the Procore URL:

```
https://app.procore.com/companies/4242/...   → company 4242
https://app.procore.com/projects/123/...     → project 123
```

Or run `wp procorewp projects`.

### 4. The default company is not set

Project-scoped shortcodes still need a company for the `Procore-Company-Id` header. Set
one under **Connection**, or pass `company_id` explicitly.

### 5. You are looking at a sandbox

If **Environment** is not Production, the site is showing sandbox data. An admin notice
says so.

### 6. Stale cache

Data changed in Procore but not on the page. Clear the cache under **Procore → Cache**,
or `wp procorewp cache-clear`.

**Log in as an administrator and reload the page.** Administrators see the real Procore
error message inline; visitors see a neutral notice. That one step usually ends the
investigation.

---

## Connection problems

### "Enter your Procore Client ID and Client Secret"

Neither field is populated. If you set them in `wp-config.php`, check the constant names —
`PROCOREWP_CLIENT_ID` and `PROCOREWP_CLIENT_SECRET` — and that they are defined before
`wp-settings.php` is required.

### "Procore rejected the authentication request"

- Credentials belong to a different environment. Production, Developer Sandbox and
  Monthly Sandbox each need their own.
- The app has no OAuth component with the `client_credentials` grant type.
- The secret was regenerated in the Developer Portal.
- The secret was truncated on paste. Re-enter it — the field shows a mask, and leaving it
  blank keeps the old value rather than clearing it.

### "Procore returned no companies"

The credentials authenticate but no company has installed the app. A Procore company
administrator has to install it and select which projects it may access.

### "The Procore connection has expired"

Authorization Code mode, and the refresh token was rejected. Procore invalidates a refresh
token the moment it is used, so this happens if the token was rotated elsewhere or the
user's Procore access was revoked. Click **Connect to Procore** again.

This is a good reason to prefer the service account mode on any unattended site.

### The redirect URI is rejected

It must match what is registered in the Developer Portal **exactly** — scheme, `www`, path
and query string. Copy it from the Connection screen rather than typing it.

### "Another request is currently authenticating"

Two requests tried to refresh at once and one waited. Harmless and self-clearing. If it
persists, an object cache may be dropping the lock; `wp procorewp reset-token` clears the
state.

---

## Rate limits

### "Procore is rate limiting this site"

Check remaining headroom on **Procore → Status**.

- Confirm caching is on under **Procore → Cache** — it is the single biggest factor.
- Raise the minimum cache lifetime.
- Reduce how many different shortcodes appear on one page.
- Look for a shortcode with a low `cache` value or `all="true"` on a large data set.

### "Procore requests are paused after repeated failures"

The circuit breaker opened after five consecutive failures. It resumes automatically after
five minutes, serving cached data meanwhile. Clearing the cache also resets it.

Consecutive failures usually mean a credential or permission problem, not a Procore
outage — run the connection test.

---

## Display problems

### Emails are missing

Working as intended. Publishing them needs **both** the global setting unticked under
**Display** and `show_email="true"` on the shortcode.

### Dates or currency look wrong

**Procore → Display** sets a date format override and a currency symbol. Blank uses the
site's own date format.

### Styling is missing

The stylesheet loads only on pages containing ProcoreWP output. If output comes from a
widget or a direct `do_shortcode()` call in a template, it loads in the footer instead of
the head. Check that **Load the ProcoreWP stylesheet** is on under **Display**.

### A template override is ignored

- Path: `wp-content/themes/your-theme/procorewp/collection.php`
- The filename must match the template name exactly, lowercase, `.php` extension
- A child theme takes precedence over its parent

---

## After upgrading from 1.x

### The plugin looks deactivated

Expected. The main file was renamed from `index.php` to `procorewp.php`, so WordPress sees
a different plugin. Activate ProcoreWP once — your settings are imported automatically.

### It still does not connect

Version 1.x never obtained a token, because it authenticated against the wrong host. Any
credentials it stored were never validated against a real Procore response. Re-run the
connection test; it will show which stage fails.

Also rotate the client secret. Version 1.x stored it as plaintext in an autoloaded option,
so treat it as exposed.

---

## Gathering diagnostics

Turn on **Procore → Tools → Record API diagnostics**, reproduce the problem, then read the
last 50 events on **Procore → Status**. Secrets, tokens and authorization codes are
redacted before anything is written.

With `WP_DEBUG` and `WP_DEBUG_LOG` on, the same entries also go to `debug.log`.

When reporting an issue, `wp procorewp doctor` output is the most useful single thing to
include — it reports whether credentials are set without printing them.

---

## Still stuck?

Open an issue at
[github.com/ibuilder/ProcoreWP/issues](https://github.com/ibuilder/ProcoreWP/issues) with
the `doctor` output, which endpoint probes failed, and the shortcode you are using.

Please redact any real credentials. For a security problem, use
[private reporting](https://github.com/ibuilder/ProcoreWP/security/advisories/new)
instead.
