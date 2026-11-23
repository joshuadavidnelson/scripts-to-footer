/**
 * Generic filter-override mechanism for the e2e suite, backed by the
 * `stf_test_filter_overrides` mu-plugin fixture.
 *
 * It reaches every `stf_`/`scripts_to_footer_` filter the plugin fires,
 * including the dynamically-built `"stf_{$post_type}"` and `"stf_{$type}"`
 * hooks. The map is JSON-encoded into a single `string` option, which
 * round-trips through `/wp/v2/settings` on every supported WordPress.
 *
 * @see tests/e2e/fixtures/stf-test-filters.php
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Option name of the JSON-encoded override map.
 */
export const FILTER_OVERRIDES_OPTION = 'stf_test_filter_overrides';

export type JsonValue =
	| null
	| boolean
	| number
	| string
	| JsonValue[]
	| { [ key: string ]: JsonValue };

interface FilterOverridePriority {
	/** `add_filter()` priority; defaults to 10. */
	priority?: number;
}

/** Return `set` verbatim, in place of the incoming value. */
export interface SetFilterOverride extends FilterOverridePriority {
	set: JsonValue;
}

/** Merge `append` onto the incoming array value. */
export interface AppendFilterOverride extends FilterOverridePriority {
	append: JsonValue[];
}

/** Remove `remove` from the incoming array value. */
export interface RemoveFilterOverride extends FilterOverridePriority {
	remove: JsonValue[];
}

export type FilterOverrideValue =
	| boolean
	| number
	| string
	| SetFilterOverride
	| AppendFilterOverride
	| RemoveFilterOverride;

/**
 * Filter names the suite exercises, copied from the plugin's
 * `apply_filters()` calls. Dynamic names are listed as the concrete
 * instances the specs use.
 */
export const KNOWN_HOOKS = [
	// includes/class-stf-public.php
	'stf_include',
	'stf_exclude_scripts',
	'scripts_to_footer_exclude_page', // deprecated
	'stf_post', // "stf_{$post_type}"
	'stf_page',
	'stf_stf_test_cpt',
	'stf_home', // "stf_{$type}"
	'stf_search',
	'stf_404',
	'stf_author_archive',
	'stf_category_archive',
	'stf_post_tag_archive',
	'stf_archive',
	'stf_stf_test_cpt_archive',
	'stf_stf_test_tax_archive',

	// includes/class-stf-functions.php
	'scripts_to_footer_post_types',
	'scripts_to_footer_taxonomies',
] as const;

export type KnownHook = ( typeof KNOWN_HOOKS )[ number ];

/**
 * Map of hook name to override spec. The fixture ignores any key that does
 * not start with `stf_` or `scripts_to_footer_`.
 */
export type FilterOverrides = Partial<
	Record< KnownHook | ( string & {} ), FilterOverrideValue >
>;

/**
 * Priority an `stf_include` override needs to beat the plugin's own
 * `stf_includes()` callback, which runs at 10 and ignores the incoming value.
 */
export const AFTER_PLUGIN = 20;

/**
 * Replace the whole override map with `overrides`.
 *
 * @param requestUtils Admin request utils.
 * @param overrides    Map of hook name to override spec.
 */
export async function setFilterOverrides(
	requestUtils: RequestUtils,
	overrides: FilterOverrides
): Promise< void > {
	const include = overrides.stf_include;
	if (
		include !== undefined &&
		! ( typeof include === 'object' && ( include.priority ?? 10 ) > 10 )
	) {
		// At priority 10 or below the plugin's own stf_includes() runs last
		// and discards the override, so the spec would silently test nothing.
		throw new Error( 'An stf_include override needs { set: …, priority: AFTER_PLUGIN }.' );
	}

	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/settings',
		data: { [ FILTER_OVERRIDES_OPTION ]: JSON.stringify( overrides ) },
	} );
}

/**
 * Clear every filter override.
 *
 * @param requestUtils Admin request utils.
 */
export async function resetFilterOverrides(
	requestUtils: RequestUtils
): Promise< void > {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/settings',
		data: { [ FILTER_OVERRIDES_OPTION ]: '' },
	} );
}
