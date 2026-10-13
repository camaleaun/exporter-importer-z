export const route = {
	// The side panel picks a type's items: /export?type=page.
	inspector: ( { search }: { search: { type?: string } } ) => !! search?.type,
};
