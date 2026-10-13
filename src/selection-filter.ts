/**
 * The side panels' "Selection" filter: all items, the ones chosen, or the
 * ones the items chosen elsewhere suggest. A panel opens on Selected when
 * its row has items chosen, else on Suggested when there are suggestions.
 */
import type { Field, Filter, View } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';

export type SelectionMode = 'all' | 'selected' | 'suggested';

/**
 * The filter's field, for any panel's items.
 *
 * @return Field.
 */
export function selectionField< Item >(): Field< Item > {
	return {
		id: 'selection',
		label: __( 'Selection', 'eizport' ),
		enableHiding: false,
		enableSorting: false,
		elements: [
			{ value: 'all', label: __( 'All', 'eizport' ) },
			{ value: 'selected', label: __( 'Selected', 'eizport' ) },
			{
				value: 'suggested',
				label: __( 'Suggested', 'eizport' ),
			},
		],
		filterBy: { operators: [ 'is' ] },
		render: () => null,
	};
}

/**
 * The filter a panel opens with, if any.
 *
 * @param chosen    Items chosen.
 * @param suggested Items suggested.
 * @return Filters.
 */
export function startFilters(
	chosen: unknown[],
	suggested: unknown[]
): Filter[] {
	if ( chosen.length ) {
		return [ { field: 'selection', operator: 'is', value: 'selected' } ];
	}
	if ( suggested.length ) {
		return [ { field: 'selection', operator: 'is', value: 'suggested' } ];
	}
	return [];
}

/**
 * What the view's Selection filter shows.
 *
 * @param view View.
 * @return Mode.
 */
export function selectionMode( view: View ): SelectionMode {
	const value = view.filters?.find(
		( filter ) => filter.field === 'selection'
	)?.value;
	return value === 'selected' || value === 'suggested' ? value : 'all';
}
