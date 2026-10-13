/**
 * Each docs page as Markdown, at /docs/<group>/<page>.md: for AI agents and
 * anyone who wants the source (linked with rel="alternate" from the page).
 */
import type { APIRoute, GetStaticPaths } from 'astro';
import type { CollectionEntry } from 'astro:content';
import { docsEntries } from '@libs/docs';
import { markdownOf } from '@libs/llms';

export const getStaticPaths: GetStaticPaths = async () =>
	( await docsEntries() ).map( ( entry ) => ( { params: { slug: entry.id }, props: { entry } } ) );

export const GET: APIRoute = ( { props, site } ) =>
	new Response( markdownOf( props.entry as CollectionEntry< 'docs' >, site! ), {
		headers: { 'Content-Type': 'text/markdown; charset=utf-8' },
	} );
