---
title: Installation
layout: default
nav_order: 2
---

# Installation

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later
- A Procore account with API access
- OpenSSL (present on essentially every host; without it, credentials cannot be encrypted
  at rest and you should use the `wp-config.php` constants below instead)

## Install the plugin

### From a release ZIP

1. Download the latest ZIP from
   [Releases](https://github.com/ibuilder/ProcoreWP/releases).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP, click **Install Now**, then **Activate**.

### With Composer

```bash
composer require ibuilder/procorewp
```

### From source

```bash
cd wp-content/plugins
git clone https://github.com/ibuilder/ProcoreWP.git procorewp
```

No build step is required. The plugin ships ready to run — the block editor script is
hand-written ES5, not a compiled bundle.

## First run

1. Go to **Procore → Connection**.
2. Choose an authentication mode. Use **Service Account (Client Credentials)** unless you
   have a specific reason not to — see [Authentication](../authentication/).
3. Enter your Client ID and Client Secret.
4. Click **Look up companies** and pick a default company.
5. Click **Test connection**.

The connection test reports each stage separately and then probes every endpoint, so you
can see exactly which Procore tool permissions your credentials hold. That table is
usually the fastest answer to "why is my shortcode empty".

## Keeping credentials out of the database

By default the client secret is encrypted with AES-256-GCM before it is stored. For the
strongest protection, define it in `wp-config.php` instead — these constants take
precedence over the admin fields and are never written to an option:

```php
define( 'PROCOREWP_CLIENT_ID', 'your-client-id' );
define( 'PROCOREWP_CLIENT_SECRET', 'your-client-secret' );
define( 'PROCOREWP_COMPANY_ID', 4242 );
```

When these are set, the Connection screen shows a note in place of the credential fields.

## Environments

**Procore → Connection → Environment** selects which Procore hosts are used:

| Environment | Login host | API host |
|---|---|---|
| Production | `login.procore.com` | `api.procore.com` |
| Developer Sandbox | `login-sandbox.procore.com` | `sandbox.procore.com` |
| Monthly Sandbox | `login-sandbox-monthly.procore.com` | `api-monthly.procore.com` |
| Custom | your own HTTPS host | your own HTTPS host |

Each environment needs its own credentials — tokens are not shared between them. Choose
**Custom** for regional or federal zones and enter both hosts; only HTTPS is accepted.

When a non-production environment is selected, an admin notice makes that obvious, so
nobody mistakes sandbox data for live data on a published page.

## Adding data to a page

Add a shortcode:

```
[procore_project_list limit="10"]
```

Or insert the **Procore** block and pick what to show from the sidebar. Both render
through the same code, so they produce identical markup.

## Uninstalling

Deleting the plugin removes its settings, credentials, tokens, cached responses and
diagnostic log. To keep them — for example when moving hosts — tick **Keep settings and
cached data when the plugin is deleted** under **Procore → Tools** before deleting.

Deactivating (rather than deleting) clears the cache and the cron job but leaves settings
in place.
