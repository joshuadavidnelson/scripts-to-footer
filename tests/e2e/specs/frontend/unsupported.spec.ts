/**
 * Custom post types and taxonomies are unsupported until a site opts them in
 * through `scripts_to_footer_post_types` / `scripts_to_footer_taxonomies`.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectLeftInHead, expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite, CPT, TAX } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setFilterOverrides } from '../../config/filter-overrides';

test.describe( 'Unsupported post types and taxonomies', () => {
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

	test.describe( 'by default', () => {
		test( 'a baseline post still moves (positive control)', async ( { baseURL } ) => {
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
		} );

		test( 'a CPT single is left in the head', async ( { baseURL } ) => {
			expectLeftInHead( await fetchPlacement( baseURL, baseline.itemLink ) );
		} );

		test( 'a CPT archive is left in the head', async ( { baseURL } ) => {
			expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
		} );

		test( 'a custom taxonomy archive is left in the head', async ( { baseURL } ) => {
			expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
		} );
	} );

	test.describe( 'once opted in through filters', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await setFilterOverrides( requestUtils, {
				scripts_to_footer_post_types: { append: [ CPT ] },
				scripts_to_footer_taxonomies: { append: [ TAX ] },
			} );
		} );

		test( 'a CPT single moves', async ( { baseURL } ) => {
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
		} );

		test( 'a CPT archive moves', async ( { baseURL } ) => {
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
		} );

		test( 'a custom taxonomy archive moves', async ( { baseURL } ) => {
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
		} );
	} );

	test( 'opting in only the post type leaves the taxonomy archive in the head', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { scripts_to_footer_post_types: { append: [ CPT ] } } );

		expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
	} );
} );
