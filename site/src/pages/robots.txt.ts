import type { APIRoute } from 'astro';
import { url } from '@libs/site';

export const GET: APIRoute = ( { site } ) =>
	new Response( `# Everyone, AI agents included, is welcome. Docs as Markdown: ${ url( 'llms.txt' ) }\nUser-agent: *\nAllow: /\n\nSitemap: ${ new URL( url( 'sitemap-index.xml' ), site ) }\n`, {
		headers: { 'Content-Type': 'text/plain; charset=utf-8' },
	} );
