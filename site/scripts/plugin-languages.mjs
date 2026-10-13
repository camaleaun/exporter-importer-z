/**
 * Compiles the plugin's translations (languages/eizport-<wp>.po
 * in each language's repository, translations.json) into .mo files and
 * puts them in wp-content/languages/plugins/ of the local Studio sites: the
 * development site and the demo sites (npm run demo-sites). The .po stays
 * in its repository; nothing is written to this one.
 *
 *   npm run plugin-languages                               from .translations/<code>/ (npm run translations)
 *   npm run plugin-languages -- --local br=../../eizport-ptb  from a local checkout
 */
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { sites } from './lib/demo.mjs';

const site = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const plugin = resolve( site, '..' );
const devSite = resolve( plugin, '../../..' );
const config = JSON.parse( readFileSync( join( site, 'translations.json' ), 'utf8' ) );
const args = process.argv.slice( 2 );
const local = Object.fromEntries(
	args.flatMap( ( arg, index ) => ( arg === '--local' ? [ args[ index + 1 ].split( '=' ) ] : [] ) ).map( ( [ code, path ] ) => [ code, resolve( path ) ] )
);
const studio = ( path, ...wpArgs ) => execFileSync( 'studio', [ 'wp', `--path=${ path }`, ...wpArgs ], { encoding: 'utf8' } ).trim();
const targets = [ devSite, ...Object.values( sites ).map( ( demo ) => demo.path ) ].filter( ( path ) => existsSync( join( path, 'wp-config.php' ) ) );

for ( const locale of config.locales ) {
	const repo = local[ locale.code ] ?? join( site, '.translations', locale.code );
	const po = join( repo, 'languages', `eizport-${ locale.wp }.po` );
	if ( ! existsSync( po ) ) {
		console.log( `${ locale.code }: no ${ po.replace( repo + '/', '' ) }.` );
		continue;
	}
	const tmp = mkdtempSync( join( tmpdir(), 'eizport-mo-' ) );
	studio( devSite, 'i18n', 'make-mo', po, tmp );
	const mo = join( tmp, `eizport-${ locale.wp }.mo` );
	for ( const path of targets ) {
		const dir = join( path, 'wp-content/languages/plugins' );
		mkdirSync( dir, { recursive: true } );
		copyFileSync( mo, join( dir, `eizport-${ locale.wp }.mo` ) );
	}
	rmSync( tmp, { recursive: true, force: true } );
	console.log( `${ locale.code }: eizport-${ locale.wp }.mo → ${ targets.map( ( path ) => path.replace( process.env.HOME, '~' ) ).join( ', ' ) }` );
}
