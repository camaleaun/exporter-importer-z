/**
 * A page's tabs (routes), with an optional count badge, as Jetpack Forms'
 * "Forms 1 | Responses 1" and Content Types' "Post Types | Taxonomies".
 */
import { useNavigate } from '@wordpress/route';
import { Badge, Tabs } from '@wordpress/ui';

export interface PageTab {
	/** Route, like "/copies". */
	path: string;
	label: string;
	count?: number;
}

interface PageTabsProps {
	tabs: PageTab[];
	active: string;
}

export default function PageTabs( { tabs, active }: PageTabsProps ) {
	const navigate = useNavigate();

	return (
		<Tabs.Root
			value={ active }
			onValueChange={ ( path: string ) => navigate( { href: path } ) }
		>
			<Tabs.List variant="minimal">
				{ tabs.map( ( tab ) => (
					<Tabs.Tab key={ tab.path } value={ tab.path }>
						<span>
							{ tab.label }
							{ tab.count !== undefined && (
								<Badge intent="draft" className="eizport-tab-count">
									{ String( tab.count ) }
								</Badge>
							) }
						</span>
					</Tabs.Tab>
				) ) }
			</Tabs.List>
		</Tabs.Root>
	);
}
