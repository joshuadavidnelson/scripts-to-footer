/**
 * The dynamic `stf_{$post_type}` filter excludes one singular post type.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectLeftInHead, expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite, CPT } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setFilterOverrides } from '../../config/filter-overrides';

test.describe( 'stf_{post_type} filters', () => {
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

	test( 'stf_post false leaves posts in the head while pages still move', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { stf_post: false } );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.postLink ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.pageLink ) );
	} );

	test( 'stf_page false leaves pages in the head while posts still move', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { stf_page: false } );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.pageLink ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );

	test( `stf_${ CPT } false leaves an opted-in CPT in the head`, async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
		} );
		// Positive control: the opted-in CPT moves.
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );

		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
			[ `stf_${ CPT }` ]: false,
		} );
		expectLeftInHead( await fetchPlacement( baseURL, baseline.itemLink ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );
} );
