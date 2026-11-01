/**
 * Uninstalling the plugin removes its options and every `stf_exclude` meta.
 *
 * !!! The plugin directory is the bind-mounted git checkout. Every uninstall
 * must pass `--skip-delete`, or WP-CLI deletes the repository source. The
 * only call site is {@link uninstallKeepingFiles}, which hard-codes it.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ContentTracker, ensureBaseline, resetSite } from '../../config/seed';
import {
	getExcludeMeta,
	getPluginState,
	setExcludeMeta,
	setPluginOptions,
} from '../../config/plugin-state';
import { PLUGIN_SLUG } from '../../config/roles';
import { expectCliSuccess, wpCli, wpCliOk } from '../../config/wp-cli';

/**
 * Run the plugin's uninstall routine without deleting its files.
 *
 * `--skip-delete` is mandatory (see the file header); `--user=admin` is
 * needed because uninstall.php `wp_die()`s unless the user can
 * `install_plugins`.
 */
async function uninstallKeepingFiles(): Promise< void > {
	expectCliSuccess(
		await wpCli( [ 'plugin', 'uninstall', PLUGIN_SLUG, '--deactivate', '--skip-delete', '--user=admin' ] ),
		'plugin uninstall'
	);
}

/**
 * Seed everything the plugin stores and prove it is there.
 *
 * @param requestUtils Admin request utils.
 * @param postId       Post that receives the exclude meta.
 */
async function seedStoredState( requestUtils: RequestUtils, postId: number ): Promise< void > {
	await setPluginOptions( requestUtils, { stf_exclude_home: 1, stf_jquery_header: 1 } );
	await wpCliOk( [ 'option', 'update', 'stf_version', '0.0.1' ] );
	await wpCliOk( [ 'option', 'update', 'stf_previous_version', '0.0.0' ] );
	await setExcludeMeta( requestUtils, postId, 1 );

	const state = await getPluginState( requestUtils );
	expect( state.options ).toEqual( { stf_exclude_home: 1, stf_jquery_header: 1 } );
	expect( state.stf_version ).toBe( '0.0.1' );
	expect( state.stf_previous_version ).toBe( '0.0.0' );
	expect( ( await getExcludeMeta( requestUtils, postId ) ).exists ).toBe( true );
}

test.describe( 'Plugin uninstall', () => {
	const tracker = new ContentTracker();

	test.beforeAll( async ( { requestUtils } ) => {
		await ensureBaseline( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await wpCliOk( [ 'plugin', 'activate', PLUGIN_SLUG ] );
		// uninstall.php leaves this behind (see the fixme below); don't let
		// the seeded value outlive the spec.
		await wpCli( [ 'option', 'delete', 'stf_previous_version' ] );
		await resetSite( requestUtils );
	} );

	test( 'removes the settings option, the version option and all exclude meta', async ( { requestUtils } ) => {
		const first = await tracker.create( requestUtils, { title: 'STF Uninstall A', content: 'x' } );
		const second = await tracker.create( requestUtils, { title: 'STF Uninstall B', content: 'x' }, 'pages' );
		await seedStoredState( requestUtils, first.id );
		await setExcludeMeta( requestUtils, second.id, 'on' );

		await uninstallKeepingFiles();

		const state = await getPluginState( requestUtils );
		expect( state.options ).toBeNull();
		expect( state.stf_version ).toBeNull();
		expect( ( await getExcludeMeta( requestUtils, first.id ) ).exists ).toBe( false );
		expect( ( await getExcludeMeta( requestUtils, second.id ) ).exists ).toBe( false );
	} );

	// Plugin bug: uninstall.php deletes only `scripts-to-footer` and
	// `stf_version`; the `stf_previous_version` option written on upgrade is
	// left behind in the database. Fixed in #32 ("fix: delete
	// stf_previous_version on uninstall"); remove the fixme once that fix is
	// in this branch's base.
	test.fixme( 'removes the stf_previous_version option', async ( { requestUtils } ) => {
		const post = await tracker.create( requestUtils, { title: 'STF Uninstall Prev', content: 'x' } );
		await seedStoredState( requestUtils, post.id );

		await uninstallKeepingFiles();

		expect( ( await getPluginState( requestUtils ) ).stf_previous_version ).toBeNull();
	} );
} );
