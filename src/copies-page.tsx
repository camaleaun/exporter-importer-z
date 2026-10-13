/**
 * The copies kept on this site (eizport/get-copies), with their links,
 * imported here (eizport/import) or deleted (eizport/delete-copies): shared
 * by the Export screen's Copies tab and Settings → Eizport.
 */
import { Page } from '@wordpress/admin-ui';
import {
	Button,
	Modal,
	__experimentalHStack as HStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import {
	DataViews,
	filterSortAndPaginate,
	type Action,
	type Field,
	type View,
} from '@wordpress/dataviews';
import { dateI18n, getSettings } from '@wordpress/date';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { trash } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';
import type { ReactNode } from 'react';
import { Badge, EmptyState } from '@wordpress/ui';

import {
	deleteAllCopies,
	deleteCopies,
	errorMessage,
	getCopies,
	importCopy,
} from './abilities';
import { displayName } from './display-name';
import HeaderRow from './header-row';
import usePhoneView from './use-phone-view';
import type { PageTab } from './page-tabs';
import type { Copy } from './types';
import { useResettableView } from './use-resettable-view';

interface CopiesPageProps {
	visual?: ReactNode;
	title: string;
	subTitle?: string;
	actions?: ReactNode;
	/** The page's tabs; the one ending in "/copies" gets the count. */
	tabs: PageTab[];
	active: string;
}

const DEFAULT_VIEW: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 20,
	sort: { field: 'created', direction: 'desc' },
	titleField: 'name',
	fields: [ 'format', 'size', 'created', 'expires' ],
	layout: {},
};

/**
 * A size in bytes, in words.
 *
 * @param bytes Size.
 * @return Size like "8.2 MB".
 */
function size( bytes: number ): string {
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let value = bytes;
	let unit = 0;
	while ( value >= 1024 && unit < units.length - 1 ) {
		value /= 1024;
		unit++;
	}
	return `${ value.toFixed( unit ? 1 : 0 ) } ${ units[ unit ] }`;
}

export default function CopiesPage( {
	visual,
	title,
	subTitle,
	actions: pageActions,
	tabs,
	active,
}: CopiesPageProps ) {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const [ copies, setCopies ] = useState< Copy[] >( [] );
	const [ confirmAll, setConfirmAll ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ view, setView, onReset ] = useResettableView( DEFAULT_VIEW );
	// On a phone, when it expires goes under the name.
	const shownView = usePhoneView( view, 'expires' );

	const load = useCallback( () => {
		setIsLoading( true );
		getCopies()
			.then( setCopies )
			.catch( ( error ) =>
				createErrorNotice( errorMessage( error ), { type: 'snackbar' } )
			)
			.finally( () => setIsLoading( false ) );
	}, [ createErrorNotice ] );

	useEffect( load, [ load ] );

	const fields: Field< Copy >[] = useMemo(
		() => [
			{
				id: 'name',
				label: __( 'File', 'eizport' ),
				enableHiding: false,
				enableGlobalSearch: true,
				// Without the random part of the file name; hovering shows it whole.
				render: ( { item } ) => (
					<span title={ item.name }>{ displayName( item.name ) }</span>
				),
			},
			{
				id: 'format',
				label: __( 'Format', 'eizport' ),
				elements: [
					{
						value: 'wxrz',
						label: __( 'WXR bundle .wxrz', 'eizport' ),
					},
					{
						value: 'playground',
						label: __( 'Playground .zip', 'eizport' ),
					},
					{
						value: 'zip',
						label: __( 'WXR bundle .zip', 'eizport' ),
					},
					{
						value: 'wxr',
						label: __( 'WXR .xml', 'eizport' ),
					},
					{
						value: 'plugin',
						label: __( 'Plugin .zip', 'eizport' ),
					},
				],
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) => (
					<Badge
						intent={
							item.format === 'playground'
								? 'informational'
								: 'draft'
						}
					>
						{ {
							playground: __( 'Playground .zip', 'eizport' ),
							wxrz: __( 'WXR bundle .wxrz', 'eizport' ),
							zip: __( 'WXR bundle .zip', 'eizport' ),
							wxr: __( 'WXR .xml', 'eizport' ),
							plugin: __( 'Plugin .zip', 'eizport' ),
						}[ item.format ] }
					</Badge>
				),
			},
			{
				id: 'size',
				label: __( 'Size', 'eizport' ),
				type: 'integer',
				filterBy: false,
				render: ( { item } ) => size( item.size ),
			},
			{
				id: 'created',
				label: __( 'Created', 'eizport' ),
				type: 'datetime',
				filterBy: false,
				render: ( { item } ) =>
					dateI18n(
						getSettings().formats.datetimeAbbreviated,
						item.created,
						undefined
					),
			},
			{
				id: 'expires',
				label: __( 'Expires', 'eizport' ),
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					item.expires
						? dateI18n(
								getSettings().formats.datetimeAbbreviated,
								item.expires,
								undefined
						  )
						: __( 'Never', 'eizport' ),
			},
		],
		[]
	);

	const actions: Action< Copy >[] = useMemo(
		() => [
			{
				id: 'open',
				label: __( 'Open in WordPress Playground', 'eizport' ),
				isPrimary: true,
				isEligible: ( item ) => item.format === 'playground',
				callback: ( [ item ] ) =>
					window.open( item.playground_url, '_blank', 'noopener' ),
			},
			{
				id: 'download',
				label: __( 'Download', 'eizport' ),
				callback: ( [ item ] ) => {
					window.location.href = item.download_url;
				},
			},
			{
				id: 'copy-link',
				label: __( 'Copy link', 'eizport' ),
				callback: ( [ item ] ) => {
					const link = item.playground_url || item.download_url;
					window.navigator.clipboard.writeText( link ).then( () =>
						createSuccessNotice(
							__( 'Link copied.', 'eizport' ),
							{ type: 'snackbar' }
						)
					);
				},
			},
			{
				id: 'import',
				label: __( 'Import into this site', 'eizport' ),
				// A plugin imports itself, once installed on the other site.
				isEligible: ( item ) => item.format !== 'plugin',
				modalHeader: __( 'Import this copy?', 'eizport' ),
				RenderModal: ( { items, closeModal } ) => {
					const [ busy, setBusy ] = useState( false );
					return (
						<>
							<Text>
								{ __(
									'Its posts, media and settings are added to this site with the WordPress Importer. Items that already exist are skipped.',
									'eizport'
								) }
							</Text>
							<HStack justify="right" style={ { marginTop: 24 } }>
								<Button
									__next40pxDefaultSize
									variant="tertiary"
									onClick={ closeModal }
									disabled={ busy }
								>
									{ __( 'Cancel', 'eizport' ) }
								</Button>
								<Button
									__next40pxDefaultSize
									variant="primary"
									isBusy={ busy }
									disabled={ busy }
									accessibleWhenDisabled
									onClick={ async () => {
										setBusy( true );
										try {
											const result = await importCopy(
												items[ 0 ].name
											);
											createSuccessNotice(
												sprintf(
													/* translators: 1: number of posts, 2: number of settings. */
													__(
														'Imported %1$d items and %2$d settings.',
														'eizport'
													),
													result.posts,
													result.options.length
												),
												{ type: 'snackbar' }
											);
										} catch ( error ) {
											createErrorNotice(
												errorMessage( error ),
												{ type: 'snackbar' }
											);
										}
										closeModal?.();
									} }
								>
									{ __( 'Import', 'eizport' ) }
								</Button>
							</HStack>
						</>
					);
				},
			},
			{
				id: 'delete',
				label: __( 'Delete permanently', 'eizport' ),
				// On phones, the bulk actions bar only shows actions with an icon.
				icon: trash,
				isDestructive: true,
				supportsBulk: true,
				modalHeader: __( 'Delete permanently?', 'eizport' ),
				RenderModal: ( { items, closeModal } ) => (
					<>
						<Text>
							{ sprintf(
								/* translators: %d: number of copies. */
								_n(
									'%d copy is deleted, and its link stops working.',
									'%d copies are deleted, and their links stop working.',
									items.length,
									'eizport'
								),
								items.length
							) }
						</Text>
						<HStack justify="right" style={ { marginTop: 24 } }>
							<Button
								__next40pxDefaultSize
								variant="tertiary"
								onClick={ closeModal }
							>
								{ __( 'Cancel', 'eizport' ) }
							</Button>
							<Button
								__next40pxDefaultSize
								variant="primary"
								isDestructive
								onClick={ async () => {
									try {
										const { deleted } = await deleteCopies(
											items.map( ( item ) => item.name )
										);
										createSuccessNotice(
											sprintf(
												/* translators: %d: number of copies. */
												_n(
													'%d copy deleted.',
													'%d copies deleted.',
													deleted,
													'eizport'
												),
												deleted
											),
											{ type: 'snackbar' }
										);
									} catch ( error ) {
										createErrorNotice( errorMessage( error ), {
											type: 'snackbar',
										} );
									}
									closeModal?.();
									load();
								} }
							>
								{ __( 'Delete', 'eizport' ) }
							</Button>
						</HStack>
					</>
				),
			},
		],
		[ createSuccessNotice, createErrorNotice, load ]
	);

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( copies, view, fields ),
		[ copies, view, fields ]
	);

	return (
		<Page
			className="eizport-page"
			visual={ visual }
			title={ title }
			subTitle={ subTitle }
			actions={
				<>
					{ pageActions }
					<Button
						__next40pxDefaultSize
						variant="secondary"
						size="compact"
						isDestructive
						disabled={ ! copies.length }
						accessibleWhenDisabled
						onClick={ () => setConfirmAll( true ) }
					>
						{ __( 'Delete all', 'eizport' ) }
					</Button>
				</>
			}
			hasPadding={ false }
		>
			{ confirmAll && (
				<Modal
					title={ __( 'Delete all copies?', 'eizport' ) }
					size="small"
					onRequestClose={ () => setConfirmAll( false ) }
				>
					<Text>
						{ sprintf(
							/* translators: %d: number of copies. */
							_n(
								'%d copy is deleted, and its link stops working.',
								'%d copies are deleted, and their links stop working.',
								copies.length,
								'eizport'
							),
							copies.length
						) }
					</Text>
					<HStack justify="right" style={ { marginTop: 24 } }>
						<Button
							__next40pxDefaultSize
							variant="tertiary"
							onClick={ () => setConfirmAll( false ) }
						>
							{ __( 'Cancel', 'eizport' ) }
						</Button>
						<Button
							__next40pxDefaultSize
							variant="primary"
							isDestructive
							onClick={ async () => {
								try {
									const { deleted } = await deleteAllCopies();
									createSuccessNotice(
										sprintf(
											/* translators: %d: number of copies. */
											_n(
												'%d copy deleted.',
												'%d copies deleted.',
												deleted,
												'eizport'
											),
											deleted
										),
										{ type: 'snackbar' }
									);
								} catch ( error ) {
									createErrorNotice( errorMessage( error ), {
										type: 'snackbar',
									} );
								}
								setConfirmAll( false );
								load();
							} }
						>
							{ __( 'Delete all', 'eizport' ) }
						</Button>
					</HStack>
				</Modal>
			) }
			<DataViews< Copy >
				data={ data }
				fields={ fields }
				view={ shownView }
				onChangeView={ setView }
				onReset={ onReset }
				actions={ actions }
				paginationInfo={ paginationInfo }
				isLoading={ isLoading }
				getItemId={ ( item ) => item.name }
				defaultLayouts={ { table: {}, list: {} } }
				empty={
					<EmptyState.Root>
						<EmptyState.Title>
							{ __( 'No copies yet', 'eizport' ) }
						</EmptyState.Title>
						<EmptyState.Description>
							{ __(
								'Exports made here are kept as copies, to download, open in Playground or import.',
								'eizport'
							) }
						</EmptyState.Description>
					</EmptyState.Root>
				}
			>
				<HeaderRow
					tabs={ tabs.map( ( tab ) =>
						tab.path.endsWith( '/copies' )
							? { ...tab, count: copies.length }
							: tab
					) }
					active={ active }
				/>
				<DataViews.Layout />
				<DataViews.Footer />
			</DataViews>
		</Page>
	);
}

