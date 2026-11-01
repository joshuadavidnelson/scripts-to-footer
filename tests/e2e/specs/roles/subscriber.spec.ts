/**
 * Subscriber role: no settings page, no editor, and a forged save request
 * cannot create `stf_exclude` meta.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ContentTracker, ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { getExcludeMeta, setExcludeMeta } from '../../config/plugin-state';
import { storageStatePath } from '../../config/roles';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
} from '../../config/scripts';

const SETTINGS_URL = '/wp-admin/options-general.php?page=scripts-to-footer';

test.describe( 'Subscriber role', () => {
	const tracker = new ContentTracker();
	let baseline: Baseline;

	test.use( { storageState: storageStatePath( 'subscriber' ) } );

	test.beforeAll( async ( { requestUtils } ) => {
		baseline = await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
		await setExcludeMeta( requestUtils, baseline.postId, null );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
		await setExcludeMeta( requestUtils, baseline.postId, null );
		await resetSite( requestUtils );
	} );

	test( 'is denied the settings page', async ( { page } ) => {
		const response = await page.goto( SETTINGS_URL );

		expect( response?.status() ).toBe( 403 );
		await expect( page.locator( 'body' ) ).toContainText( /not allowed to access this page/i );
		await expect( page.locator( 'input[name^="scripts-to-footer["]' ) ).toHaveCount( 0 );
	} );

	test.describe( 'control: administrator', () => {
		test.use( { storageState: storageStatePath( 'administrator' ) } );

		test( 'sees the settings page and the meta box', async ( { page, admin } ) => {
			const response = await page.goto( SETTINGS_URL );
			expect( response?.status() ).toBe( 200 );
			await expect( page.locator( 'input[name="scripts-to-footer[stf_exclude_home]"]' ) ).toBeVisible();

			await admin.editPost( baseline.postId );
			await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 1 );
		} );
	} );

	test( 'is denied the post editor', async ( { page } ) => {
		const response = await page.goto( `/wp-admin/post.php?post=${ baseline.postId }&action=edit` );

		expect( response?.status() ).toBe( 403 );
		await expect( page.locator( 'body' ) ).toContainText( /not allowed to edit/i );
		await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 0 );
	} );

	test( 'a forged post.php save creates no meta, while the same request from an admin does', async ( { page, browser, requestUtils, baseURL } ) => {
		// Control: harvest the nonces from an admin editor page, then replay
		// them as a plain form POST from an admin session on a scratch post.
		const scratch = await tracker.create( requestUtils, { title: 'STF Forged Control', content: 'x' } );
		const adminContext = await browser.newContext( { baseURL, storageState: storageStatePath( 'administrator' ) } );
		const editResponse = await adminContext.request.get( `/wp-admin/post.php?post=${ scratch.id }&action=edit` );
		const editHtml = await editResponse.text();
		const fieldValue = ( id: string ): string => {
			const match = editHtml.match( new RegExp( `id="${ id }" name="${ id }" value="([^"]+)"` ) );
			expect( match, `#${ id } is printed on the edit screen` ).not.toBeNull();
			return match ? match[ 1 ] : '';
		};
		const scratchForm = { stf_nonce: fieldValue( 'stf_nonce' ), _wpnonce: fieldValue( '_wpnonce' ) };
		const adminSave = await adminContext.request.post( '/wp-admin/post.php', {
			form: {
				action: 'editpost',
				post_ID: String( scratch.id ),
				post_type: 'post',
				post_title: 'STF Forged Control',
				stf_exclude: 'on',
				...scratchForm,
			},
			failOnStatusCode: false,
		} );
		expect( adminSave.status() ).toBeLessThan( 400 );
		expect( await getExcludeMeta( requestUtils, scratch.id ) ).toEqual( { exists: true, value: '1' } );
		await adminContext.close();

		// Forgery as the subscriber against the baseline post, reusing the
		// admin's (invalid for this user) nonces.
		const nonceFor = ( await ( await page.request.get( '/wp-admin/profile.php' ) ).text() ).match( /name="_wpnonce" value="([^"]+)"/ );
		const forged = await page.request.post( '/wp-admin/post.php', {
			form: {
				action: 'editpost',
				post_ID: String( baseline.postId ),
				post_type: 'post',
				post_title: 'STF E2E Post',
				stf_exclude: 'on',
				stf_nonce: scratchForm.stf_nonce,
				_wpnonce: nonceFor?.[ 1 ] ?? scratchForm._wpnonce,
			},
			failOnStatusCode: false,
		} );
		expect( forged.status() ).toBeGreaterThanOrEqual( 400 );

		expect( ( await getExcludeMeta( requestUtils, baseline.postId ) ).exists ).toBe( false );
		const visitor = await visitorContext( baseURL );
		expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );
		expectLeftInHead( await getPlacement( visitor, scratch.link ) );
		await visitor.dispose();
	} );
} );
