/**
 * The row under the page header, as in Jetpack Forms: the page's tabs on the
 * left, the DataViews search, filters and view settings on the right, then
 * the filter chips. Rendered inside <DataViews>.
 */
import { DataViews } from '@wordpress/dataviews';
import { Stack } from '@wordpress/ui';

import PageTabs, { type PageTab } from './page-tabs';
import './style.scss';

interface HeaderRowProps {
	tabs?: PageTab[];
	active?: string;
	filters?: boolean;
}

export default function HeaderRow( {
	tabs = [],
	active = '',
	filters = true,
}: HeaderRowProps ) {
	return (
		<>
			<Stack
				className="eizport-header-row"
				justify="space-between"
				align="center"
			>
				{ tabs.length ? (
					<PageTabs tabs={ tabs } active={ active } />
				) : (
					<span />
				) }
				<Stack align="center" gap="sm">
					<DataViews.Search />
					{ filters && <DataViews.FiltersToggle /> }
					<DataViews.ViewConfig />
				</Stack>
			</Stack>
			{ filters && (
				<DataViews.FiltersToggled className="eizport-filters-row" />
			) }
		</>
	);
}
