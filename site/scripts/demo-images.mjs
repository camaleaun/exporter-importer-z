/**
 * Redraws the demo source's images with the plugin's name on them
 * (demo-sites.sh): the development site's copies still carry an older one.
 * Each file passed is replaced in place, with the look its name asks for;
 * other names are left alone.
 *
 *   node scripts/demo-images.mjs <file.jpg>…
 */
import { basename } from 'node:path';
import sharp from 'sharp';

const WIDTH = 1200;
const HEIGHT = 630;
const DARK = '#1e3a78';
const LIGHT = '#5594dc';

// Where the circle sits and which way the gradient runs, so the images stay
// told apart on the screens.
const looks = {
	'demo-image.jpg': { circle: 'end', from: DARK, to: LIGHT },
	'home-conteudo.jpg': { circle: 'start', from: DARK, to: LIGHT },
	'home-capa.jpg': { circle: 'start', from: LIGHT, to: DARK },
};

const svg = ( { circle, from, to } ) => {
	const cx = circle === 'start' ? 300 : WIDTH - 300;
	const text = circle === 'start' ? { x: WIDTH - 80, anchor: 'end' } : { x: 80, anchor: 'start' };
	return `<svg xmlns="http://www.w3.org/2000/svg" width="${ WIDTH }" height="${ HEIGHT }">
	<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${ from }"/><stop offset="1" stop-color="${ to }"/></linearGradient></defs>
	<rect width="100%" height="100%" fill="url(#g)"/>
	<circle cx="${ cx }" cy="${ HEIGHT / 2 }" r="180" fill="#fff" fill-opacity="0.3"/>
	<text x="${ text.x }" y="${ HEIGHT / 2 + 8 }" text-anchor="${ text.anchor }" font-family="Menlo, monospace" font-size="24" fill="#fff">Eizport – demo image</text>
</svg>`;
};

for ( const file of process.argv.slice( 2 ) ) {
	const look = looks[ basename( file ) ];
	if ( look ) {
		await sharp( Buffer.from( svg( look ) ) ).jpeg( { quality: 85 } ).toFile( file );
	}
}
