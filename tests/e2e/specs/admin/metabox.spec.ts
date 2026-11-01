/**
 * The "Scripts to Footer Settings" meta box in the block editor: the
 * per-post `stf_exclude` switch, its legacy `'on'` value, and its absence on
 * unsupported post types.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ContentTracker, ensureBaseline, resetSite } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { getExcludeMeta, setExcludeMeta } from '../../config/plugin-state';
import { wpCliOk } from '../../config/wp-cli';
import { openMetaBoxes, saveWithMetaBoxes } from '../../config/editor';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
} from '../../config/scripts';

test.describe( 'Meta box', () => {
	const tracker = new ContentTracker();
	let baseline: Baseline;

	test.beforeAll( async ( { requestUtils } ) => {
		baseline = await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
		await resetSite( requestUtils );
	} );

	test( 'checking the box and updating stores 1 and keeps scripts in the head; unchecking restores', async ( { admin, page, requestUtils, baseURL } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Metabox Toggle', content: 'x' } );
		const visitor = await visitorContext( baseURL );
		expectMovedToFooter( await getPlacement( visitor, post.link ) );

		await admin.editPost( post.id );
		await openMetaBoxes( page );
		await expect( page.locator( '#scripts-to-footer .hndle, #scripts-to-footer h2' ).first() ).toContainText( 'Scripts to Footer Settings' );
		const checkbox = page.locator( '#stf_exclude' );
		await expect( checkbox ).not.toBeChecked();

		await checkbox.check();
		await saveWithMetaBoxes( page );

		expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: '1' } );
		expectLeftInHead( await getPlacement( visitor, post.link ) );

		await page.reload();
		await openMetaBoxes( page );
		await expect( page.locator( '#stf_exclude' ) ).toBeChecked();

		await page.locator( '#stf_exclude' ).uncheck();
		await saveWithMetaBoxes( page );

		expect( ( await getExcludeMeta( requestUtils, post.id ) ).exists ).toBe( false );
		expectMovedToFooter( await getPlacement( visitor, post.link ) );

		await visitor.dispose();
	} );

	test( 'legacy "on" meta seeded through the test API is honored on the front end', async ( { requestUtils, baseURL } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Legacy Api', content: 'x' } );
		const visitor = await visitorContext( baseURL );

		await setExcludeMeta( requestUtils, post.id, 'on' );
		expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: 'on' } );
		expectLeftInHead( await getPlacement( visitor, post.link ) );

		await setExcludeMeta( requestUtils, post.id, null );
		expectMovedToFooter( await getPlacement( visitor, post.link ) );

		await visitor.dispose();
	} );

	test( 'legacy "on" meta seeded with WP-CLI is honored on the front end', async ( { requestUtils, baseURL } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Legacy Cli', content: 'x' } );
		const visitor = await visitorContext( baseURL );

		await wpCliOk( [ 'post', 'meta', 'update', String( post.id ), 'stf_exclude', 'on' ] );
		expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: 'on' } );
		expectLeftInHead( await getPlacement( visitor, post.link ) );

		await setExcludeMeta( requestUtils, post.id, null );
		expectMovedToFooter( await getPlacement( visitor, post.link ) );

		await visitor.dispose();
	} );

	test( 'opening a legacy "on" post shows the box checked and migrates the meta to 1', async ( { admin, page, requestUtils, baseURL } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Legacy Migrate', content: 'x' } );
		await setExcludeMeta( requestUtils, post.id, 'on' );

		await admin.editPost( post.id );
		await openMetaBoxes( page );
		await expect( page.locator( '#stf_exclude' ) ).toBeChecked();

		expect( await getExcludeMeta( requestUtils, post.id ) ).toEqual( { exists: true, value: '1' } );

		const visitor = await visitorContext( baseURL );
		expectLeftInHead( await getPlacement( visitor, post.link ) );
		await visitor.dispose();
	} );

	test( 'the meta box appears for posts and pages but not for the unsupported custom post type', async ( { admin, page, requestUtils } ) => {
		const pagePost = await tracker.create( requestUtils, { title: 'STF Metabox Page', content: 'x' }, 'pages' );

		await admin.editPost( baseline.postId );
		await openMetaBoxes( page );
		await expect( page.locator( '#scripts-to-footer' ) ).toBeVisible();
		await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 1 );

		await admin.editPost( pagePost.id );
		await openMetaBoxes( page );
		await expect( page.locator( '#scripts-to-footer' ) ).toBeVisible();

		// The editor is fully loaded for the CPT item, yet no box is added.
		await admin.editPost( baseline.itemId );
		await expect( page.getByRole( 'region', { name: 'Editor top bar' } ) ).toBeVisible();
		await expect( page.locator( '#scripts-to-footer' ) ).toHaveCount( 0 );
		await expect( page.locator( '#stf_exclude' ) ).toHaveCount( 0 );
	} );
} );
