/**
 * Each `stf_exclude_{type}` setting leaves exactly its own view in the head,
 * and the jQuery setting keeps jQuery (only) in the head.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	expectInFooter,
	expectInHead,
	expectLeftInHead,
	expectMovedToFooter,
	expectNoDuplicates,
	expectOrderingIntact,
	FIXTURE_HEADER_HANDLES,
	FOOTER_HANDLE,
} from '../../config/scripts';
import { fetchPlacement } from '../../config/front';
import { ensureBaseline, resetSite, CPT, TAX } from '../../config/seed';
import type { Baseline } from '../../config/seed';
import { setPluginOptions } from '../../config/plugin-state';
import { setFilterOverrides } from '../../config/filter-overrides';

interface View {
	label: string;
	option: string;
	url: ( baseline: Baseline ) => string;
	status?: number;
}

const VIEWS: View[] = [
	{ label: 'home', option: 'stf_exclude_home', url: ( b ) => b.urls.home },
	{ label: 'search', option: 'stf_exclude_search', url: ( b ) => b.urls.search },
	{ label: '404', option: 'stf_exclude_404', url: ( b ) => b.urls.notFound, status: 404 },
	{ label: 'author archive', option: 'stf_exclude_author_archive', url: ( b ) => b.urls.author },
	{ label: 'generic (date) archive', option: 'stf_exclude_archive', url: ( b ) => b.urls.date },
	{ label: 'category archive', option: 'stf_exclude_category_archive', url: ( b ) => b.urls.category },
	{ label: 'tag archive', option: 'stf_exclude_post_tag_archive', url: ( b ) => b.urls.tag },
];

test.describe( 'Exclusion options', () => {
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
		test( `${ view.option } leaves only the ${ view.label } in the head`, async ( { requestUtils, baseURL } ) => {
			await setPluginOptions( requestUtils, { [ view.option ]: 1 } );

			expectLeftInHead( await fetchPlacement( baseURL, view.url( baseline ), view.status ?? 200 ) );

			// The option is scoped: a single post and every other listed view still move.
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
			for ( const other of VIEWS.filter( ( v ) => v !== view ) ) {
				expectMovedToFooter( await fetchPlacement( baseURL, other.url( baseline ), other.status ?? 200 ) );
			}
		} );
	}

	test( 'stf_jquery_header keeps jQuery in the head and moves everything else', async ( { requestUtils, baseURL } ) => {
		await setPluginOptions( requestUtils, { stf_jquery_header: 1 } );

		for ( const url of [ baseline.urls.home, baseline.postLink ] ) {
			const placement = await fetchPlacement( baseURL, url );
			expectInHead( placement, [ 'jquery-core', 'jquery-migrate' ] );
			expectInFooter( placement, [ ...FIXTURE_HEADER_HANDLES, FOOTER_HANDLE ] );
			expect( placement.bodyIds ).toContain( 'stf-test-inline-js-before' );
			expectNoDuplicates( placement );
			expectOrderingIntact( placement );
		}
	} );

	test( 'without stf_jquery_header jQuery moves to the footer (control)', async ( { baseURL } ) => {
		const placement = await fetchPlacement( baseURL, baseline.urls.home );
		expectInFooter( placement, [ 'jquery-core', 'jquery-migrate' ] );
	} );

	test.describe( 'custom post type and taxonomy archives', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await setFilterOverrides( requestUtils, {
				scripts_to_footer_post_types: { append: [ CPT ] },
				scripts_to_footer_taxonomies: { append: [ TAX ] },
			} );
		} );

		test( `stf_exclude_${ CPT }_archive leaves only the CPT archive in the head`, async ( { requestUtils, baseURL } ) => {
			// Positive control: both archives are included without the option.
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );

			await setPluginOptions( requestUtils, { [ `stf_exclude_${ CPT }_archive` ]: 1 } );

			expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
		} );

		test( `stf_exclude_${ TAX }_archive leaves only the taxonomy archive in the head`, async ( { requestUtils, baseURL } ) => {
			await setPluginOptions( requestUtils, { [ `stf_exclude_${ TAX }_archive` ]: 1 } );

			expectLeftInHead( await fetchPlacement( baseURL, baseline.urls.taxArchive ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.urls.cptArchive ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.itemLink ) );
			expectMovedToFooter( await fetchPlacement( baseURL, baseline.postLink ) );
		} );
	} );
} );
