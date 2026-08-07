---
title: Home
layout: default
nav_order: 1
---

# ProcoreWP

Publish live [Procore](https://www.procore.com/) construction project data on a WordPress
site — with shortcodes, blocks, and a cached REST proxy.

Everything ProcoreWP does is **read-only**. It never writes to Procore.

```
[procore_project_list company_id="4242" limit="10"]
[procore_project id="123"]
[procore_rfis id="123" status="open" limit="5"]
```

---

## Start here

| | |
|---|---|
| [Installation](installation/) | Get the plugin running |
| [Authentication](authentication/) | Create a Procore app and connect |
| [Shortcodes](shortcodes/) | Full reference for all eighteen |
| [Blocks](blocks/) | Using ProcoreWP in the block editor |
| [Caching & rate limits](caching/) | How ProcoreWP stays inside Procore's quotas |
| [Templating & styling](templating/) | Change the markup and the CSS |
| [REST API](rest-api/) | The optional read-only proxy |
| [WP-CLI](wp-cli/) | Command line operations |
| [Troubleshooting](troubleshooting/) | When a shortcode shows nothing |
| [Upgrading from 1.x](upgrading/) | What changed and what you must do |

---

## What it can display

**Projects and company** — project directories, project detail panels, single project
fields, project images, team members, company vendors, company offices, and project
locations with geo microdata.

**Documents** — drawing areas and specification sections.

**Project tools** — RFIs, submittals, punch lists, observations, daily logs, change
orders and schedule milestones.

**Anything else** — the generic `[procore_data]` shortcode renders any endpoint published
in the plugin's [endpoint registry](shortcodes/#the-generic-reader).

---

## Why version 2 is a rewrite

Version 1.x could not work against the live Procore API. It sent its token request to
`api.procore.com`, but Procore serves authentication from `login.procore.com` — a
different host — so no access token was ever issued, on any install, regardless of
configuration.

Underneath that, it sent the company scope as a query parameter where Procore requires a
request header, stored the client secret as plaintext in an option loaded on every page
view, ran its connection test from an unverified `POST`, and cached nothing at all
against an API with a documented ten-second spike limit.

Version 2.0.0 fixes all of that and adds two-mode authentication, caching, rate-limit
handling, encrypted credential storage, eleven new shortcodes, blocks, a REST proxy and
WP-CLI. The complete list is in the
[changelog](https://github.com/ibuilder/ProcoreWP/blob/main/CHANGELOG.md).

---

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later
- A Procore account with API access

---

## Third-party service

ProcoreWP contacts Procore to retrieve the data you ask it to display: `login.procore.com`
for authentication and `api.procore.com` for data, or the sandbox or regional hosts you
configure.

It sends your Client ID and Client Secret during authentication, plus the company and
project identifiers you configure. **No visitor data is sent to Procore.**

[Procore terms of service](https://www.procore.com/legal/termsofservice) ·
[Procore privacy policy](https://www.procore.com/legal/privacy)
