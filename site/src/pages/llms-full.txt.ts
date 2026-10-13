/**
 * /llms-full.txt: every docs page in sidebar order, as Markdown, in one file
 * (llmstxt.org) — plus the FAQ and changelog from readme.txt.
 */
import type { APIRoute } from 'astro';
import { docsEntries, docsPages } from '@libs/docs';
import { markdownOf } from '@libs/llms';
import { changelog, faq, site, whatsNew } from '@libs/site';

const text = ( html: string ) => html.replace( /<[^>]+>/g, '' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' );

export const GET: APIRoute = async ( { site: origin } ) => {
	const entries = await docsEntries();
	const parts = [
		`# ${ site.name } — full documentation\n\n> ${ site.description }\n\nVersion ${ site.version }. Requires WordPress ${ site.requires }+ and PHP ${ site.requiresPhp }+.`,
		`## What's new in ${ site.version }\n\n${ whatsNew().map( ( change ) => `- ${ change }` ).join( '\n' ) }`,
	];
	for ( const page of docsPages ) {
		const entry = entries.find( ( item ) => item.id === page.slug );
		if ( entry ) {
			parts.push( markdownOf( entry, origin! ).replace( /^# /, '## ' ).replace( /\n## /g, '\n### ' ).replace( /\n### (?!.*\n)/g, '\n#### ' ) );
		}
	}
	parts.push( '## FAQ\n\n' + faq().map( ( item ) => `### ${ item.question }\n\n${ item.answer.map( text ).join( '\n\n' ) }` ).join( '\n\n' ) );
	parts.push( '## Changelog\n\n' + changelog().map( ( item ) => `### ${ item.version }\n\n${ item.changes.map( ( change ) => `- ${ text( change ) }` ).join( '\n' ) }` ).join( '\n\n' ) );
	return new Response( parts.join( '\n\n---\n\n' ) + '\n', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } } );
};
