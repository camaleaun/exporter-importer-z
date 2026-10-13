/**
 * The side panel for the Options row: the site's options one by one
 * (eizport/get-options), searched, paged and filtered by group, each with
 * its checkbox. Checking or clicking an option takes it to the export at
 * once; the "Selection" filter shows all or only the ones chosen. Groups
 * and "all options" chosen before (saved defaults, the classic screen)
 * stay until the row is left out.
 */
import { Page } from '@wordpress/admin-ui';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { DataViews, type Field, type View } from '@wordpress/dataviews';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { useNavigate } from '@wordpress/route';
import { Badge } from '@wordpress/ui';

import { errorMessage, getExportOptions, getOptions } from './abilities';
import HeaderRow from './header-row';
import mergeSelection from './merge-selection';
import usePhoneView from './use-phone-view';
import {
	OPTIONS_ROW,
	chosenFor,
	groupName,
	setRowItems,
	suggestedFor,
	useExportState,
	useSuggestions,
} from './state';
import { selectionField, selectionMode, startFilters } from './selection-filter';
import type { ExportOptions, OptionRow } from './types';
import { useResettableView } from './use-resettable-view';

/** The group suggested when nothing is chosen yet. */
const SUGGESTED_GROUP = 'basics';

/**
 * A size in bytes, in words.
 *
 * @param bytes Size.
 * @return Size like "1.2 KB".
 */
function size( bytes: number ): string {
	return bytes < 1024 ? `${ bytes } B` : `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
}

export default function OptionsPicker() {
	const navigate = useNavigate();
	const close = () => navigate( { href: '/export' } );
	const state = useExportState();
	const { settings } = state;
	const { createErrorNotice } = useDispatch( noticesStore );
	const [ rows, setRows ] = useState< OptionRow[] >( [] );
	const [ total, setTotal ] = useState( { items: 0, pages: 0 } );
	const [ isLoading, setIsLoading ] = useState( true );
	useSuggestions();
	const selection = chosenFor( state, OPTIONS_ROW ).map( String );
	const suggested = suggestedFor( OPTIONS_ROW ).map( String );
	// The list asks for the options chosen (or suggested) when it loads, not on each check.
	const chosenNow = useRef( { selected: selection, suggested } );
	chosenNow.current = { selected: selection, suggested };
	const [ groups, setGroups ] = useState< ExportOptions[ 'option_groups' ] >(
		[]
	);
	const nothingChosen =
		! settings.option_all &&
		! selection.length &&
		! settings.option_groups.length;
	// Options chosen (the suggested ones too) or suggested: the panel opens on them only.
	let startFilter: View[ 'filters' ] = startFilters( selection, suggested );
	if ( ! startFilter.length && ( nothingChosen || settings.option_groups.length ) ) {
		startFilter = [
			{
				field: 'group',
				operator: 'is',
				value: nothingChosen
					? SUGGESTED_GROUP
					: settings.option_groups[ 0 ],
			},
		];
	}
	const [ view, setView, onReset ] = useResettableView( {
		type: 'table',
		search: '',
		page: 1,
		perPage: 50,
		titleField: 'name',
		// Size, notes and value stay in the view settings, hidden to start with.
		fields: [],
		layout: { density: 'compact' },
		filters: startFilter,
	} );
	const mode = selectionMode( view );
	const group = String(
		view.filters?.find( ( filter ) => filter.field === 'group' )?.value ||
			''
	);

	useEffect( () => {
		getExportOptions()
			.then( ( options ) => setGroups( options.option_groups ) )
			.catch( () => undefined );
	}, [] );

	useEffect( () => {
		setIsLoading( true );
		getOptions( {
			search: view.search || '',
			page: view.page || 1,
			per_page: view.perPage || 50,
			...( group ? { group } : {} ),
			...( mode !== 'all' ? { names: chosenNow.current[ mode ] } : {} ),
		} )
			.then( ( page ) => {
				setRows( page.items );
				setTotal( { items: page.total, pages: page.total_pages } );
			} )
			.catch( ( error ) =>
				createErrorNotice( errorMessage( error ), { type: 'snackbar' } )
			)
			.finally( () => setIsLoading( false ) );
	}, [
		view.search,
		view.page,
		view.perPage,
		group,
		mode,
		createErrorNotice,
	] );

	const fields: Field< OptionRow >[] = useMemo(
		() => [
			selectionField< OptionRow >(),
			{
				id: 'name',
				label: __( 'Option', 'eizport' ),
				enableHiding: false,
				enableSorting: false,
			},
			{
				id: 'group',
				label: __( 'Group', 'eizport' ),
				enableHiding: false,
				enableSorting: false,
				elements: groups.map( ( item ) => ( {
					value: item.group,
					label: groupName( item.label ),
					description: item.label,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				render: () => null,
			},
			{
				id: 'size',
				label: __( 'Size', 'eizport' ),
				type: 'integer',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) => size( item.size ),
			},
			{
				id: 'value',
				label: __( 'Value', 'eizport' ),
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) => (
					<span className="eizport-option-value" title={ item.value }>
						{ item.value.length > 80
							? `${ item.value.slice( 0, 79 ) }…`
							: item.value }
					</span>
				),
			},
			{
				id: 'flags',
				label: __( 'Notes', 'eizport' ),
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					item.post_id ? (
						<Badge intent="informational">
							{ __( 'IDs mapped on import', 'eizport' ) }
						</Badge>
					) : null,
			},
		],
		[ groups ]
	);

	const shownView = usePhoneView( view );

	return (
		<Page
			className="eizport-page eizport-picker"
			title={ __( 'Options', 'eizport' ) }
			subTitle={ __(
				'Check the options to set on the site that imports the file: they join the export at once. Site URLs, the admin email, keys, active plugins, roles and transients are never listed.',
				'eizport'
			) }
			actions={
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					size="compact"
					onClick={ close }
				>
					{ __( 'Close', 'eizport' ) }
				</Button>
			}
			hasPadding={ false }
		>
			<DataViews< OptionRow >
				data={ rows }
				fields={ fields }
				view={ shownView }
				onChangeView={ setView }
				onReset={ onReset }
				paginationInfo={ {
					totalItems: total.items,
					totalPages: total.pages,
				} }
				isLoading={ isLoading }
				getItemId={ ( item ) => item.name }
				defaultLayouts={ { table: {} } }
				selection={ selection }
				onChangeSelection={ ( next ) =>
					setRowItems(
						OPTIONS_ROW,
						mergeSelection(
							selection,
							rows.map( ( row ) => row.name ),
							next
						)
					)
				}
				onClickItem={ ( item ) =>
					setRowItems(
						OPTIONS_ROW,
						selection.includes( item.name )
							? selection.filter( ( name ) => name !== item.name )
							: [ ...selection, item.name ]
					)
				}
				actions={ [
					{
						id: 'select',
						label: __( 'Select', 'eizport' ),
						supportsBulk: true,
						callback: ( chosen ) =>
							setRowItems( OPTIONS_ROW, [
								...new Set( [
									...selection,
									...chosen.map( ( item ) => item.name ),
								] ),
							] ),
					},
				] }
			>
				<HeaderRow />
				<DataViews.Layout />
				<DataViews.Footer />
			</DataViews>
		</Page>
	);
}
