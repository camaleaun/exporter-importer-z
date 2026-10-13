import { redirect } from '@wordpress/route';

export const route = {
	// Settings opens on the General tab.
	beforeLoad: () => {
		throw redirect( { href: '/general' } );
	},
};
