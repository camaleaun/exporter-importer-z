---
title: "Media"
description: "Which attachments come with the items exported, how media files go inside bundles, and the \"Only attached media\" setting."
---

## Which attachments are exported

When you export **some** items (not all content):

1. **WordPress** adds each item's attached media (`post_parent`) and featured image (`_thumbnail_id`).
2. **The plugin** also adds the media the items **use in their content without being attached**: images, galleries, covers, files, video and audio inserted from the library. It finds them by:
   - block attributes: `"id"` (image, cover, file, video, audio), `"mediaId"` (media & text), `"ids"` (gallery);
   - `wp-image-<id>` classes and the `[gallery ids="…"]` shortcode;
   - upload URLs, sized ones included (`-300x200`), through `attachment_url_to_postid()`.
3. **Plugins** add more with [`eizport_media_used`](../../developers/hooks/#eizport_media_used) — the WooCommerce compatibility adds product galleries.

Turn step 2 off with **Settings → Export → Only attached media**, or per export with `media_used: false`.

## Media files in the bundle

**Media files** (on by default) puts the files of every exported attachment inside the bundle, under `uploads/` with the same folders as on the old site. On import they are served to the importer from the bundle, so nothing is downloaded and the old site can be offline.

Only files in this site's uploads folder go in; offloaded media (a CDN, S3) is left to the importer to download, as usual.

## Example

A page with an attached image (A), a different featured image (B) and an image from the library in its content (C):

| Setting | Exported |
| --- | --- |
| Default | the page, A, B, C |
| Only attached media | the page, A, B |
