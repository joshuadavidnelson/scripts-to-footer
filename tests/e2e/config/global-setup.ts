/**
 * External dependencies
 */
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE, PLUGIN_SLUG, THEME_SLUG } from './roles';
import { ensureBaseline, resetSite } from './seed';

/**
 * Prepare the e2e site once, before any project runs: authenticate, pin the
 * classic theme, make sure the plugin is active (a lifecycle run that died
 * mid-test can leave it inactive), reset site options, plugin options and
 * filter overrides, and seed the baseline content.
 *
 * @param config Resolved Playwright config.
 */
async function globalSetup( config: FullConfig ): Promise< void > {
	const { baseURL } = config.projects[ 0 ].use;

	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		baseURL,
		storageStatePath: ADMIN_STORAGE_STATE,
	} );

	await requestUtils.setupRest();

	try {
		await requestUtils.activateTheme( THEME_SLUG );
	} catch ( error ) {
		throw new Error(
			`Failed to activate the "${ THEME_SLUG }" theme the e2e suite pins.`,
			{ cause: error }
		);
	}

	await requestUtils.activatePlugin( PLUGIN_SLUG );
	await resetSite( requestUtils );
	await ensureBaseline( requestUtils );

	await requestContext.dispose();
}

export default globalSetup;
