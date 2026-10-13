/**
 * The files made from src/data/screenshots.ts: SCREENSHOTS.md and
 * readme.txt's == Screenshots == section. Written by scripts/screenshots.mjs,
 * compared by scripts/check-screenshots.mjs.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { screenshots, video } from '../../src/data/screenshots.ts';

export const root = new URL( '../../', import.meta.url );
export const plugin = new URL( '../', root );
export const capturedFile = new URL( 'src/data/screenshots-captured.json', root );
export const docFile = new URL( 'SCREENSHOTS.md', root );
export const readmeFile = new URL( 'readme.txt', plugin );

export const readCaptured = () => JSON.parse( readFileSync( capturedFile, 'utf8' ) );

const where = ( file ) =>
	file.replace( /^views\/Home\.astro$/, 'Home (`/`)' ).replace( /^content\/docs\/(.+)\.md$/, 'Docs `/docs/$1/`' );
const code = ( list ) => list.map( ( s ) => `\`${ s }\`` ).join( ' ' );

export function docText( captured ) {
	const rows = screenshots.map( ( shot ) =>
		[
			'',
			`\`${ shot.id }\``,
			shot.feature,
			`${ shot.screen } — ${ shot.state }`,
			shot.viewport,
			shot.usedIn.map( where ).join( '<br>' ) + ( shot.wporg ? `<br>WordPress.org #${ shot.wporg.number }` : '' ),
			code( shot.sources ),
			captured[ shot.id ]?.date ?? '—',
			'',
		].join( ' | ' ).trim()
	);
	return `# Screenshots

Generated from \`src/data/screenshots.ts\` by \`npm run screenshots\` — edit that file, not this one.

Which feature and screen each image shows, where it appears, and the plugin files that, when changed, mean it must be captured again. After changing a feature:

\`\`\`bash
npm run demo-sites                 # refresh the demo Studio sites with the current plugin
npm run screenshots -- --changed   # recapture what the change touches (and says if the video needs it)
npm run video                      # when the video's scenes changed (see video/STORYBOARD.md)
\`\`\`

Images: \`src/assets/screenshots/<id>.webp\` (the site makes AVIF/WebP sizes). WordPress.org: \`.wordpress-org/screenshot-N.png\` and readme.txt's \`== Screenshots ==\`. \`npm run check\` fails when this list and the site disagree.

| Image | Feature | Screen and state | Viewport | Shown in | Depends on | Captured |
| --- | --- | --- | --- | --- | --- | --- |
${ rows.join( '\n' ) }
| \`demo\` video | The whole flow: export, import, Playground, AI agents | See \`video/STORYBOARD.md\` | 1920×1080 | ${ video.usedIn.map( where ).join( '<br>' ) }<br>Apoia.se (YouTube) | ${ code( video.sources ) } | ${ captured.demo?.date ?? '—' } |
`;
}

export function readmeSection() {
	const shots = screenshots.filter( ( shot ) => shot.wporg ).sort( ( a, b ) => a.wporg.number - b.wporg.number );
	return `== Screenshots ==\n\n${ shots.map( ( shot ) => `${ shot.wporg.number }. ${ shot.wporg.caption }` ).join( '\n' ) }\n\n`;
}

/** readme.txt with its == Screenshots == section as the manifest says (before Upgrade Notice). */
export function readmeWithScreenshots() {
	const readme = readFileSync( readmeFile, 'utf8' );
	const without = readme.replace( /== Screenshots ==\n[\s\S]*?(?=== )/, '' );
	return without.replace( /(?=== Upgrade Notice ==)/, readmeSection() );
}

export function writeFiles( captured ) {
	writeFileSync( docFile, docText( captured ) );
	writeFileSync( readmeFile, readmeWithScreenshots() );
}
