/**
 * A DataViews view whose appearance can be reset, like the Site Editor's
 * lists: the view settings (cog) show "Reset view" and a dot on the cog once
 * the layout, fields, sort, page size or density differ from the start.
 * Filters and search aren't part of it: they are what the list is showing.
 */
import { useRef, useState } from '@wordpress/element';
import type { View } from '@wordpress/dataviews';
import type { Dispatch, SetStateAction } from 'react';

const APPEARANCE = [
	'type',
	'fields',
	'sort',
	'perPage',
	'layout',
	'titleField',
	'mediaField',
	'descriptionField',
	'showTitle',
	'showMedia',
	'showDescription',
	'showLevels',
	'groupByField',
] as const;

type Appearance = ( typeof APPEARANCE )[ number ];

const appearanceOf = ( view: View ): string =>
	JSON.stringify(
		APPEARANCE.map(
			( key ) => ( view as Partial< Record< Appearance, unknown > > )[ key ] ?? null
		)
	);

/**
 * @param initial The view the list starts with.
 * @return The view, its setter, and DataViews' onReset: a function once the
 *         appearance changed, false while it is the starting one.
 */
export function useResettableView(
	initial: View
): [ View, Dispatch< SetStateAction< View > >, ( () => void ) | false ] {
	const [ view, setView ] = useState< View >( initial );
	const start = useRef( initial );
	const modified = appearanceOf( view ) !== appearanceOf( start.current );
	const onReset = modified
		? () =>
				setView( ( current ) => {
					const back = Object.fromEntries(
						APPEARANCE.map( ( key ) => [
							key,
							( start.current as Partial< Record< Appearance, unknown > > )[ key ],
						] )
					);
					return { ...current, ...back, page: 1 } as View;
				} )
		: false;

	return [ view, setView, onReset ];
}
