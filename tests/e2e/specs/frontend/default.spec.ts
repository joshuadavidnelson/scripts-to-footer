/**
 * With default options and no filters, the plugin moves every header script
 * to the footer on every supported view.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite, useStaticFrontPage } from '../../config/seed';
import type { Baseline } from '../../config/seed';

test.describe( 'Default behavior', () => {
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

	test( 'home (latest posts) moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.home ) );
	} );

	test( 'a static front page moves scripts to the footer', async ( { requestUtils, baseURL } ) => {
		await useStaticFrontPage( requestUtils, baseline );
		expectMovedToFooter( await fetchPlacement( baseURL, '/' ) );
	} );

	test( 'the posts page of a static front page moves scripts to the footer', async ( { requestUtils, baseURL } ) => {
		await useStaticFrontPage( requestUtils, baseline );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.blogPageLink ) );
	} );

	test( 'a single post moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );

	test( 'a page moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.pageLink ) );
	} );

	test( 'search results move scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.search ) );
	} );

	test( 'a 404 moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.notFound, 404 ) );
	} );

	test( 'a category archive moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.categoryLink ) );
	} );

	test( 'a tag archive moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.tagLink ) );
	} );

	test( 'an author archive moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.author ) );
	} );

	test( 'a date archive moves scripts to the footer', async ( { baseURL } ) => {
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.date ) );
	} );
} );
