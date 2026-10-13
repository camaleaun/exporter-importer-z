/**
 * Docs as plain Markdown for AI agents: a page with its title, description
 * and canonical URL, image paths made absolute; the full set for
 * /llms-full.txt.
 */
import type { CollectionEntry } from 'astro:content';
import { docsPages } from './docs';

/** One docs page as standalone Markdown. */
export const markdownOf = ( entry: CollectionEntry< 'docs' >, site: URL ): string => {
	const page = docsPages.find( ( item ) => item.slug === entry.id );
	const href = page ? new URL( page.href, site ).href : '';
	const body = ( entry.body ?? '' )
		// Screenshots: point to the page instead of a source path agents can't open.
		.replace( /!\[([^\]]*)\]\([^)]*\)/g, '*Screenshot: $1*' )
		// Relative doc links → absolute URLs.
		.replace( /\]\((\.\.\/[^)#]*)(#[^)]*)?\)/g, ( match, path: string, hash = '' ) =>
			href ? `](${ new URL( path, href ).href }${ hash })` : match
		);
	return `# ${ entry.data.title }\n\n> ${ entry.data.description }\n\nSource: ${ href }\n\n${ body.trim() }\n`;
};
