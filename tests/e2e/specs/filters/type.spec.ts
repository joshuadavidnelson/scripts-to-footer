/**
 * The dynamic `stf_{$type}` filters override the per-view exclusion options.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { expectLeftInHead, expectMovedToFooter } from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite, CPT, TAX } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setPluginOptions } from '../../config/plugin-state';
import { setFilterOverrides } from '../../config/filter-overrides';

interface View {
	type: string;
	url: ( baseline: Baseline ) => string;
	status?: number;
}

const VIEWS: View[] = [
	{ type: 'home', url: ( b ) => b.urls.home },
	{ type: 'search', url: ( b ) => b.urls.search },
	{ type: '404', url: ( b ) => b.urls.notFound, status: 404 },
	{ type: 'author_archive', url: ( b ) => b.urls.author },
	{ type: 'category_archive', url: ( b ) => b.urls.category },
	{ type: 'post_tag_archive', url: ( b ) => b.urls.tag },
	{ type: 'archive', url: ( b ) => b.urls.date },
];

test.describe( 'stf_{type} filters', () => {
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

	for ( const view of VIEWS ) {
		test( `stf_${ view.type } false leaves only that view in the head`, async ( { requestUtils, baseURL } ) => {
			await setFilterOverrides( requestUtils, { [ `stf_${ view.type }` ]: false } );

			expectLeftInHead( await fetchPlacement( baseURL, view.url( baseline ), view.status ?? 200 ) );

			// In-test control: the plugin is live and the home view still moves
			// (or, for the home case, a post does).
			const control = view.type === 'home' ? baseline.postLink : baseline.urls.home;
			expectMovedToFooter( await fetchPlacement( baseURL, control ) );

			// Scoped: a post and a different view still move.
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
			const other = VIEWS.find( ( v ) => v !== view )!;
			expectMovedToFooter( await fetchPlacement( baseURL, other.url( baseline ), other.status ?? 200 ) );
		} );
	}

	test( 'stf_home true overrides stf_exclude_home', async ( { requestUtils, baseURL } ) => {
		await setPluginOptions( requestUtils, { stf_exclude_home: 1 } );
		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.home ) );

		await setFilterOverrides( requestUtils, { stf_home: true } );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.home ) );
	} );

	test( 'stf_<cpt>_archive false leaves only the opted-in CPT archive in the head', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
			scripts_to_footer_taxonomies: { append: [ TAX ] },
		} );

		// Control: opted in, the CPT archive moves.
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );

		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
			scripts_to_footer_taxonomies: { append: [ TAX ] },
			[ `stf_${ CPT }_archive` ]: false,
		} );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );

	test( 'stf_<tax>_archive false leaves only the opted-in taxonomy archive in the head', async ( { requestUtils, baseURL } ) => {
		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
			scripts_to_footer_taxonomies: { append: [ TAX ] },
		} );

		// Control: opted in, the taxonomy archive moves.
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );

		await setFilterOverrides( requestUtils, {
			scripts_to_footer_post_types: { append: [ CPT ] },
			scripts_to_footer_taxonomies: { append: [ TAX ] },
			[ `stf_${ TAX }_archive` ]: false,
		} );

		expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
		expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
	} );
} );
