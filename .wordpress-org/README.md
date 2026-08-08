# WordPress.org assets

These files are read by wordpress.org from the plugin's **SVN `assets/` directory**, not
from the distributed ZIP. `.distignore` therefore excludes this folder from releases.

At submission time, copy the contents of this directory into the SVN `assets/` folder:

```
svn/assets/
├── banner-1544x500.png
├── banner-772x250.png
├── icon-256x256.png
└── icon-128x128.png
```

## What's here

| File | Purpose |
|---|---|
| `banner-1544x500.png` | Plugin page header, retina |
| `banner-772x250.png` | Plugin page header, standard |
| `icon-256x256.png` | Search results and the plugin card, retina |
| `icon-128x128.png` | Search results and the plugin card, standard |

The mark is three rising bars on a baseline — construction progress — with the tallest in
the accent orange. It was chosen to stay legible at the 32px size WordPress uses in the
installed-plugins list, which rules out anything with fine detail or text.

Palette: `#0B2742` → `#1B5E8C` (blueprint blue) with `#FF6B35` as the accent.

Regenerate them with the script in the project's scratchpad, or edit and re-export at the
exact pixel dimensions above — wordpress.org will not scale them for you.

## Screenshots

**There are deliberately none.**

`readme.txt` previously declared six screenshots that did not exist, which renders as six
broken slots on the plugin page. That claim has been removed rather than satisfied with
fabricated ones.

Screenshots for this plugin should be captured from a site connected to a **real Procore
account**, because the whole point of the listing is showing real project data flowing
into WordPress. A gallery built from invented project names and staff would be
misleading, and the connection-test screen — the most useful thing to show — is only
meaningful when it is probing genuine tool permissions.

When you are ready to submit, capture these six at 1280px wide, drop them in this folder
as `screenshot-1.png` … `screenshot-6.png`, and restore the `== Screenshots ==` section
to `readme.txt` with matching captions in the same order:

1. **Procore → Connection** — authentication mode, environment and credentials
2. **The connection test** — six stages plus the endpoint permission probe
3. **Procore → Shortcodes** — the generated reference
4. **Procore → Status** — rate-limit headroom and cache statistics
5. **A project page on the front end** — `[procore_project]` and `[procore_team]`
6. **The Procore block in the editor** — sidebar plus live preview

Redact anything commercially sensitive: company names, project values, and staff email
addresses. Note that the plugin suppresses emails by default, so screenshot 5 will not
leak them unless both privacy opt-ins have been turned off.
