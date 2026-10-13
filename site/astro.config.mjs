// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import translations from './translations.json' with { type: 'json' };

// Every language the site may have (translations.json): the sitemap pairs
// each page with its versions in the others.
const sitemapLocales = Object.fromEntries( [ translations.default, ...translations.locales ].map( ( locale ) => [ locale.code, locale.lang ] ) );

// https://astro.build/config
export default defineConfig( {
	site: 'https://eizport.github.io',
	trailingSlash: 'always',
	build: {
		format: 'directory',
		// Every page's CSS goes inline: no render-blocking stylesheet.
		inlineStylesheets: 'always',
	},
	integrations: [ sitemap( { i18n: { defaultLocale: translations.default.code, locales: sitemapLocales } } ) ],
	image: {
		// Responsive images everywhere, Markdown included: srcset and sizes.
		layout: 'constrained',
		responsiveStyles: true,
	},
	markdown: {
		shikiConfig: {
			themes: { light: 'github-light-high-contrast', dark: 'github-dark-high-contrast' },
		},
	},
	devToolbar: { enabled: false },
	vite: {
		css: {
			preprocessorOptions: {
				scss: { loadPaths: [ 'node_modules' ] },
			},
		},
	},
} );
