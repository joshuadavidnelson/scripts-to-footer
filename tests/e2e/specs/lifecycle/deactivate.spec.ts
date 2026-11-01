/**
 * Deactivating the plugin must hand every script back to <head>, and
 * reactivating it must move them to the footer again.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { PLUGIN_SLUG } from '../../config/roles';
import { expectCliSuccess, wpCli } from '../../config/wp-cli';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
} from '../../config/scripts';

test.describe( 'Plugin deactivation', () => {
	let baseline: Baseline;

	test.beforeAll( async ( { requestUtils } ) => {
		baseline = await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Whatever happened above, leave the plugin active for later runs.
		await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		await resetSite( requestUtils );
	} );

	test( 'restores head scripts on the home page and a post', async ( { baseURL } ) => {
		const visitor = await visitorContext( baseURL );
		expectMovedToFooter( await getPlacement( visitor, baseline.urls.home ) );
		expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );

		expectCliSuccess( await wpCli( [ 'plugin', 'deactivate', PLUGIN_SLUG ] ), 'plugin deactivate' );

		expectLeftInHead( await getPlacement( visitor, baseline.urls.home ) );
		expectLeftInHead( await getPlacement( visitor, baseline.postLink ) );

		await visitor.dispose();
	} );

	test( 'reactivating moves scripts to the footer again', async ( { baseURL } ) => {
		const visitor = await visitorContext( baseURL );

		expectCliSuccess( await wpCli( [ 'plugin', 'deactivate', PLUGIN_SLUG ] ), 'plugin deactivate' );
		expectLeftInHead( await getPlacement( visitor, baseline.urls.home ) );

		const activated = await wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		expectCliSuccess( activated, 'plugin activate' );
		expect( activated.stdout ).toMatch( /Activated|Success/i );

		expectMovedToFooter( await getPlacement( visitor, baseline.urls.home ) );
		expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );

		await visitor.dispose();
	} );
} );
