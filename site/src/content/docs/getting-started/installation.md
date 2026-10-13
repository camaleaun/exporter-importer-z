---
title: "Installation"
description: "Install the plugin from its release zip, check the requirements, and add the WordPress Importer for imports."
---

## From the release zip

1. Download the latest release: [`eizport.zip`](https://github.com/eizport/eizport/releases/latest/download/eizport.zip). The URL always points to the newest version.
2. In WordPress, go to **Plugins → Add Plugin → Upload Plugin**, choose the file and click **Install Now**.
3. **Activate** it. Tools → Export now opens the new Export screen.

## With WP-CLI

```bash
wp plugin install https://github.com/eizport/eizport/releases/latest/download/eizport.zip --activate
```

## Try it first

Open the [live demo in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/eizport/eizport/main/playground/blueprint.json): a throwaway site in your browser with the plugin and sample pages.

## To import

Importing needs the **WordPress Importer**. On **Tools → Import**, click **Install Now** under *WordPress*, then **Run Importer**. The plugin's links lead there while the importer isn't installed. Exporting works without it.

## Requirements in detail

| Requirement | Why |
| --- | --- |
| WordPress 7.0+ | The Export and Settings screens are built with `@wordpress/build` and booted by core's `@wordpress/boot`. |
| PHP 7.4+ | The plugin's minimum. |
| `zlib` | `.wxrz` bundles are gzipped tar archives. |
| `ZipArchive` (or PclZip) | `.zip` bundles and Playground `.zip` files. Core's PclZip is the fallback. |
| Abilities API | Part of WordPress 6.9+; the screens run the plugin's abilities. |

## Uninstalling

Deleting the plugin removes `wp-content/uploads/eizport/` (temporary copies and unfinished imports), its settings and the saved export defaults (the site's and every user's).
