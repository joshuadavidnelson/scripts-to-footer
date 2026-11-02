/**
 * The deprecated `scripts_to_footer_exclude_page` filter still excludes a
 * singular post, and logs a deprecation notice when it does.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectLeftInHead, expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setFilterOverrides } from '../../config/filter-overrides';
import { debugLogSize, readDebugLogSince } from '../../config/wp-cli';

const DEPRECATION = /Scripts-to-Footer Plugin Error: The 'scripts_to_footer_exclude_page' is depreciated/;

test.describe( 'scripts_to_footer_exclude_page (deprecated)', () => {
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

	for ( const value of [ true, 'on' ] ) {
		test( `${ JSON.stringify( value ) } excludes a post and logs the deprecation`, async ( { requestUtils, baseURL } ) => {
			// Positive control: the post moves without the filter.
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );

			await setFilterOverrides( requestUtils, { scripts_to_footer_exclude_page: value } );

			const offset = await debugLogSize();
			expectLeftInHead( await fetchPlacement( baseURL, baseline.postLink ) );
			expect( await readDebugLogSince( offset ) ).toMatch( DEPRECATION );
		} );
	}

	for ( const value of [ false, null ] ) {
		test( `${ JSON.stringify( value ) } does not exclude and does not log`, async ( { requestUtils, baseURL } ) => {
			// Positive control for the log assertion: true does log.
			await setFilterOverrides( requestUtils, { scripts_to_footer_exclude_page: true } );
			const controlOffset = await debugLogSize();
			expectLeftInHead( await fetchPlacement( baseURL, baseline.pageLink ) );
			expect( await readDebugLogSince( controlOffset ) ).toMatch( DEPRECATION );

			await setFilterOverrides( requestUtils, { scripts_to_footer_exclude_page: { set: value } } );
			const offset = await debugLogSize();
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.pageLink ) );
			expect( await readDebugLogSince( offset ) ).not.toMatch( DEPRECATION );
		} );
	}
} );
