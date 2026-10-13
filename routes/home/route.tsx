import { redirect } from '@wordpress/route';

export const route = {
	// The screen opens on the Export tab.
	beforeLoad: () => {
		throw redirect( { href: '/export' } );
	},
};
