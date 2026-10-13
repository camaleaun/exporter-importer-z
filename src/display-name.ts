/**
 * A copy's name as people read it: without the random part
 * ("site-mW8NbAa0OEpRt5ON.zip" → "site.zip"), as Copies\keep() adds it.
 *
 * @param name The copy's file name.
 * @return The name to show.
 */
export function displayName( name: string ): string {
	return name.replace( /-[A-Za-z0-9]{16}(\.[a-z0-9]+)$/i, '$1' );
}
