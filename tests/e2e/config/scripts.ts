/**
 * The suite's core assertion: fetch a front-end URL's HTML, split it at
 * `</head>`, and report which half each `<script id="…">` printed in.
 *
 * Works on raw HTML from an `APIRequestContext` rather than the DOM, because
 * the browser's parser hoists nothing across `</head>` but does normalize
 * markup; the raw response is exactly what the plugin controls.
 */
import { expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';

/**
 * Handles `stf-test-scripts.php` enqueues for the header. With the plugin
 * active on an included view, every one of these must print after `</head>`.
 *
 * `jquery` is an alias without a `src`, so it prints as `jquery-core` and
 * `jquery-migrate`.
 */
export const HEADER_HANDLES = [
	'stf-test-header',
	'stf-test-dep-a',
	'stf-test-dep-b',
	'stf-test-chain',
	'stf-test-inline',
	'jquery-core',
	'jquery-migrate',
] as const;

/**
 * Header handles that belong to the fixture alone (no jQuery), for specs
 * where jQuery's placement is under test separately.
 */
export const FIXTURE_HEADER_HANDLES = HEADER_HANDLES.filter(
	( handle ) => ! handle.startsWith( 'jquery' )
);

/**
 * Handle the fixture registers for the footer from the start.
 */
export const FOOTER_HANDLE = 'stf-test-footer';

/**
 * Where a page's scripts printed.
 */
export interface ScriptPlacement {
	url: string;
	status: number;
	/** Every `<script id>` before `</head>`, in document order. */
	headIds: string[];
	/** Every `<script id>` after `</head>`, in document order. */
	bodyIds: string[];
	html: string;
}

/**
 * A `<script …>` opening tag. Quoted attribute values are consumed whole, so
 * a `>` inside one does not end the tag early.
 */
const SCRIPT_TAG = /<script\b((?:[^>"']|"[^"]*"|'[^']*')*)>/gi;

/**
 * The `id` attribute inside a tag's attribute string. Requires whitespace
 * before `id`, so `data-id="…"` is not mistaken for it.
 */
const ID_ATTR = /(?:^|\s)id\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/i;

/**
 * Every `<script id>` in `html`, in document order.
 *
 * @param html HTML fragment.
 */
export function scriptIds( html: string ): string[] {
	const ids: string[] = [];
	for ( const tag of html.matchAll( SCRIPT_TAG ) ) {
		const id = ID_ATTR.exec( tag[ 1 ] );
		if ( id ) {
			ids.push( id[ 1 ] ?? id[ 2 ] ?? id[ 3 ] );
		}
	}
	return ids;
}

/**
 * Parse an HTML document into a {@link ScriptPlacement}.
 *
 * @param url    URL the HTML came from (for messages).
 * @param status HTTP status.
 * @param html   Raw HTML.
 */
export function parsePlacement( url: string, status: number, html: string ): ScriptPlacement {
	const index = html.search( /<\/head\s*>/i );
	expect( index, `${ url } has no </head>` ).toBeGreaterThan( -1 );

	return {
		url,
		status,
		headIds: scriptIds( html.slice( 0, index ) ),
		bodyIds: scriptIds( html.slice( index ) ),
		html,
	};
}

/**
 * Create a logged-out request context, so front-end fetches see what a
 * visitor sees (no admin bar, no auth cookies).
 *
 * @param baseURL Site base URL.
 */
export async function visitorContext( baseURL: string | undefined ): Promise< APIRequestContext > {
	return playwrightRequest.newContext( {
		baseURL,
		storageState: { cookies: [], origins: [] },
	} );
}

/**
 * Fetch a front-end URL and report where its scripts printed.
 *
 * @param request        Request context (use {@link visitorContext}).
 * @param url            Absolute or base-relative URL.
 * @param expectedStatus Expected HTTP status (200 unless testing a 404).
 */
export async function getPlacement(
	request: APIRequestContext,
	url: string,
	expectedStatus = 200
): Promise< ScriptPlacement > {
	const response = await request.get( url, { failOnStatusCode: false } );
	const html = await response.text();

	expect( response.status(), `unexpected status for ${ url }` ).toBe( expectedStatus );

	return parsePlacement( response.url(), response.status(), html );
}

/**
 * Assert each handle printed exactly once, after `</head>`.
 *
 * @param placement Result of {@link getPlacement}.
 * @param handles   Script handles (without the `-js` suffix).
 */
export function expectInFooter( placement: ScriptPlacement, handles: readonly string[] ): void {
	for ( const handle of handles ) {
		const id = `${ handle }-js`;
		expect( placement.headIds, `${ id } should not print in <head> of ${ placement.url }` ).not.toContain( id );
		expect( placement.bodyIds, `${ id } should print after </head> of ${ placement.url }` ).toContain( id );
	}
}

/**
 * Assert each handle printed exactly once, before `</head>`.
 *
 * @param placement Result of {@link getPlacement}.
 * @param handles   Script handles (without the `-js` suffix).
 */
export function expectInHead( placement: ScriptPlacement, handles: readonly string[] ): void {
	for ( const handle of handles ) {
		const id = `${ handle }-js`;
		expect( placement.headIds, `${ id } should print in <head> of ${ placement.url }` ).toContain( id );
		expect( placement.bodyIds, `${ id } should not print after </head> of ${ placement.url }` ).not.toContain( id );
	}
}

/**
 * Assert no `<script id>` printed more than once across the whole document.
 *
 * @param placement Result of {@link getPlacement}.
 */
export function expectNoDuplicates( placement: ScriptPlacement ): void {
	const all = [ ...placement.headIds, ...placement.bodyIds ];
	const duplicates = all.filter( ( id, index ) => all.indexOf( id ) !== index );
	expect( duplicates, `script ids printed twice on ${ placement.url }` ).toEqual( [] );
}

/**
 * Assert the inline `before` script stayed with its handle, and dependencies
 * printed ahead of their dependents, wherever they landed.
 *
 * @param placement Result of {@link getPlacement}.
 */
export function expectOrderingIntact( placement: ScriptPlacement ): void {
	const all = [ ...placement.headIds, ...placement.bodyIds ];
	const at = ( id: string ) => all.indexOf( id );

	// Presence first: indexOf() of a missing id is -1, which would make every
	// "printed before" comparison below pass vacuously.
	for ( const id of [ 'stf-test-inline-js-before', 'stf-test-inline-js', 'stf-test-dep-a-js', 'stf-test-dep-b-js', 'stf-test-chain-js' ] ) {
		expect( all, `${ id } printed on ${ placement.url }` ).toContain( id );
	}
	expect( at( 'stf-test-inline-js' ) - at( 'stf-test-inline-js-before' ), 'inline before-script directly precedes its handle' ).toBe( 1 );
	expect( at( 'stf-test-dep-a-js' ) ).toBeLessThan( at( 'stf-test-dep-b-js' ) );
	expect( at( 'stf-test-dep-b-js' ) ).toBeLessThan( at( 'stf-test-chain-js' ) );
}

/**
 * The plugin's default outcome on an included view: every fixture header
 * script (and jQuery) in the footer, the footer script still in the footer,
 * nothing duplicated, ordering intact.
 *
 * @param placement Result of {@link getPlacement}.
 */
export function expectMovedToFooter( placement: ScriptPlacement ): void {
	expectInFooter( placement, [ ...HEADER_HANDLES, FOOTER_HANDLE ] );
	expect( placement.bodyIds, 'inline before-script follows its handle to the footer' ).toContain( 'stf-test-inline-js-before' );
	expectNoDuplicates( placement );
	expectOrderingIntact( placement );
}

/**
 * The untouched outcome (plugin inactive or view excluded): every fixture
 * header script (and jQuery) in `<head>`, the footer script in the footer.
 *
 * @param placement Result of {@link getPlacement}.
 */
export function expectLeftInHead( placement: ScriptPlacement ): void {
	expectInHead( placement, HEADER_HANDLES );
	expectInFooter( placement, [ FOOTER_HANDLE ] );
	expect( placement.headIds, 'inline before-script stays with its handle in <head>' ).toContain( 'stf-test-inline-js-before' );
	expectNoDuplicates( placement );
	expectOrderingIntact( placement );
}
