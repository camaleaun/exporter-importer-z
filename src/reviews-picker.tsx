/**
 * The side panel for the Product reviews row: WooCommerce's approved
 * product reviews (eizport/get-reviews), searched and paged, each with its
 * checkbox. Checking or clicking one takes it to the export at once; the
 * "Selection" filter shows all, the ones chosen or the ones the products
 * chosen suggest (their reviews).
 */
import { Page } from '@wordpress/admin-ui';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { DataViews, type Field, type View } from '@wordpress/dataviews';
import { dateI18n, getSettings } from '@wordpress/date';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { useNavigate } from '@wordpress/route';

import { errorMessage, getReviews } from './abilities';
import HeaderRow from './header-row';
import mergeSelection from './merge-selection';
import usePhoneView from './use-phone-view';
import { selectionField, selectionMode, startFilters } from './selection-filter';
import {
	REVIEWS_ROW,
	chosenFor,
	setRowItems,
	suggestedFor,
	useExportState,
	useSuggestions,
} from './state';
import type { Review } from './types';
import { useResettableView } from './use-resettable-view';

export default function ReviewsPicker() {
	const navigate = useNavigate();
	const close = () => navigate( { href: '/export' } );
	const state = useExportState();
	useSuggestions();
	const { createErrorNotice } = useDispatch( noticesStore );
	const [ rows, setRows ] = useState< Review[] >( [] );
	const [ total, setTotal ] = useState( { items: 0, pages: 0 } );
	const [ isLoading, setIsLoading ] = useState( true );
	const selection = chosenFor( state, REVIEWS_ROW ).map( String );
	const suggested = suggestedFor( REVIEWS_ROW ).map( String );
	// The list asks for the reviews chosen (or suggested) when it loads, not on each check.
	const chosenNow = useRef( { selected: selection, suggested } );
	chosenNow.current = { selected: selection, suggested };
	const [ view, setView, onReset ] = useResettableView( {
		type: 'table',
		search: '',
		page: 1,
		perPage: 20,
		titleField: 'product_title',
		descriptionField: 'excerpt',
		fields: [ 'author', 'rating', 'date' ],
		layout: { density: 'compact' },
		filters: startFilters( selection, suggested ),
	} );
	const mode = selectionMode( view );

	useEffect( () => {
		setIsLoading( true );
		getReviews( {
			search: view.search || '',
			page: view.page || 1,
			per_page: view.perPage || 20,
			...( mode !== 'all'
				? { include: chosenNow.current[ mode ].map( Number ) }
				: {} ),
		} )
			.then( ( page ) => {
				setRows( page.items );
				setTotal( { items: page.total, pages: page.total_pages } );
			} )
			.catch( ( error ) =>
				createErrorNotice( errorMessage( error ), { type: 'snackbar' } )
			)
			.finally( () => setIsLoading( false ) );
	}, [ view.search, view.page, view.perPage, mode, createErrorNotice ] );

	const fields: Field< Review >[] = useMemo(
		() => [
			selectionField< Review >(),
			{
				id: 'product_title',
				label: __( 'Product', 'eizport' ),
				enableHiding: false,
				enableSorting: false,
			},
			{
				id: 'excerpt',
				label: __( 'Review', 'eizport' ),
				enableHiding: false,
				enableSorting: false,
			},
			{
				id: 'author',
				label: __( 'Author', 'eizport' ),
				enableSorting: false,
				filterBy: false,
			},
			{
				id: 'rating',
				label: __( 'Rating', 'eizport' ),
				type: 'integer',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					item.rating
						? sprintf(
								/* translators: %d: rating, 1 to 5. */
								__( '%d of 5', 'eizport' ),
								item.rating
						  )
						: '—',
			},
			{
				id: 'date',
				label: __( 'Date', 'eizport' ),
				type: 'datetime',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					dateI18n(
						getSettings().formats.datetimeAbbreviated,
						item.date,
						undefined
					),
			},
		],
		[]
	);

	const shownView = usePhoneView( view );
	const choose = ( ids: string[] ) => setRowItems( REVIEWS_ROW, ids.map( Number ) );

	return (
		<Page
			className="eizport-page eizport-picker"
			title={ __( 'Product reviews', 'eizport' ) }
			subTitle={ __(
				'Check reviews to export them with their products: they join the export at once. Without this row, products go without their reviews.',
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
			<DataViews< Review >
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
				getItemId={ ( item ) => String( item.id ) }
				defaultLayouts={ { table: {} } }
				selection={ selection }
				onChangeSelection={ ( next ) =>
					choose(
						mergeSelection(
							selection,
							rows.map( ( row ) => String( row.id ) ),
							next
						)
					)
				}
				onClickItem={ ( item ) => {
					const id = String( item.id );
					choose(
						selection.includes( id )
							? selection.filter( ( chosen ) => chosen !== id )
							: [ ...selection, id ]
					);
				} }
				actions={ [
					{
						id: 'select',
						label: __( 'Select', 'eizport' ),
						supportsBulk: true,
						callback: ( chosen ) =>
							choose( [
								...new Set( [
									...selection,
									...chosen.map( ( item ) => String( item.id ) ),
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
