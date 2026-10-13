# Eizport — brand

How to show Eizport: the mark and what it means, the logo’s versions and space, colors, type, icons and voice. The files are in [`brand/`](brand/), built by [`scripts/brand.mjs`](scripts/brand.mjs) (`npm run brand`).

<p><img src="brand/logo-horizontal.svg" alt="Eizport, horizontal logo" width="412" height="64"></p>

## Concept

The **Z** is what sets the name apart, so the mark is a Z — drawn as **two arrows**:

- **the top stroke leaves** (white): export, content going out of a site;
- **the bottom stroke arrives** (orange `#f2994a`): import, content landing on another;
- **the diagonal joins them**: one bundle, one trip;
- **the square is WordPress’s admin blue** (`#3858e9`), rounded at 14/64 (22%): it feels at home next to the screens it lives in.

Round caps and joins, one stroke width — the same line as the icons.

<p><img src="brand/mark.svg" alt="The mark" width="96" height="96"></p>

## Logo

| Version | File | Use |
| --- | --- | --- |
| Horizontal | [`logo-horizontal.svg`](brand/logo-horizontal.svg) | Default: headers, footers, README |
| Horizontal, dark | [`logo-horizontal-dark.svg`](brand/logo-horizontal-dark.svg) | On dark backgrounds (light wordmark, mark unchanged) |
| Stacked | [`logo-stacked.svg`](brand/logo-stacked.svg) | Square spaces, covers |
| Mark | [`mark.svg`](brand/mark.svg) | Icons, favicon, the WordPress.org plugin icon |
| Mark, one color | [`mark-mono.svg`](brand/mark-mono.svg) | Uses `currentColor` |
| Mark, white | [`mark-white.svg`](brand/mark-white.svg) | On photos or dark color |

The wordmark is “Eizport” in **Inter SemiBold** with a tight track, its **z** in the primary blue (orange on dark) — the mark's Z. The SVGs have the wordmark as outlines, so they render the same anywhere. In running text, write the name in full: Eizport.

## Clear space and sizes

- **Clear space:** a quarter of the mark’s height on every side; nothing else enters it.
- **Smallest mark:** 16px (favicon).
- **Smallest horizontal logo:** 120px wide.

## Don’ts

- Don’t recolor the arrows or swap them: out is white, in is orange.
- Don’t rotate, mirror or stretch the mark — the Z reads left to right.
- Don’t put the mark on a busy photo or on the primary blue itself.
- Don’t set the name in another typeface, or split the Z from it.
- Don’t use the WordPress logo next to ours as if they were one brand.
- Don’t use orange for text on white: it fails contrast.

## Color

The neutrals are WordPress’s admin grays; the primary is its admin blue. Contrast is measured against the color used on it (WCAG).

| Name | Hex | Token | On | Contrast | Use |
| --- | --- | --- | --- | --- | --- |
| Primary | `#3858e9` | `--eiz-primary` | white | 5.61:1 | Links, primary buttons, the mark’s square |
| Primary strong | `#1d35b4` | `--eiz-primary-strong` | white | 9.46:1 | Hover, the gradient’s start |
| Accent | `#f2994a` | `--eiz-accent` | ink | 7.49:1 | The import arrow, highlights — never text on white |
| Ink | `#1e1e1e` | `--eiz-ink` | white | 16.67:1 | Text |
| Muted | `#50575e` | `--eiz-muted` | white | 7.33:1 | Secondary text |
| Night | `#0f1115` | `--eiz-night` | white | 18.90:1 | Dark backgrounds, the gradient’s end |
| Surface | `#f6f7f7` | `--eiz-surface` | ink | 15.53:1 | Bands, panels |
| Border | `#dcdcde` | `--eiz-border` | — | — | Lines, dividers |

**Gradient:** `#1d35b4 → #0f1115` at 160°, with an orange glow (`rgb(242 153 74 / .22)`) — the site’s hero, the social image and the WordPress.org banners. Dark-mode values of each token are in [DESIGN.md](DESIGN.md).

## Typography

**Inter**, variable, self-hosted (one Latin woff2, preloaded). Headings Bold with a tight track (−0.02em, −0.035em for the h1); text Regular at 16px or more, line height 1.6. Code in the system’s monospace. Fluid scale: h1 36–64px, h2 28–40px, h3 20–24px, lead 18–21px.

## Icons

Line icons on a 24px grid, 1.75px stroke, round caps and joins — the mark’s line at small size ([`src/components/Icon.astro`](src/components/Icon.astro)).

## Voice

- **Short and plain.** Say what happens: “Media comes from the file.” Not: “Leverage seamless asset portability.”
- **WordPress’s words.** Name screens as WordPress does: Tools → Export, the WordPress Importer, Settings. Bold for what you click.
- **Honest about limits.** Say what isn’t exported and why — site URLs, keys, roles — before someone wonders.

## Other files from the brand

`npm run brand` also writes the favicon (`public/favicon.svg`), the touch icon (`public/apple-touch-icon.png`), the social image (`public/og-image.png`, 1200×630) and the WordPress.org assets (`../.wordpress-org/icon-*.png`, `icon.svg`, `banner-772x250.png`, `banner-1544x500.png`).
