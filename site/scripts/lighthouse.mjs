/**
 * Runs Lighthouse (mobile and desktop) on the site's page templates, in
 * every language built, and
 * prints the four scores and every audit below 1. Against `npm run preview`
 * by default, or a base URL: `npm run lighthouse -- https://eizport.github.io/`.
 * SCHEME=light or SCHEME=dark forces the color scheme; PAGES=",docs/" runs only
 * those paths. Reports go to
 * .lighthouse/ (ignored).
 */
import { existsSync, readFileSync } from 'node:fs';
import { mkdir, writeFile } from 'node:fs/promises';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse from 'lighthouse';
import desktopConfig from 'lighthouse/core/config/desktop-config.js';

const base = process.argv[ 2 ] ?? 'http://127.0.0.1:4321/';
const templates = [ '', 'docs/', 'docs/developers/ai-agents/', 'docs/exporting/options/', 'docs/reference/changelog/', 'donate/', 'supporters/' ];
// The same templates in each language the build has (translations.json).
const languages = JSON.parse( readFileSync( 'translations.json', 'utf8' ) ).locales.filter( ( locale ) => existsSync( `dist/${ locale.code }/` ) );
const all = [ ...templates, ...languages.flatMap( ( locale ) => templates.map( ( page ) => `${ locale.code }/${ page }` ) ) ];
// PAGES=",br/docs/" re-runs only some (comma-separated paths; empty is the home).
const pages = process.env.PAGES === undefined ? all : process.env.PAGES.split( ',' );
const categories = [ 'performance', 'accessibility', 'best-practices', 'seo' ];

await mkdir( '.lighthouse', { recursive: true } );
// SCHEME=light|dark forces the color scheme (contrast differs); else the system's.
const scheme = { dark: 0, light: 1 }[ process.env.SCHEME ?? '' ];
const chrome = await chromeLauncher.launch( {
	chromeFlags: [ '--headless=new', ...( scheme === undefined ? [] : [ `--blink-settings=preferredColorScheme=${ scheme }` ] ) ],
} );
let failed = false;

for ( const page of pages ) {
	for ( const form of [ 'mobile', 'desktop' ] ) {
		const url = new URL( page, base ).href;
		const result = await lighthouse(
			url,
			{ port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: categories },
			form === 'desktop' ? desktopConfig : undefined
		);
		const { lhr } = result;
		const scores = categories.map( ( id ) => Math.round( lhr.categories[ id ].score * 100 ) );
		console.log( `${ scores.join( ' / ' ) }  ${ form.padEnd( 7 ) } ${ url }` );
		for ( const id of categories ) {
			for ( const ref of lhr.categories[ id ].auditRefs ) {
				const audit = lhr.audits[ ref.id ];
				if ( ref.weight > 0 && audit.score !== null && audit.score < 1 ) {
					failed = true;
					console.log( `    ✗ [${ id }] ${ audit.id }: ${ audit.displayValue ?? '' } ${ audit.title }` );
				}
			}
		}
		await writeFile( `.lighthouse/${ ( page || 'home' ).replaceAll( '/', '_' ) }-${ form }.json`, result.report );
	}
}

await chrome.kill();
process.exitCode = failed ? 1 : 0;
