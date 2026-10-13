/**
 * DataViews reports the selection of the rows it shows; with server paging
 * or search, the rows chosen elsewhere must stay chosen.
 *
 * @param previous Everything chosen so far.
 * @param visible  IDs of the rows on screen.
 * @param next     What DataViews reports now.
 * @return Everything chosen.
 */
export default function mergeSelection(
	previous: string[],
	visible: string[],
	next: string[]
): string[] {
	const elsewhere = previous.filter( ( id ) => ! visible.includes( id ) );
	return [ ...new Set( [ ...elsewhere, ...next ] ) ];
}
