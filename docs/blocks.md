---
title: Blocks
layout: default
nav_order: 5
---

# Blocks

Connect for Procore registers a single **Procore** block with one inserter variation per shortcode.
Search the inserter for "Procore" and you will see entries for RFIs, submittals, the
project list and the rest.

Blocks are on by default and can be turned off under **Procore → Tools**. Shortcodes work
either way, including inside the Shortcode block.

## Using it

1. Insert a **Procore: …** block, or insert **Procore** and pick what to show from the
   sidebar.
2. Set the project or company ID in **Options**.
3. The preview renders live, using the same code the front end uses.

The sidebar only offers the attributes that apply to the selected data type — an image
block shows a width control, a list block shows sorting and limits. Anything not surfaced
in the UI is still reachable through the equivalent shortcode.

| Control | Applies to |
|---|---|
| Show | All — which data to render |
| Project ID / Company ID | All |
| Endpoint | The generic reader |
| Field / Label | Single field |
| Heading | Lists and records |
| Maximum rows | Lists |
| Filter by status | Project tools |
| Sort by field / direction | Lists |
| Columns | Lists |
| Image width | Featured image |
| Show email addresses | Team |
| Fetch every page | Lists |
| Extra CSS classes | All |

Alignment, spacing and colour come from WordPress's own block supports.

## How it renders

The block is server-rendered: `save()` returns `null`, and the front end calls the same
shortcode handler. That means markup, escaping, caching and error handling are identical
between a block and a shortcode — there is no second rendering path to keep in sync.

It also means credentials and cached payloads never reach the editor as block content;
the editor requests a rendered preview from the server like any other dynamic block.

## No build step

The editor script is hand-written ES5 against the global `wp.*` runtime. There is no
`node_modules`, no bundler and no compiled output.

This is deliberate. Shipping a minified bundle without its source is a wordpress.org
review flag, it makes the plugin unauditable, and it puts Node between a contributor and
a one-line fix. The file in `blocks/procore/index.js` is the file that runs.

## Turning blocks off

**Procore → Tools → Register Connect for Procore blocks.** Existing block content stops rendering
while this is off; converting those blocks to their shortcode equivalents first avoids
surprises.
