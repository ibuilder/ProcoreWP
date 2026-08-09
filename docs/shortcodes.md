---
title: Shortcodes
layout: default
nav_order: 4
---

# Shortcode reference

Every 1.x shortcode name is preserved, and the legacy `id` attribute still works as an
alias for `project_id`, so pages written for the old plugin keep rendering.

A live version of this reference — generated from the same registry the code uses, so it
can never drift — is inside the plugin at **Procore → Shortcodes**.

---

## Shared attributes

Accepted by every shortcode.

| Attribute | Default | Notes |
|---|---|---|
| `company_id` | Site default | Falls back to **Procore → Connection → Default company** |
| `project_id` | Site default | `id` is accepted as an alias |
| `limit` | `0` (all) | Caps the rendered rows |
| `page` | `1` | Which page of results to request |
| `orderby` | — | Any field path, e.g. `name` or `vendor.name` |
| `order` | `asc` | `asc` or `desc` |
| `columns` | Per shortcode | Comma-separated field paths; see [Empty columns](#empty-columns) |
| `template` | Per shortcode | Template name, overridable in your theme |
| `class` | — | Extra CSS classes, sanitized |
| `title` | Per shortcode | Heading text; pass `-` to omit the heading |
| `cache` | Endpoint default | Lifetime in seconds; cannot go below the site floor |
| `empty_text` | "No records were found." | Shown when nothing comes back |
| `show_email` | `false` | See [Privacy](#privacy) |
| `all` | `false` | Follow pagination and collect every page |

Records missing the `orderby` field always sort last, in both directions.

### Empty columns

A default column that no record fills in is not rendered. Procore projects vary in
which tools they use and which fields they populate, so a default column set that
suits one project can produce a column of dashes on another. Rather than show a
header above nothing, the column is left out.

This applies only to the default columns — the ones you did not choose. If you pass
`columns=` the list is rendered exactly as written, empty or not, because you asked
for it:

```
[procore_rfis id="123"]                          Due Date drops if no RFI has one
[procore_rfis id="123" columns="number,due_date"] Due Date always renders
```

A table is never reduced to no columns at all.

---

## Projects

### `[procore_project_list]`

A table of projects in a company.

```
[procore_project_list company_id="4242" limit="10" active_only="true" orderby="name"]
```

| Attribute | Default | Notes |
|---|---|---|
| `active_only` | `false` | Hide inactive projects |

Default columns: ID, project name, project number, location, status.

### `[procore_project]`

One project as a labelled detail panel. Empty fields are omitted rather than rendered
blank.

```
[procore_project id="123"]
```

### `[procore_project_data]`

A single field from a project record.

```
[procore_project_data id="123" field="start_date" label="Ground broken"]
[procore_project_data id="123" field="total_value" format="currency"]
```

| Attribute | Notes |
|---|---|
| `field` | **Required.** Must be allow-listed |
| `label` | Defaults to a title-cased version of the field name |
| `format` | `date`, `currency`, `number`, or omit to infer from the field name |

Fields are allow-listed rather than arbitrary, because a Procore project payload contains
internal identifiers and personal data that should not be publishable by typing a field
name into a page. Fields ending `_date` or `_at` format as dates automatically; fields
ending `value`, `budget`, `total` or `amount` format as currency.

To publish a field that is not on the list:

```php
add_filter( 'procore_connect_allowed_project_fields', function ( array $fields ): array {
	$fields[] = 'custom_field_name';
	return $fields;
} );
```

### `[procore_featured_image]`

The project logo or photo.

```
[procore_featured_image id="123" width="600"]
```

| Attribute | Default |
|---|---|
| `width` | `300` |
| `height` | auto |
| `alt` | The project name |
| `lazy` | `true` |

### `[procore_project_map]`

Projects that have coordinates, as an accessible list carrying geo microdata.

```
[procore_project_map limit="20"]
```

No mapping library is loaded. Bundling one would send visitor IP addresses to a
third party without consent, and remotely hosted assets are not permitted on
wordpress.org. Instead each item carries `data-latitude` and `data-longitude`, and the
container carries the full point set as JSON in `data-procore-connect-points` — so a theme can
attach whichever mapping provider the site already licenses. Each entry also links to
OpenStreetMap.

---

## People and companies

### `[procore_team]`

```
[procore_team id="123"]
[procore_team id="123" show_email="true"]
```

Columns: name, role, company, email.

Email output requires **two** opt-ins — see [Privacy](#privacy).

### `[procore_vendors]`

Companies in the Procore company directory.

```
[procore_vendors orderby="name" limit="50"]
```

### `[procore_offices]`

```
[procore_offices]
```

---

## Documents

### `[procore_drawings]`

```
[procore_drawings id="123" limit="5"]
```

### `[procore_specifications]`

```
[procore_specifications id="123" limit="10"]
```

---

## Project tools

All accept `status` to filter, plus the shared attributes.

```
[procore_rfis id="123" status="open" limit="5"]
[procore_submittals id="123" orderby="due_date"]
[procore_punch_list id="123" status="open"]
[procore_observations id="123"]
[procore_daily_logs id="123" limit="7"]
[procore_change_orders id="123"]
[procore_milestones id="123"]
```

| Shortcode | Default columns |
|---|---|
| `procore_rfis` | Number, subject, status, due date |
| `procore_submittals` | Number, title, status, due date |
| `procore_punch_list` | Item, description, status, due date |
| `procore_observations` | Number, observation, status, due date |
| `procore_daily_logs` | Date, notes, status |
| `procore_change_orders` | Number, title, status, value |
| `procore_milestones` | Task, start, finish, percent complete |

---

## The generic reader

### `[procore_data]`

Renders any endpoint published in the plugin's registry, without needing a bespoke
shortcode.

```
[procore_data endpoint="rfis" project_id="123" columns="number,subject,status,due_date"]
```

| Attribute | Notes |
|---|---|
| `endpoint` | **Required.** Must be a published registry slug |

Published endpoints: `projects`, `project`, `project_users`, `project_vendors`,
`company_vendors`, `offices`, `drawing_areas`, `drawing_revisions`,
`specification_sections`, `rfis`, `submittals`, `punch_items`, `observations`,
`daily_logs`, `change_orders`, `milestones`.

Identity endpoints such as `me` and `companies` are deliberately **not** published and
cannot be reached from a page.

To register your own — useful when Procore bumps a resource version and a path changes:

```php
add_filter( 'procore_connect_endpoints', function ( array $endpoints ): array {
	$endpoints['meetings'] = array(
		'label'      => 'Meetings',
		'path'       => '/rest/v1.0/projects/{project_id}/meetings',
		'scope'      => 'project',
		'paginated'  => true,
		'ttl'        => 900,
		'permission' => 'Meetings: Read Only',
		'fields'     => array( 'id', 'title', 'date' ),
		'public'     => true,
	);
	return $endpoints;
} );
```

Nothing outside this registry is callable. That boundary is what makes both
`[procore_data]` and the public REST proxy safe to expose.

---

## Privacy

Procore project directories contain personal data. Publishing them exposes staff and
subcontractors to address harvesting, so Procore Connect will not output an email address
unless **both** of the following are true:

1. **Procore → Display → Never output email addresses** is unticked, and
2. the individual shortcode carries `show_email="true"`.

Even then, addresses pass through `antispambot()`.

Procore's own error messages routinely name accounts, projects and permissions. Those are
shown to users with `manage_options` only; everyone else sees a neutral notice.

---

## Caching per shortcode

`cache` sets a lifetime in seconds for that shortcode's data:

```
[procore_rfis id="123" cache="300"]
```

It can only lengthen the lifetime, never shorten it below the site floor set in
**Procore → Cache**. A page author cannot configure a page into a rate-limit breach. See
[Caching & rate limits](../caching/).
