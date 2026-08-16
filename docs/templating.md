---
title: Templating & styling
layout: default
nav_order: 7
---

# Templating and styling

## Do not edit the plugin

ProcoreWP 1.x told you to edit `assets/css/procore-integration.css` inside the plugin
folder. Every plugin update overwrote it and your work was gone.

There are two supported ways to change the output, and both survive updates.

## Custom CSS

**Procore → Display → Custom CSS.** Added inline after the plugin stylesheet, so your
rules win without needing `!important`. Markup is stripped on save.

```css
.procore-connect-table thead th {
	background: #0d3b66;
	color: #fff;
}

.procore-connect-status--active {
	background: #d1f4dd;
}
```

To drop the plugin stylesheet entirely and style everything from your theme, untick
**Load the Connect for Procore stylesheet**.

## Template overrides

Copy a template out of the plugin and into your theme:

```
wp-content/plugins/connect-for-procore/templates/collection.php
  → wp-content/themes/your-theme/procore-connect/collection.php
```

The lookup order is child theme, then parent theme, then the plugin's own default.

| Template | Used by |
|---|---|
| `collection.php` | Every list shortcode |
| `record.php` | `[procore_project]` |
| `field.php` | `[procore_project_data]` |
| `image.php` | `[procore_featured_image]` |
| `map.php` | `[procore_project_map]` |

### Per-shortcode templates

Add a file and point a shortcode at it:

```
[procore_rfis id="123" template="rfi-cards"]
```

Resolves to `yourtheme/procore-connect/rfi-cards.php`.

### What a template receives

Everything arrives in `$data`.

```php
$data['rows']       // array of records (collection templates)
$data['record']     // single record (record template)
$data['columns']    // column definitions: key, label, format
$data['title']      // heading text
$data['class']      // sanitized class attribute
$data['show_email'] // whether the shortcode opted in to email output
$data['atts']       // the sanitized shortcode attributes
$data['tag']        // the shortcode tag
```

### Escaping

Get cell content from `Format::cell()`. It escapes everything it returns, and the only
markup it emits is a status badge or a link.

```php
<?php

use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

$rows    = (array) ( $data['rows'] ?? array() );
$columns = (array) ( $data['columns'] ?? array() );
?>
<ul class="my-project-cards">
	<?php foreach ( $rows as $row ) : ?>
		<li>
			<?php foreach ( $columns as $column ) : ?>
				<span class="label"><?php echo esc_html( $column['label'] ); ?></span>
				<span class="value"><?php echo wp_kses_post( Format::cell( $row, $column, ! empty( $data['show_email'] ) ) ); ?></span>
			<?php endforeach; ?>
		</li>
	<?php endforeach; ?>
</ul>
```

Keeping every value on that one path is what makes the escaping guarantee auditable.
Reaching into `$row` and echoing raw values reintroduces exactly the XSS surface the
rewrite closed — Procore field values are user-entered data.

### Reading fields directly

`Arr` handles the sparse payloads Procore returns; it tolerates missing keys rather than
warning.

```php
use ProcoreConnect\Support\Arr;
use ProcoreConnect\Support\Format;

echo esc_html( Arr::str( $row, 'vendor.name', 'Unassigned' ) );
echo esc_html( Format::date( Arr::get( $row, 'due_date' ) ) );
echo esc_html( Format::location( $row ) );
```

## Changing columns

Without touching a template:

```
[procore_rfis id="123" columns="number,subject,assignee.name,due_date"]
```

Unknown fields get a title-cased label and plain text formatting. To change a default
column map permanently:

```php
add_filter( 'procore_connect_shortcodes', function ( array $shortcodes ): array {
	$shortcodes['procore_rfis']['columns'] = array(
		array( 'key' => 'number',  'label' => 'RFI',   'format' => 'text' ),
		array( 'key' => 'subject', 'label' => 'Query', 'format' => 'text' ),
		array( 'key' => 'due_date', 'label' => 'Due',  'format' => 'date' ),
	);
	return $shortcodes;
} );
```

### Column formats

| Format | Renders |
|---|---|
| `text` | Escaped text; related records use their `name`/`title` |
| `date` | Site date format, or the plugin override |
| `currency` | Configured symbol plus a localised number |
| `percent` | Rounded, with a `%` sign |
| `status` | An Active/Inactive badge |
| `email` | A `mailto:` link, subject to both privacy opt-ins |
| `url` | An external link labelled with the hostname |

The special key `__location` composes city, state and country from the record.

## Styling reference

| Class | Element |
|---|---|
| `.procore-connect` | Every wrapper |
| `.procore-connect-title` | Headings |
| `.procore-connect-table-wrap` | Horizontal scroll container |
| `.procore-connect-table` | Tables |
| `.procore-connect-details` / `.procore-connect-detail` | Detail lists |
| `.procore-connect-field__label` / `__value` | Single fields |
| `.procore-connect-status--active` / `--inactive` | Status badges |
| `.procore-connect-map__list` / `__item` / `__name` | Location lists |
| `.procore-connect-notice--error` / `--empty` | Notices |

The bundled stylesheet collapses tables into stacked rows below 600px, using each cell's
`data-label`, and respects `prefers-color-scheme: dark`.
