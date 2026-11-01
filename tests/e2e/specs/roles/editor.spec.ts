/**
 * Editor role: no access to the plugin settings, full use of the meta box.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ContentTracker, ensureBaseline, resetSite } from '../../config/seed';
import { getExcludeMeta } from '../../config/plugin-state';
import { storageStatePath } from '../../config/roles';
import { openMetaBoxes, saveWithMetaBoxes } from '../../config/editor';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
} from '../../config/scripts';

import { SETTINGS_URL, expectSettingsPageExistsForAdmin } from './settings-page';

test.describe( 'Editor role', () => {
	const tracker = new ContentTracker();

	test.use( { storageState: storageStatePath( 'editor' ) } );

	test.beforeAll( async ( { requestUtils } ) => {
		await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
		await resetSite( requestUtils );
	} );

	test( 'is denied the settings page', async ( { page, browser, baseURL } ) => {
		// Control, same test: the page exists for an administrator, so the denial below is the plugin's capability check.
		await expectSettingsPageExistsForAdmin( browser, baseURL );

		const response = await page.goto( SETTINGS_URL );

		expect( response?.status() ).toBe( 403 );
		await expect( page.locator( 'body' ) ).toContainText( /not allowed to access this page/i );
		await expect( page.locator( 'input[name^="scripts-to-footer["]' ) ).toHaveCount( 0 );
	} );

	test.describe( 'control: administrator', () => {
		test.use( { storageState: storageStatePath( 'administrator' ) } );

		test( 'sees the same settings page', async ( { page } ) => {
			const response = await page.goto( SETTINGS_URL );

			expect( response?.status() ).toBe( 200 );
			await expect( page.locator( 'input[name="scripts-to-footer[stf_exclude_home]"]' ) ).toBeVisible();
		} );
	} );

	test( 'can use the meta box on an admin-authored post and it takes effect', async ( { admin, page, requestUtils, baseURL } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Editor Role Post', content: 'x' } );
		const visitor = await visitorContext( baseURL );
		try {
			expectMovedToFooter( await getPlacement( visitor, post.link ) );

			await admin.editPost( post.id );
			await openMetaBoxes( page );
			await page.locator( '#stf_exclude' ).check();
			await saveWithMetaBoxes( page );

			expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: '1' } );
			expectLeftInHead( await getPlacement( visitor, post.link ) );
		} finally {
			await visitor.dispose();
		}
	} );
} );
