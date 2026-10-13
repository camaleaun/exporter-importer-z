/**
 * Fails (exit 1) when the screenshots and src/data/screenshots.ts disagree:
 * an image without an entry or an entry without an image; a page showing an
 * image its entry doesn't list in `usedIn`, or listing one it doesn't show;
 * a WordPress.org screenshot missing; SCREENSHOTS.md or readme.txt's
 * == Screenshots == out of date. Part of `npm run check`.
 */
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { screenshots, video } from '../src/data/screenshots.ts';
import { docFile, docText, plugin, readCaptured, readmeFile, readmeWithScreenshots, root } from './lib/screenshots-files.mjs';

const errors = [];
const src = new URL( 'src/', root );
const ids = new Set( screenshots.map( ( shot ) => shot.id ) );

const images = readdirSync( new URL( 'assets/screenshots/', src ) ).filter( ( f ) => f.endsWith( '.webp' ) ).map( ( f ) => f.replace( /\.webp$/, '' ) );
for ( const image of images ) if ( ! ids.has( image ) ) errors.push( `src/assets/screenshots/${ image }.webp has no entry in src/data/screenshots.ts` );
for ( const id of ids ) if ( ! images.includes( id ) ) errors.push( `${ id }: no src/assets/screenshots/${ id }.webp (npm run screenshots -- ${ id })` );

// Which files show which images. data/landing.ts feeds views/Home.astro.
const files = [ 'views/Home.astro', ...readdirSync( new URL( 'content/docs/', src ), { recursive: true } ).filter( ( f ) => f.endsWith( '.md' ) ).map( ( f ) => `content/docs/${ f }` ) ];
const shown = new Map();
for ( const file of files ) {
	let text = readFileSync( new URL( file, src ), 'utf8' );
	if ( file === 'views/Home.astro' ) text += readFileSync( new URL( 'data/landing.ts', src ), 'utf8' );
	const found = new Set( [ ...text.matchAll( /screenshots\/([a-z0-9-]+)\.webp/g ) ].map( ( m ) => m[ 1 ] ) );
	if ( /<DemoVideo\b/.test( text ) ) found.add( 'demo' ); // components/DemoVideo.astro plays video/demo.*
	shown.set( file, found );
}
for ( const shot of [ ...screenshots, { ...video, id: 'demo' } ] ) {
	for ( const [ file, found ] of shown ) {
		const listed = shot.usedIn.includes( file );
		if ( found.has( shot.id ) && ! listed ) errors.push( `${ file } shows ${ shot.id } but its entry doesn't list it in usedIn` );
		if ( ! found.has( shot.id ) && listed ) errors.push( `${ shot.id } lists ${ file } in usedIn but that file doesn't show it` );
	}
	for ( const file of shot.usedIn ) if ( ! shown.has( file ) ) errors.push( `${ shot.id }: usedIn ${ file } doesn't exist` );
	if ( shot.wporg && ! existsSync( new URL( `.wordpress-org/screenshot-${ shot.wporg.number }.png`, plugin ) ) ) {
		errors.push( `${ shot.id }: .wordpress-org/screenshot-${ shot.wporg.number }.png is missing (npm run screenshots -- ${ shot.id })` );
	}
}

if ( readFileSync( docFile, 'utf8' ) !== docText( readCaptured() ) ) errors.push( 'SCREENSHOTS.md is out of date: npm run screenshots -- --doc' );
if ( readFileSync( readmeFile, 'utf8' ) !== readmeWithScreenshots() ) errors.push( "readme.txt's == Screenshots == is out of date: npm run screenshots -- --doc" );

if ( errors.length ) {
	console.error( errors.map( ( e ) => `✗ ${ e }` ).join( '\n' ) );
	process.exit( 1 );
}
console.log( `Screenshots: ${ ids.size } images and the demo video match the site, SCREENSHOTS.md and readme.txt.` );
