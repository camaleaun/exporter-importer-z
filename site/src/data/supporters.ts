/**
 * The people who support the project with money, thanked on /supporters/
 * (the Apoia.se rewards promise a name on this page). Add a name only with
 * the person's consent, as they want it shown; `url` is optional. Tiers are
 * the Apoia.se rewards: Playground names are featured.
 */
export type SupporterTier = 'playground' | 'import' | 'bundle' | 'export';

export interface Supporter {
	name: string;
	tier: SupporterTier;
	url?: string;
}

export const supporters: Supporter[] = [];

/** The tiers, highest first, as the Apoia.se rewards name them (amounts per month). */
export const tiers: { id: SupporterTier; name: string; amount: string }[] = [
	{ id: 'playground', name: 'Playground', amount: 'R$ 100' },
	{ id: 'import', name: 'Import', amount: 'R$ 25' },
	{ id: 'bundle', name: 'Bundle', amount: 'R$ 10' },
	{ id: 'export', name: 'Export', amount: 'R$ 5' },
];
