# Eizport site — design system

What the product site is made of: tokens, breakpoints and components. For the logo, colors’ meaning and voice, see [BRAND.md](BRAND.md).

## Principles

- **Feels like WordPress.** Admin blue and grays, 40px controls, the same breakpoints as blocks.
- **Semantic first.** Landmarks, one `h1` per page, real tables with `caption` and `th scope`, `details` for disclosure.
- **Zero JavaScript.** Static HTML, CSS inlined per page, one font file. Lighthouse 100 in all four categories, mobile and desktop, light and dark (`npm run lighthouse`).
- **Light and dark.** Every color token uses `light-dark()` and follows the system.
- **Agent-ready.** Every page also exists as Markdown (`/index.md`, `/docs/<group>/<page>.md`), linked with `rel="alternate"`, indexed in `/llms.txt`; `/llms-full.txt` has all the docs.

## Tokens

[`src/styles/tokens.scss`](src/styles/tokens.scss), emitted once by [`global.scss`](src/styles/global.scss).

| Token | Light | Dark |
| --- | --- | --- |
| `--eiz-primary` | `#3858e9` | `#8297ff` |
| `--eiz-primary-strong` | `#1d35b4` | `#a8b6ff` |
| `--eiz-primary-soft` | `#eef1fe` | `#1b2245` |
| `--eiz-accent` | `#f2994a` | `#f2994a` |
| `--eiz-accent-soft` | `#fdf0e4` | `#3a2716` |
| `--eiz-ink` | `#1e1e1e` | `#f0f0f1` |
| `--eiz-muted` | `#50575e` | `#b7bbc0` |
| `--eiz-border` | `#dcdcde` | `#2e333b` |
| `--eiz-surface` | `#f6f7f7` | `#171a21` |
| `--eiz-paper` | `#ffffff` | `#0f1115` |
| `--eiz-success` / `warning` / `error` | `#007017` / `#8a4d00` / `#cc1818` | `#4ab866` / `#f0b849` / `#f86368` |

**Type:** `--eiz-font` (Inter Variable, then the system’s), `--eiz-mono`; sizes `--eiz-text-sm` (0.875rem), `--eiz-text` (1rem), `--eiz-text-lg`, `--eiz-h3`, `--eiz-h2`, `--eiz-h1` (fluid, `clamp()`).

**Space:** `--eiz-space-1 … 24` in 4px steps (1, 2, 3, 4, 6, 8, 12, 16, 24). **Radius:** `--eiz-radius-sm` 6px, `--eiz-radius` 12px, `--eiz-radius-lg` 20px. **Shadow:** `--eiz-shadow`, `--eiz-shadow-lg`. **Widths:** `--eiz-width` 75rem, `--eiz-reading` 72ch, `--eiz-gutter` fluid 16–32px. **Targets:** `--eiz-target` 40px.

## Breakpoints

WordPress’s, from `@wordpress/base-styles`, mobile first. Components `@use '../styles/mq' as *` ([`_mq.scss`](src/styles/_mq.scss), mixins only) and write `@include break-medium { … }`.

| Name | Min width | Mixin | On this site |
| --- | --- | --- | --- |
| small | 600px | `break-small` | Two columns start (footer, docs pager) |
| medium | 782px | `break-medium` | Header links show (the Menu disclosure goes); blocks’ columns stack below this |
| large | 960px | `break-large` | Docs sidebar becomes a column; features go side by side |
| wide | 1280px | `break-wide` | “On this page” column in the docs |

## Components

All in [`src/components/`](src/components/), no client JavaScript.

| Component | Props | Notes |
| --- | --- | --- |
| `Button` | `href`, `variant` (`primary` \| `secondary` \| `light`), `icon`, `size` (`md` \| `lg`) | A link styled as a button; 40px min (48px large). One primary per view |
| `Badge` | `tone` (`neutral` \| `primary` \| `accent`) | |
| `Callout` | `type` (`note` \| `tip` \| `warning`), `title` | In Markdown docs, a blockquote gives the note style |
| `Screenshot` | `src`, `alt`, `frame` (`browser` \| `phone`), `eager`, `sizes`, `caption` | AVIF + WebP with `srcset`; lazy unless `eager` (the hero’s, the LCP) |
| `Logo` | `size`, `markOnly` | The mark inline, the wordmark as text |
| `Icon` | `name`, `size`, `label` | Decorative (`aria-hidden`) unless `label` |
| `Header`, `Footer` | — | The header’s links fold into a `details` menu below 782px |
| `DocsNav` | `slug`, `label` | The docs sidebar, from [`src/data/sidebar.ts`](src/data/sidebar.ts) |

Layouts: [`BaseLayout`](src/layouts/BaseLayout.astro) (head, SEO, social cards, JSON-LD, the font) and [`DocsLayout`](src/layouts/DocsLayout.astro) (sidebar, page, “On this page”, previous/next, edit and Markdown links).

## Screenshots

Sources in [`src/assets/screenshots/`](src/assets/screenshots/) (WebP, from a WordPress Studio site, 1440×900 at 2× and 390×844 at 3×). See [README.md](README.md) to recapture them.
