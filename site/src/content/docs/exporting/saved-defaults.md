---
title: "Saved defaults"
description: "Save the Export screen's choices as default, per user or for the whole site, on both screens."
---

**Save as default** keeps everything you chose: content types with their items (or filters, on the classic screen), options, format, file name, media files and Playground themes and plugins. The Export screen opens with them, and **Reset to defaults** goes back to them. Nothing else is kept: choices you don't save are forgotten when you leave or reload the screen.

## Whose defaults

**Settings → General → Defaults are saved for**:

- **Each user** (default) — stored in user meta; anyone who can export saves their own.
- **The whole site** — one set for everyone; saving or clearing it needs `manage_options`.

## Both screens

- **New screen:** *Save as default* in the header; *Reset to defaults* goes back to them, and *Clear defaults* (active once the choices are the defaults) forgets them.
- **Classic screen:** check *Save these choices as my default* before downloading. The classic screen starts filled with the defaults too.

## Clearing

*Clear defaults* in the Export screen's header, *Clear saved defaults* in Settings → General, or the [`eizport/clear-export-defaults`](../../developers/abilities/#defaults) ability.

## What is checked

Defaults are sanitized when saved and again when loaded: unknown types and items, options that are never exported and themes or plugins no longer installed are dropped. Filters keep only the values that are set.
