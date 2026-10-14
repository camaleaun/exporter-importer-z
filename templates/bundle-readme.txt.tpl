=== EIZPORT_PLUGIN_NAME ===
Requires at least: EIZPORT_REQUIRES_WP
Tested up to: EIZPORT_TESTED_UP_TO
Requires PHP: EIZPORT_REQUIRES_PHP
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EIZPORT_SHORT_DESCRIPTION

== Description ==

This plugin carries content exported from another WordPress site with Eizport (https://eizport.github.io/): pages, posts, media and settings, in one bundle. Once activated, its screen under Tools imports the bundle with the WordPress Importer, then offers to delete this plugin.

Eizport draws that screen and runs the import; without it, the screen says how to get it.

== External services ==

When its screen is opened and Eizport isn't installed, this plugin asks the WordPress.org plugin API (api.wordpress.org) whether Eizport is in the plugin directory: the request carries the name "eizport" and what WordPress sends with every such request (its version, the site's language and address). "Install Now" installs Eizport or the WordPress Importer from downloads.wordpress.org, through WordPress's own installer, only when you click it. WordPress.org privacy policy: https://wordpress.org/about/privacy/

When Eizport isn't in the directory, the screen links to its releases on GitHub (github.com/eizport/eizport) instead; nothing is sent unless you click it. Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service — privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

Like the WordPress Importer, the import downloads media files the bundle doesn't include from their original addresses.
