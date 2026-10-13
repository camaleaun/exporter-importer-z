/**
 * Ways to support the project with money. The plugin is free and stays
 * free: donations are optional. An option shows on /donate/ (and in the
 * header) only once its value is filled in — never a link that leads
 * nowhere.
 *
 * - GitHub Sponsors: turn it on at https://github.com/sponsors, then set
 *   `enabled: true`.
 * - Pix: the key (e-mail, phone, CPF/CNPJ or random key), with the
 *   receiver's name and city as the bank shows them; the page builds the
 *   "Pix copia e cola" code and its QR code at build time.
 * - PayPal, Ko-fi, Apoia.se: the full URL of the page. Apoia.se's page is
 *   in Brazilian Portuguese, and the site says so next to the link.
 */
export const donate = {
	githubSponsors: { enabled: false, url: 'https://github.com/sponsors/eizport' },
	pix: { key: '', name: '', city: '' },
	paypal: { url: '' },
	kofi: { url: '' },
	apoiase: { url: 'https://apoia.se/eizport' },
};

export interface DonateLink {
	id: string;
	name: string;
	url: string;
	text: string;
	audience: string;
	/** The language of the page the link leads to, when not English. */
	lang?: { code: string; label: string };
}

/** The link options that are set, in order. */
export const donateLinks = (): DonateLink[] =>
	[
		{
			id: 'github',
			name: 'GitHub Sponsors',
			url: donate.githubSponsors.enabled ? donate.githubSponsors.url : '',
			text: 'Monthly or one-time, with no fees: GitHub passes 100% on.',
			audience: 'Anywhere',
		},
		{ id: 'paypal', name: 'PayPal', url: donate.paypal.url, text: 'Any amount, by card or PayPal balance.', audience: 'Anywhere' },
		{ id: 'kofi', name: 'Ko-fi', url: donate.kofi.url, text: 'Buy a coffee — one-time or monthly.', audience: 'Anywhere' },
		{
			id: 'apoiase',
			name: 'Apoia.se',
			url: donate.apoiase.url,
			text: 'Monthly support in Brazilian reais, from R$ 5. Every supporter’s name goes on the supporters page.',
			audience: 'Brazil',
			lang: { code: 'pt-BR', label: 'Page in Portuguese (pt-BR)' },
		},
	].filter( ( link ) => link.url !== '' );

/** Whether any way to donate money is set. */
export const hasDonations = (): boolean => donateLinks().length > 0 || donate.pix.key !== '';

/** A Pix field: id, length (2 digits), value. */
const field = ( id: string, value: string ): string => `${ id }${ String( value.length ).padStart( 2, '0' ) }${ value }`;

/** CRC16-CCITT (0x1021, initial 0xFFFF), as the BR Code standard asks. */
const crc16 = ( text: string ): string => {
	let crc = 0xffff;
	for ( const char of text ) {
		crc ^= char.charCodeAt( 0 ) << 8;
		for ( let bit = 0; bit < 8; bit++ ) {
			crc = crc & 0x8000 ? ( ( crc << 1 ) ^ 0x1021 ) & 0xffff : ( crc << 1 ) & 0xffff;
		}
	}
	return crc.toString( 16 ).toUpperCase().padStart( 4, '0' );
};

/** ASCII only, as banks' apps expect in name and city. */
const plain = ( text: string, max: number ): string =>
	text
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[^A-Za-z0-9 .-]/g, '' )
		.slice( 0, max );

/** The static "Pix copia e cola" code (BR Code, any amount). */
export const pixCode = (): string => {
	const { key, name, city } = donate.pix;
	const payload =
		field( '00', '01' ) +
		field( '26', field( '00', 'br.gov.bcb.pix' ) + field( '01', key ) ) +
		field( '52', '0000' ) +
		field( '53', '986' ) +
		field( '58', 'BR' ) +
		field( '59', plain( name, 25 ) ) +
		field( '60', plain( city, 15 ) ) +
		field( '62', field( '05', '***' ) ) +
		'6304';
	return payload + crc16( payload );
};
