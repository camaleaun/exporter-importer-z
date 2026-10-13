/**
 * The docs' pages in sidebar order, with their URLs, as Bootstrap's
 * libs/content: the sidebar decides the order and the slugs; a Markdown page
 * the sidebar names must exist, or the build fails.
 */
import { getCollection, type CollectionEntry } from 'astro:content';
import { sidebar } from '../data/sidebar';
import { defaultLocale, href, pageStatus, t, type Locale } from './i18n';
import { slugify, url } from './site';

export interface DocsPage {
	group: string;
	title: string;
	slug: string;
	href: string;
	generated: boolean;
}

export const docsPages: DocsPage[] = sidebar.flatMap( ( group ) =>
	group.pages.map( ( page ) => {
		const slug = `${ slugify( group.title ) }/${ slugify( page.title ) }`;
		return {
			group: group.title,
			title: page.title,
			slug,
			href: url( `docs/${ slug }/` ),
			generated: !! page.generated,
		};
	} )
);

/** The Markdown entries, checked against the sidebar. */
export const docsEntries = async (): Promise< CollectionEntry< 'docs' >[] > => {
	const entries = await getCollection( 'docs' );
	const ids = new Set( entries.map( ( entry ) => entry.id ) );
	for ( const page of docsPages ) {
		if ( ! page.generated && ! ids.has( page.slug ) ) {
			throw new Error( `"${ page.title }" is in src/data/sidebar.ts but src/content/docs/${ page.slug }.md is missing.` );
		}
	}
	for ( const entry of entries ) {
		if ( ! docsPages.some( ( page ) => page.slug === entry.id ) ) {
			throw new Error( `src/content/docs/${ entry.id }.md is not in src/data/sidebar.ts.` );
		}
	}
	return entries;
};

/** The pages in a language: translated titles where there are, its URLs. */
export const docsPagesIn = ( locale: Locale ): DocsPage[] =>
	locale.code === defaultLocale.code
		? docsPages
		: docsPages.map( ( page ) => ( {
				...page,
				group: t( locale ).docs.groups[ page.slug.split( '/' )[ 0 ] ] ?? page.group,
				title: pageStatus( locale, page.slug ).title ?? page.title,
				href: href( locale, `docs/${ page.slug }/` ),
			} ) );

/** The pages before and after one. */
export const neighbors = ( slug: string, locale: Locale = defaultLocale ): { previous?: DocsPage; next?: DocsPage } => {
	const pages = docsPagesIn( locale );
	const index = pages.findIndex( ( page ) => page.slug === slug );
	return { previous: pages[ index - 1 ], next: pages[ index + 1 ] };
};

/** A language's translated Markdown entries (src/content/i18n/<code>/docs/), by slug. */
export const translatedEntries = async ( locale: Locale ): Promise< Map< string, CollectionEntry< 'docsI18n' > > > => {
	const prefix = `${ locale.code }/docs/`;
	const entries = await getCollection( 'docsI18n', ( entry ) => entry.id.startsWith( prefix ) );
	return new Map( entries.map( ( entry ) => [ entry.id.slice( prefix.length ), entry ] ) );
};
