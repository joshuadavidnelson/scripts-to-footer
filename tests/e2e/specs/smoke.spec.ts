/**
 * The harness gate. Every other project depends on this one, so if the
 * environment, plugin, theme or fixtures are not in a trustworthy state the
 * run stops here with one clear failure.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { PLUGIN_FILE, THEME_SLUG, TEST_API } from '../config/roles';
import { FILTER_OVERRIDES_OPTION } from '../config/filter-overrides';
import { ensureBaseline } from '../config/seed';
import { getPlacement, visitorContext, HEADER_HANDLES, FOOTER_HANDLE } from '../config/scripts';

test.describe( 'Harness smoke', () => {
	test( 'the plugin is installed and active', async ( { requestUtils } ) => {
		const plugin = await requestUtils.rest< { status: string; plugin: string } >( {
			path: `/wp/v2/plugins/${ PLUGIN_FILE.replace( /\.php$/, '' ) }`,
		} );
		expect( plugin.status ).toBe( 'active' );
	} );

	test( 'the pinned classic theme is active', async ( { requestUtils } ) => {
		const themes = await requestUtils.rest< Array< { stylesheet: string } > >( {
			path: '/wp/v2/themes',
			params: { status: 'active' },
		} );
		expect( themes.map( ( theme ) => theme.stylesheet ) ).toEqual( [ THEME_SLUG ] );
	} );

	test( 'the fixture mu-plugins are loaded', async ( { requestUtils } ) => {
		// stf-test-filters.php registers this setting.
		const settings = await requestUtils.rest< Record< string, unknown > >( { path: '/wp/v2/settings' } );
		expect( settings ).toHaveProperty( FILTER_OVERRIDES_OPTION );

		// stf-test-api.php registers this route.
		const state = await requestUtils.rest< Record< string, unknown > >( { path: `/${ TEST_API }/options` } );
		expect( state ).toHaveProperty( 'stf_version' );

		// stf-test-cpt.php registers the fixture post type.
		const types = await requestUtils.rest< Record< string, unknown > >( { path: '/wp/v2/types' } );
		expect( types ).toHaveProperty( 'stf_test_cpt' );
	} );

	test( 'the front end renders every fixture script', async ( { requestUtils, baseURL } ) => {
		await ensureBaseline( requestUtils );
		const visitor = await visitorContext( baseURL );
		const placement = await getPlacement( visitor, '/' );
		await visitor.dispose();

		const all = [ ...placement.headIds, ...placement.bodyIds ];
		for ( const handle of [ ...HEADER_HANDLES, FOOTER_HANDLE ] ) {
			expect( all, `${ handle } is enqueued by stf-test-scripts.php` ).toContain( `${ handle }-js` );
		}
	} );
} );
