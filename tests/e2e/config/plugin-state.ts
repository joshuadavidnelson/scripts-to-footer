/**
 * Read and write the plugin's own stored state through the
 * `stf-test/v1` mu-plugin API: the `scripts-to-footer` options array and the
 * raw `stf_exclude` post meta.
 *
 * @see tests/e2e/fixtures/stf-test-api.php
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { TEST_API } from './roles';

/**
 * Keys of the `scripts-to-footer` options array, as the settings page saves
 * them (`stf_exclude_{$type}` plus `stf_jquery_header`).
 */
export type PluginOptions = Partial< Record< string, number | string > >;

export interface StoredPluginState {
	options: PluginOptions | null | false;
	stf_version: string | null | false;
	stf_previous_version: string | null | false;
}

/**
 * Replace the `scripts-to-footer` options array.
 *
 * @param requestUtils Admin request utils.
 * @param options      The whole options array.
 */
export async function setPluginOptions(
	requestUtils: RequestUtils,
	options: PluginOptions
): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: `/${ TEST_API }/options`,
		data: { options },
	} );
}

/**
 * Delete the `scripts-to-footer` option, restoring plugin defaults.
 *
 * @param requestUtils Admin request utils.
 */
export async function resetPluginOptions( requestUtils: RequestUtils ): Promise< void > {
	await requestUtils.rest( { method: 'DELETE', path: `/${ TEST_API }/options` } );
}

/**
 * Read the plugin's stored options and version markers.
 *
 * @param requestUtils Admin request utils.
 */
export async function getPluginState( requestUtils: RequestUtils ): Promise< StoredPluginState > {
	return requestUtils.rest< StoredPluginState >( { path: `/${ TEST_API }/options` } );
}

/**
 * Write raw `stf_exclude` meta on a post (`null` deletes it). Accepts the
 * legacy `'on'` value the metabox no longer writes.
 *
 * @param requestUtils Admin request utils.
 * @param postId       Post ID.
 * @param value        Meta value, or null to delete.
 */
export async function setExcludeMeta(
	requestUtils: RequestUtils,
	postId: number,
	value: number | string | null
): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: `/${ TEST_API }/meta/${ postId }`,
		data: { value },
	} );
}

/**
 * Read raw `stf_exclude` meta on a post.
 *
 * @param requestUtils Admin request utils.
 * @param postId       Post ID.
 */
export async function getExcludeMeta(
	requestUtils: RequestUtils,
	postId: number
): Promise< { exists: boolean; value: string } > {
	return requestUtils.rest( { path: `/${ TEST_API }/meta/${ postId }` } );
}
