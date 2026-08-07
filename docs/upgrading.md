---
title: Upgrading from 1.x
layout: default
nav_order: 11
---

# Upgrading from 1.x

## Two things you must do

### 1. Activate the plugin once

The main plugin file was renamed from `index.php` to `procorewp.php`. WordPress identifies
a plugin by its file path, so it treats 2.0.0 as a different plugin and shows it as
inactive.

Go to **Plugins**, find **ProcoreWP**, and activate it. Your settings are imported
automatically by the upgrade routine — you do not need to re-enter anything.

The rename was unavoidable: a main file called `index.php` fails Plugin Check and cannot
be submitted to wordpress.org.

### 2. Re-run the connection test

**Procore → Connection → Test connection.**

Version 1.x sent its token request to `api.procore.com/oauth/token`. Procore serves
authentication from `login.procore.com/oauth/token` — a different host — so no token was
ever issued, on any 1.x install. Whatever credentials you had were never validated against
a real Procore response, so verify them now rather than assuming an empty shortcode is a
new bug.

### And rotate your secret

Version 1.x stored the client secret, access token and refresh token as plaintext in an
autoloaded WordPress option — read into memory on every single page request, and present
in every database backup. Treat that secret as exposed: regenerate it in the Procore
Developer Portal and enter the new one.

---

## What keeps working

**All of it.** Every 1.x shortcode tag is preserved, and the legacy `id` attribute is
still accepted as an alias for `project_id`. This page from 1.x renders unchanged:

```
[procore_featured_image id="123" company_id="456" width="600"]
[procore_project id="123" company_id="456"]
[procore_team id="123" company_id="456"]
[procore_drawings id="123" company_id="456" limit="5"]
[procore_project_data id="123" company_id="456" field="budget" label="Budget"]
[procore_project_list company_id="456" show_details="true" active_only="true"]
```

Your Client ID, Client Secret and default company ID are migrated from the old
`procore_integration_settings` option. The secret is re-encrypted on the way in, and the
old plaintext option is deleted. The stale token is discarded, since it was never valid.

---

## Behaviour changes worth knowing

| | 1.x | 2.0.0 |
|---|---|---|
| Custom CSS | Edit files in the plugin folder | **Procore → Display → Custom CSS**, or a theme template override |
| Email addresses | Published by default | Suppressed by default; needs two opt-ins |
| API errors on the front end | Shown to everyone | Administrators only |
| `limit="0"` | Returned page one, silently | Returns page one; add `all="true"` for every page |
| Settings location | Settings → Procore Integration | A top-level **Procore** menu |
| Caching | None | On by default |

### Custom CSS will be lost

If you edited `assets/css/procore-integration.css` inside the plugin folder — which the
1.x README instructed — those changes are gone, as they were on every previous update.

Copy them into **Procore → Display → Custom CSS** before or after upgrading. Class names
have changed from `procore-*` to `procorewp-*`; the mapping is in
[Templating & styling](../templating/#styling-reference).

### Email addresses stop appearing

`[procore_team]` published every member's email address on a public page by default. It no
longer does. To restore the old behaviour, untick **Never output email addresses** under
**Display** *and* add `show_email="true"` to the shortcode.

Worth thinking about before you do: those addresses belong to staff and subcontractors who
did not choose to have them published.

### A stale page may look different

With caching on, a project page can be up to fifteen minutes behind Procore by default.
Adjust lifetimes under **Procore → Cache**, but do not disable caching on a public site —
[the reason why](../caching/#why-this-matters).

---

## Rolling back

Deactivate 2.0.0 and reactivate the 1.x plugin. Its option was deleted during migration,
so you would need to re-enter credentials.

This is not recommended. Version 1.x cannot authenticate against Procore's live API, and
it stores your credentials in plaintext.

---

## Removing 1.x files

Once 2.0.0 is running, delete the old plugin directory
(`wp-content/plugins/procore-integration/`) from the Plugins screen. Its uninstall routine
in 2.0.0 also removes the legacy `procore_integration_settings` option if any trace
remains.

---

## Fresh install instead

If it is simpler, you can start clean:

1. Note your Client ID, Client Secret and company ID.
2. Delete the 1.x plugin.
3. Install 2.0.0 and follow [Installation](../installation/).
4. Rotate the client secret in the Developer Portal.

Your existing pages and shortcodes need no changes either way.
