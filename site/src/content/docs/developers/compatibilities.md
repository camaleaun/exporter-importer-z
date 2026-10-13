---
title: "Compatibilities"
description: "Which plugins are supported (WooCommerce) and which aren't officially yet (ACF, Elementor), how support is built from the public hooks, and how a plugin ships its own."
---

Support for another plugin is a **compatibility**: a file that uses only the public [hooks](../hooks/). The plugin ships one, for WooCommerce; any plugin can add its own — or replace the bundled one.

## At a glance

| Plugin | Status | What to expect |
| --- | --- | --- |
| **WooCommerce** | Supported | Products, variations, orders and coupons as content types; shop, cart, checkout, my account and terms pages as linked settings; product galleries and category images; products shown by blocks suggested; product reviews in their own row; the product lookup table refreshed after an import |
| **Advanced Custom Fields (ACF)** | Not officially supported yet, not tested | See below |
| **Elementor** | Not officially supported yet, not tested | See below |

Not tested means just that: nothing here was checked against these plugins. What follows comes from how they store their data, and from what the WordPress Importer does with it.

### Advanced Custom Fields

- **Field values** live in each post's custom fields (post meta), and the WXR carries every post's custom fields, so they come along with the items exported.
- **Fields that hold IDs** (image, file, gallery, relationship, post object, page link, user) keep the IDs of the old site: the WordPress Importer doesn't remap IDs inside custom fields. Where the other site gives the items new IDs, those fields point to the wrong item or to nothing.
- **Field groups** are stored by ACF as its own post types, or in local JSON files in the theme; neither is chosen for you. Export them with ACF's own tools (*ACF → Tools → Export*), or keep them in the theme's JSON.
- **Options pages** store their values in site options (names starting with `options_`), which the **Options** row can export by name.

### Elementor

- **Designs** live in each page's custom fields (`_elementor_data`, a JSON holding the widgets, with image IDs and URLs), so they come along with the pages.
- **IDs and URLs inside that JSON** aren't remapped by the WordPress Importer: images can keep pointing to the old site's IDs and addresses.
- **Global styles and templates** (the site kit, saved templates) are Elementor's own content; Elementor's *Import / Export Kit* tool is made for them.
- **Generated CSS** files aren't exported; Elementor builds them again (*Elementor → Tools → Regenerate CSS & Data*).

Using one of these and want it supported? [Open an issue](https://github.com/eizport/eizport/issues) with what you export — or write a compatibility, below.

## The WooCommerce compatibility

`includes/compat/woocommerce.php`, registered on `eizport_register_compat` when `WooCommerce` exists:

| Hook | Adds |
| --- | --- |
| `eizport_linked_options` | Shop, Cart, Checkout, My account and Terms and conditions pages, as roles |
| `eizport_option_groups` | The *WooCommerce pages* group |
| `eizport_denied_options` | `woocommerce_version`, `woocommerce_db_version` |
| `eizport_media_used` | Product and variation galleries (`_product_image_gallery`), and the images of the products' categories |
| `eizport_term_media` | Product categories' `thumbnail_id`, mapped to the imported image |
| `import_end` | Saves each imported product the way WooCommerce does and refreshes its row in `wc_product_meta_lookup` (prices, SKU, stock: what the shop's filters and sorting read) |
| `eizport_suggested_items` | Products that blocks and `[products ids="…"]` show in the items chosen |
| `export_wp` + `query` | Product reviews: only the ones chosen, when the export says which (`reviews`) |

## Writing one

```php
add_action( 'eizport_register_compat', function () {
	if ( ! class_exists( 'My_Plugin' ) ) {
		return;
	}
	add_filter( 'eizport_linked_options', 'my_plugin_eizport_linked' );
	add_filter( 'eizport_option_groups', 'my_plugin_eizport_groups' );
	add_filter( 'eizport_denied_options', 'my_plugin_eizport_denied' );
} );
```

`eizport_register_compat` fires on `plugins_loaded` at priority 20, after every plugin has loaded, so `class_exists()` checks are reliable.

## Replacing the bundled one

A plugin that ships its own support removes the bundled one first:

```php
remove_action( 'eizport_register_compat', 'Eizport\Compat\WooCommerce\register' );
```

## Testing

Test the compatibility's functions directly, so tests pass without the other plugin installed, and check that it registers only when that plugin is active.
