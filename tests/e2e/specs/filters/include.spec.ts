/**
 * The `stf_include` master filter. The plugin's own callback sits at priority
 * 10 and ignores the incoming value, so overrides run at AFTER_PLUGIN.
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
import { setFilterOverrides, AFTER_PLUGIN } from '../../config/filter-overrides';

test.describe( 'stf_include filter', () => {
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

	test( 'false excludes the home view and a post', async ( { requestUtils, baseURL } ) => {
		// Positive control: both move without the override.
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.home ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );

		await setFilterOverrides( requestUtils, { stf_include: { set: false, priority: AFTER_PLUGIN } } );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.home ) );
		expectLeftInHead( await fetchPlacement( baseURL, baseline.postLink ) );
	} );

	test( 'true re-includes a view excluded by option', async ( { requestUtils, baseURL } ) => {
		await setPluginOptions( requestUtils, { stf_exclude_home: 1 } );
		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.home ) );

		await setFilterOverrides( requestUtils, { stf_include: { set: true, priority: AFTER_PLUGIN } } );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.home ) );
	} );

	test( 'true re-includes a view the plugin does not support', async ( { requestUtils, baseURL } ) => {
		// A CPT single is unsupported by default.
		expectLeftInHead( await fetchPlacement( baseURL, baseline.itemLink ) );

		await setFilterOverrides( requestUtils, { stf_include: { set: true, priority: AFTER_PLUGIN } } );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
	} );
} );
