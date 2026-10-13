/**
 * The tabs of Settings → Eizport.
 */
import { __ } from '@wordpress/i18n';

import type { PageTab } from './page-tabs';

/** Settings → Eizport. */
export const SETTINGS_TABS = (): PageTab[] => [
	{ path: '/general', label: __( 'General', 'eizport' ) },
	{ path: '/export', label: __( 'Export', 'eizport' ) },
	{ path: '/copies', label: __( 'Copies', 'eizport' ) },
];
