/**
 * The plugin under a block (full site editing) theme. The theme is switched
 * in `beforeAll` and always restored in `afterAll`.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectLeftInHead, expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setPluginOptions } from '../../config/plugin-state';
import { BLOCK_THEME_SLUG, THEME_SLUG } from '../../config/roles';

test.describe( 'Block theme', () => {
	let baseline: Baseline;

	test.beforeAll( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
		baseline = await ensureBaseline( requestUtils );
		await requestUtils.activateTheme( BLOCK_THEME_SLUG );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		try {
			await resetSite( requestUtils );
		} finally {
			await requestUtils.activateTheme( THEME_SLUG );
		}
	} );

	const views: Array< [ string, ( b: Baseline ) => string, number? ] > = [
		[ 'home', ( b ) => b.urls.home ],
		[ 'a post', ( b ) => b.postLink ],
		[ 'a page', ( b ) => b.pageLink ],
		[ 'search', ( b ) => b.urls.search ],
		[ 'a category archive', ( b ) => b.categoryLink ],
		[ 'a 404', ( b ) => b.urls.notFound, 404 ],
	];

	for ( const [ label, url, status ] of views ) {
		test( `${ label } moves scripts to the footer`, async ( { baseURL } ) => {
			expectMovedToFooter( await fetchPlacement( baseURL, url( baseline ), status ?? 200 ) );
		} );
	}

	test( 'stf_exclude_home leaves only the home view in the head', async ( { requestUtils, baseURL } ) => {
		await setPluginOptions( requestUtils, { stf_exclude_home: 1 } );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.home ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );
} );
