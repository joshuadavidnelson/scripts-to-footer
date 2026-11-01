/**
 * Settings → Scripts to Footer: each exclusion checkbox must persist through
 * a real form save and flip exactly the front-end view it names.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { ensureBaseline, resetSite, CPT } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { getPluginState } from '../../config/plugin-state';
import { setFilterOverrides } from '../../config/filter-overrides';
import {
	getPlacement,
	visitorContext,
	expectMovedToFooter,
	expectLeftInHead,
	expectInHead,
	expectInFooter,
	expectNoDuplicates,
	FIXTURE_HEADER_HANDLES,
	FOOTER_HANDLE,
} from '../../config/scripts';

const SETTINGS_URL = '/wp-admin/options-general.php?page=scripts-to-footer';

interface ExclusionCase {
	label: string;
	/** Checkbox name suffix, i.e. the key stored in the options array. */
	key: string;
	/** Base-relative URL of the view the checkbox controls. */
	url: ( baseline: Baseline ) => string;
	status?: number;
}

const FRONT_END_CASES: ExclusionCase[] = [
	{ label: 'blog page', key: 'stf_exclude_home', url: ( b ) => b.urls.home },
	{ label: 'search results', key: 'stf_exclude_search', url: ( b ) => b.urls.search },
	{ label: '404 pages', key: 'stf_exclude_404', url: ( b ) => b.urls.notFound, status: 404 },
	{ label: 'author archives', key: 'stf_exclude_author_archive', url: ( b ) => b.urls.author },
	{ label: 'other (date) archives', key: 'stf_exclude_archive', url: ( b ) => b.urls.date },
	{ label: 'category archives', key: 'stf_exclude_category_archive', url: ( b ) => b.urls.category },
	{ label: 'tag archives', key: 'stf_exclude_post_tag_archive', url: ( b ) => b.urls.tag },
];

/**
 * Post type archive checkboxes the page lists. The default supported types
 * (`post`, `page`) have no archive view in the pinned theme, so only the
 * stored value is observable.
 */
const POST_TYPE_ARCHIVE_KEYS = [ 'stf_exclude_post_archive', 'stf_exclude_page_archive' ];

/**
 * Locator of a settings checkbox.
 *
 * @param page Admin page.
 * @param key  Option key.
 */
function box( page: Page, key: string ) {
	return page.locator( `input[name="scripts-to-footer[${ key }]"]` );
}

/**
 * Read one stored value of the `scripts-to-footer` options array.
 *
 * @param requestUtils Admin request utils.
 * @param key          Option key.
 */
async function storedOption( requestUtils: RequestUtils, key: string ): Promise< number | string | undefined > {
	const { options } = await getPluginState( requestUtils );
	return options ? options[ key ] : undefined;
}

/**
 * Submit the settings form and wait for the "settings saved" notice.
 *
 * @param page Admin page.
 */
async function saveSettings( page: Page ): Promise< void > {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	await expect( page.locator( '#setting-error-settings_updated' ) ).toBeVisible();
}

test.describe( 'Settings page', () => {
	let baseline: Baseline;

	test.beforeAll( async ( { requestUtils } ) => {
		baseline = await ensureBaseline( requestUtils );
		await resetSite( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetSite( requestUtils );
	} );

	test( 'renders every exclusion field for an administrator', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect( page.getByRole( 'heading', { name: 'Scripts to Footer Settings' } ) ).toBeVisible();
		for ( const { key } of FRONT_END_CASES ) {
			await expect( box( page, key ), key ).toBeVisible();
			await expect( box( page, key ), `${ key } is unchecked by default` ).not.toBeChecked();
		}
		for ( const key of POST_TYPE_ARCHIVE_KEYS ) {
			await expect( box( page, key ), key ).toBeVisible();
		}
		await expect( box( page, 'stf_jquery_header' ) ).toBeVisible();
	} );

	for ( const { label, key, url, status } of FRONT_END_CASES ) {
		test( `excluding ${ label } persists and leaves only that view in the head`, async ( { page, requestUtils, baseURL } ) => {
			const visitor = await visitorContext( baseURL );
			try {

				// Baseline: the view is moved, as is a control view.
				expectMovedToFooter( await getPlacement( visitor, url( baseline ), status ) );

				await page.goto( SETTINGS_URL );
				await box( page, key ).check();
				await saveSettings( page );

				// Persisted in the form after a fresh load and in storage.
				await page.reload();
				await expect( box( page, key ) ).toBeChecked();
				expect( await storedOption( requestUtils, key ) ).toBe( 1 );

				// Front end: that view is untouched, another view is still moved.
				expectLeftInHead( await getPlacement( visitor, url( baseline ), status ) );
				expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );

				// Unchecking restores it.
				await box( page, key ).uncheck();
				await saveSettings( page );
				await page.reload();
				await expect( box( page, key ) ).not.toBeChecked();
				expect( ( await storedOption( requestUtils, key ) ) ).toBeUndefined();
				expectMovedToFooter( await getPlacement( visitor, url( baseline ), status ) );
			} finally {
				await visitor.dispose();
			}
		} );
	}

	for ( const key of POST_TYPE_ARCHIVE_KEYS ) {
		test( `post type archive option ${ key } is saved as 1 and survives reload`, async ( { page, requestUtils } ) => {
			await page.goto( SETTINGS_URL );
			await box( page, key ).check();
			await saveSettings( page );

			await page.reload();
			await expect( box( page, key ) ).toBeChecked();
			expect( ( await storedOption( requestUtils, key ) ) ).toBe( 1 );

			await box( page, key ).uncheck();
			await saveSettings( page );
			expect( ( await storedOption( requestUtils, key ) ) ).toBeUndefined();
		} );
	}

	test( 'saving several exclusions stores them together and keeps the rest moved', async ( { page, requestUtils, baseURL } ) => {
		await page.goto( SETTINGS_URL );
		await box( page, 'stf_exclude_home' ).check();
		await box( page, 'stf_exclude_search' ).check();
		await saveSettings( page );
		await page.reload();

		await expect( box( page, 'stf_exclude_home' ) ).toBeChecked();
		await expect( box( page, 'stf_exclude_search' ) ).toBeChecked();
		await expect( box( page, 'stf_exclude_404' ) ).not.toBeChecked();
		expect( ( await getPluginState( requestUtils ) ).options ).toEqual( {
			stf_exclude_home: 1,
			stf_exclude_search: 1,
		} );

		const visitor = await visitorContext( baseURL );
		try {
			expectLeftInHead( await getPlacement( visitor, baseline.urls.home ) );
			expectLeftInHead( await getPlacement( visitor, baseline.urls.search ) );
			expectMovedToFooter( await getPlacement( visitor, baseline.urls.date ) );
		} finally {
			await visitor.dispose();
		}
	} );

	test( 'keep jQuery in the header leaves jQuery in head and moves the rest', async ( { page, requestUtils, baseURL } ) => {
		const visitor = await visitorContext( baseURL );
		try {
			expectMovedToFooter( await getPlacement( visitor, baseline.urls.home ) );

			await page.goto( SETTINGS_URL );
			await box( page, 'stf_jquery_header' ).check();
			await saveSettings( page );

			await page.reload();
			await expect( box( page, 'stf_jquery_header' ) ).toBeChecked();
			expect( ( await storedOption( requestUtils, 'stf_jquery_header' ) ) ).toBe( 1 );

			for ( const url of [ baseline.urls.home, baseline.postLink ] ) {
				const placement = await getPlacement( visitor, url );
				expectInHead( placement, [ 'jquery-core', 'jquery-migrate' ] );
				expectInFooter( placement, [ ...FIXTURE_HEADER_HANDLES, FOOTER_HANDLE ] );
				expectNoDuplicates( placement );
			}

			await box( page, 'stf_jquery_header' ).uncheck();
			await saveSettings( page );
			expectMovedToFooter( await getPlacement( visitor, baseline.urls.home ) );
		} finally {
			await visitor.dispose();
		}
	} );

	test( 'a custom post type archive checkbox saved through the form leaves only that archive in the head', async ( { page, requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, { scripts_to_footer_post_types: { append: [ CPT ] } } );
		const key = `stf_exclude_${ CPT }_archive`;

		const visitor = await visitorContext( baseURL );
		try {
			// Control: opted in, the archive moves before the form is saved.
			expectMovedToFooter( await getPlacement( visitor, baseline.urls.cptArchive ) );

			await page.goto( SETTINGS_URL );
			await box( page, key ).check();
			await saveSettings( page );

			await page.reload();
			await expect( box( page, key ) ).toBeChecked();
			expect( await storedOption( requestUtils, key ) ).toBe( 1 );

			expectLeftInHead( await getPlacement( visitor, baseline.urls.cptArchive ) );
			expectMovedToFooter( await getPlacement( visitor, baseline.postLink ) );
		} finally {
			await visitor.dispose();
		}
		await resetSite( requestUtils );
	} );
} );
