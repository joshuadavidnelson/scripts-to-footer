/**
 * Author role: the meta box works on their own posts only; no settings page.
 */
import { test, expect, RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ContentTracker, ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { getExcludeMeta } from '../../config/plugin-state';
import { ROLE_USERS, storageStatePath } from '../../config/roles';
import { openMetaBoxes, saveWithMetaBoxes } from '../../config/editor';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
} from '../../config/scripts';

const SETTINGS_URL = '/wp-admin/options-general.php?page=scripts-to-footer';

test.describe( 'Author role', () => {
	const tracker = new ContentTracker();
	let baseline: Baseline;
	let authorUtils: RequestUtils;

	test.use( { storageState: storageStatePath( 'author' ) } );

	test.beforeAll( async ( { requestUtils, baseURL } ) => {
		baseline = await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
		authorUtils = await RequestUtils.setup( {
			baseURL,
			user: { username: ROLE_USERS.author.username, password: ROLE_USERS.author.password },
			storageStatePath: storageStatePath( 'author' ),
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
		await resetSite( requestUtils );
	} );

	test.afterAll( async () => {
		await authorUtils.request.dispose();
	} );

	test( 'can use the meta box on their own post and it takes effect', async ( { admin, page, requestUtils, baseURL } ) => {
		const post = await tracker.create( authorUtils, { title: 'STF Author Own Post', content: 'x' } );
		const visitor = await visitorContext( baseURL );
		expectMovedToFooter( await getPlacement( visitor, post.link ) );

		await admin.editPost( post.id );
		await openMetaBoxes( page );
		await page.locator( '#stf_exclude' ).check();
		await saveWithMetaBoxes( page );

		expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: '1' } );
		expectLeftInHead( await getPlacement( visitor, post.link ) );

		await visitor.dispose();
	} );

	test( 'cannot open the editor for the administrator\'s post', async ( { admin, page, requestUtils, baseURL } ) => {
		// Control: the author reaches the box on their own post.
		const own = await tracker.create( authorUtils, { title: 'STF Author Control', content: 'x' } );
		await admin.editPost( own.id );
		await openMetaBoxes( page );
		await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 1 );

		const response = await page.goto( `/wp-admin/post.php?post=${ baseline.postId }&action=edit` );
		expect( response?.status() ).toBe( 403 );
		await expect( page.locator( 'body' ) ).toContainText( /not allowed to edit/i );
		await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 0 );

		expect( ( await getExcludeMeta( requestUtils, baseline.postId ) ).exists ).toBe( false );
		const visitor = await visitorContext( baseURL );
		expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );
		await visitor.dispose();
	} );

	test( 'is denied the settings page', async ( { page } ) => {
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
} );
