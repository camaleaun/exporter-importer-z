/**
 * A table view that fits a phone: only the title column (and its
 * description line), since DataViews scrolls the others off screen.
 */
import { useViewportMatch } from '@wordpress/compose';
import type { View } from '@wordpress/dataviews';

/**
 * The view to show.
 *
 * @param view        The view as the user set it.
 * @param description Field shown under the title on a phone, if any.
 * @return The view, without columns on a phone.
 */
export default function usePhoneView( view: View, description?: string ): View {
	const isSmall = useViewportMatch( 'medium', '<' );
	if ( ! isSmall ) {
		return view;
	}
	return {
		...view,
		fields: [],
		...( description ? { descriptionField: description } : {} ),
	};
}
