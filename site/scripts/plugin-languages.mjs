/**
 * Installs the plugin's official translations (the translate.wordpress.org
 * language pack of each language in translations.json: .po, .mo, .l10n.php and
 * the screens' .json) in wp-content/languages/plugins/ of the local Studio
 * sites: installed on the development site with WP-CLI, then copied to the
 * demo sites (npm run demo-sites).
 * Nothing is written to this repository.
 *
 *   npm run plugin-languages
 */
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { sites } from './lib/demo.mjs';

const site = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const plugin = resolve( site, '..' );
const devSite = resolve( plugin, '../../..' );
const config = JSON.parse( readFileSync( join( site, 'translations.json' ), 'utf8' ) );
const studio = ( path, ...wpArgs ) => execFileSync( 'studio', [ 'wp', `--path=${ path }`, ...wpArgs ], { encoding: 'utf8' } ).trim();
const targets = [ devSite, ...Object.values( sites ).map( ( demo ) => demo.path ) ].filter( ( path ) => existsSync( join( path, 'wp-config.php' ) ) );

const packFiles = ( dir, wp ) => ( existsSync( dir ) ? readdirSync( dir ).filter( ( file ) => file.startsWith( `eizport-${ wp }` ) ) : [] );

for ( const locale of config.locales ) {
	// The development site runs the plugin's current version, the one translate.wordpress.org has a pack for.
	const source = join( devSite, 'wp-content/languages/plugins' );
	// Files left by an older install (or compiled by hand) would make WP-CLI skip the pack.
	for ( const file of packFiles( source, locale.wp ) ) {
		rmSync( join( source, file ) );
	}
	studio( devSite, 'language', 'plugin', 'install', 'eizport', locale.wp );
	const files = packFiles( source, locale.wp );
	if ( ! files.includes( `eizport-${ locale.wp }.mo` ) ) {
		throw new Error( `${ locale.code }: translate.wordpress.org has no ${ locale.wp } language pack for this version of Eizport.` );
	}
	// The demo sites get the same files, whatever version their copy of the plugin is.
	for ( const path of targets.filter( ( path ) => path !== devSite ) ) {
		const dir = join( path, 'wp-content/languages/plugins' );
		mkdirSync( dir, { recursive: true } );
		for ( const file of packFiles( dir, locale.wp ) ) {
			rmSync( join( dir, file ) );
		}
		for ( const file of files ) {
			copyFileSync( join( source, file ), join( dir, file ) );
		}
	}
	console.log( `${ locale.code }: ${ files.length } files of the eizport ${ locale.wp } language pack → ${ targets.map( ( path ) => path.replace( process.env.HOME, '~' ) ).join( ', ' ) }` );
}
