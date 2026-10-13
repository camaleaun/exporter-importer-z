/**
 * Settings → Eizport → Copies.
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';

import CopiesPage from '../../src/copies-page';
import { mark } from '../../src/mark';
import { SETTINGS_TABS } from '../../src/tabs';

function Stage() {
	return (
		<CopiesPage
			visual={ <Icon icon={ mark } /> }
			title={ __( 'Eizport', 'eizport' ) }
			subTitle={ __(
				'Exports kept on this site, to download, open in WordPress Playground or import. Anyone with a copy’s link can download it until it expires.',
				'eizport'
			) }
			tabs={ SETTINGS_TABS() }
			active="/copies"
		/>
	);
}

export const stage = Stage;
