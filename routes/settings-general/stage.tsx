/**
 * Eizport's settings → General: the Export screen (replacing
 * Tools → Export or not, classic or new, saved defaults) and how long
 * copies are kept.
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import type { Form } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { Stack } from '@wordpress/ui';

import { clearExportDefaults, errorMessage } from '../../src/abilities';
import SettingsForm from '../../src/settings-form';

const FORM: Form = {
	layout: { type: 'card' },
	fields: [
		{
			id: 'screen',
			label: __( 'Export screen', 'eizport' ),
			description: __(
				'"Save as default" on the Export screens keeps the choices, to start with them next time.',
				'eizport'
			),
			layout: {
				type: 'card',
				isOpened: false,
				// What is chosen, while closed.
				summary: [ { id: 'defaults_scope', visibility: 'when-collapsed' } ],
			},
			children: [ 'replace_export', 'classic_ui', 'defaults_scope' ],
		},
		{
			id: 'import-screen',
			label: __( 'Import screen', 'eizport' ),
			layout: {
				type: 'card',
				isOpened: false,
			},
			children: [ 'import_direct', 'style_import' ],
		},
		{
			id: 'copies',
			label: __( 'Copies', 'eizport' ),
			description: __(
				'Exports kept on this site, to open by link or import.',
				'eizport'
			),
			layout: {
				type: 'card',
				isOpened: false,
				// What is chosen, while closed.
				summary: [ { id: 'copy_lifetime', visibility: 'when-collapsed' } ],
			},
			children: [ 'copy_lifetime' ],
		},
	],
};

function Stage() {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	return (
		<SettingsForm
			active="/general"
			subTitle={ __(
				'Which Export screen opens, saved defaults, and how long exports are kept.',
				'eizport'
			) }
			form={ FORM }
			footer={
				<Stack justify="flex-end">
					<Button
						__next40pxDefaultSize
						variant="secondary"
						isDestructive
						onClick={ () =>
							clearExportDefaults()
								.then( () =>
									createSuccessNotice(
										__(
											'Saved defaults cleared.',
											'eizport'
										),
										{ type: 'snackbar' }
									)
								)
								.catch( ( error ) =>
									createErrorNotice( errorMessage( error ), {
										type: 'snackbar',
									} )
								)
						}
					>
						{ __( 'Clear saved defaults', 'eizport' ) }
					</Button>
				</Stack>
			}
		/>
	);
}

export const stage = Stage;
