/**
 * The `stf_exclude_scripts` filter keeps chosen handles (and their
 * dependencies) in the head while everything else moves.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	expectInFooter,
	expectInHead,
	expectLeftInHead,
	expectMovedToFooter,
	expectNoDuplicates,
	expectOrderingIntact,
	HEADER_HANDLES,
	FOOTER_HANDLE,
} from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setPluginOptions } from '../../config/plugin-state';
import { setFilterOverrides } from '../../config/filter-overrides';
import { debugLogSize, readDebugLogSince } from '../../config/wp-cli';

test.describe( 'stf_exclude_scripts filter', () => {
	let baseline: Baseline;

	test.beforeAll( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
		baseline = await ensureBaseline( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
	} );

	test( 'a handle with dependencies keeps its whole chain in the head', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { stf_exclude_scripts: { append: [ 'stf-test-chain' ] } } );

		for ( const url of [ baseline.urls.home, baseline.postLink ] ) {
			const placement = await fetchPlacement( baseURL, url );
			const kept = [ 'stf-test-chain', 'stf-test-dep-b', 'stf-test-dep-a' ];
			expectInHead( placement, kept );
			expectInFooter( placement, [ ...HEADER_HANDLES.filter( ( h ) => ! kept.includes( h ) ), FOOTER_HANDLE ] );
			expect( placement.bodyIds ).toContain( 'stf-test-inline-js-before' );
			expectNoDuplicates( placement );
			expectOrderingIntact( placement );
		}
	} );

	test( 'a single handle stays in the head while the rest move', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { stf_exclude_scripts: { append: [ 'stf-test-header' ] } } );

		const placement = await fetchPlacement( baseURL, baseline.postLink );
		expectInHead( placement, [ 'stf-test-header' ] );
		expectInFooter( placement, [ ...HEADER_HANDLES.filter( ( h ) => h !== 'stf-test-header' ), FOOTER_HANDLE ] );
		expectNoDuplicates( placement );
		expectOrderingIntact( placement );
	} );

	test( 'a handle that is not enqueued causes no error and everything else moves', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { stf_exclude_scripts: { append: [ 'stf-test-not-enqueued' ] } } );

		// WP_DEBUG_DISPLAY is off, so warnings never reach the HTML; read the debug log.
		const logOffset = await debugLogSize();
		const placement = await fetchPlacement( baseURL, baseline.postLink );
		const newLog = await readDebugLogSince( logOffset );
		expectMovedToFooter( placement );
		expect( newLog.split( '\n' ).filter( ( line ) => /PHP (Warning|Notice|Deprecated|Fatal error).*scripts-to-footer/.test( line ) ) ).toEqual( [] );
		expect( [ ...placement.headIds, ...placement.bodyIds ].some( ( id ) => id.startsWith( 'stf-test-not-enqueued' ) ) ).toBe( false );
	} );

	test( 'on an excluded view everything stays in the head with no duplicates', async ( { requestUtils, baseURL } ) => {
		await setPluginOptions( requestUtils, { stf_exclude_home: 1 } );
		await setFilterOverrides( requestUtils, { stf_exclude_scripts: { append: [ 'stf-test-chain', 'stf-test-header' ] } } );

		const placement = await fetchPlacement( baseURL, baseline.urls.home );
		expectLeftInHead( placement );
		expectNoDuplicates( placement );

		// The option stays scoped: a post still moves its non-excluded scripts.
		const post = await fetchPlacement( baseURL, baseline.postLink );
		expectInFooter( post, [ 'stf-test-inline', 'jquery-core', FOOTER_HANDLE ] );
		expectInHead( post, [ 'stf-test-chain', 'stf-test-header' ] );
	} );
} );
