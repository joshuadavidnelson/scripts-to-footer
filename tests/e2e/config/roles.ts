import path from 'node:path';

/**
 * Directory holding each role's authenticated browser storage state.
 *
 * Written by `global-setup.ts` (admin) and `auth.setup.ts` (other roles).
 * Git-ignored: it holds live session cookies.
 */
export const AUTH_DIR = path.join( __dirname, '..', '..', '..', 'playwright', '.auth' );

/**
 * Roles the harness keeps a signed-in browser state for.
 */
export const ROLES = [ 'administrator', 'editor', 'author', 'subscriber' ] as const;

export type Role = ( typeof ROLES )[ number ];

/**
 * Non-admin users created by `auth.setup.ts`, keyed by role.
 *
 * Passwords are fixed (throwaway local env). The `stf_` prefix lets
 * `auth.setup.ts` find these users with one `/wp/v2/users` search.
 */
export const ROLE_USERS: Record<
	Exclude< Role, 'administrator' >,
	{ username: string; password: string; email: string; roles: string[] }
> = {
	editor: {
		username: 'stf_editor',
		password: 'stf-editor-password',
		email: 'stf_editor@example.com',
		roles: [ 'editor' ],
	},
	author: {
		username: 'stf_author',
		password: 'stf-author-password',
		email: 'stf_author@example.com',
		roles: [ 'author' ],
	},
	subscriber: {
		username: 'stf_subscriber',
		password: 'stf-subscriber-password',
		email: 'stf_subscriber@example.com',
		roles: [ 'subscriber' ],
	},
};

/**
 * Absolute path to the stored authentication state for a role.
 *
 * @param role Role to resolve the storage state for.
 */
export function storageStatePath( role: Role ): string {
	return path.join( AUTH_DIR, `${ role }.json` );
}

/**
 * Storage state used by every project unless a spec opts into another role.
 */
export const ADMIN_STORAGE_STATE = storageStatePath( 'administrator' );

/**
 * Slug of the plugin under test, as `RequestUtils.activatePlugin()` keys it
 * (the kebab-cased plugin name from `/wp/v2/plugins`).
 */
export const PLUGIN_SLUG = 'scripts-to-footer';

/**
 * Plugin basename, as WP-CLI and `/wp/v2/plugins/<plugin>` expect it.
 */
export const PLUGIN_FILE = 'scripts-to-footer/scripts-to-footer.php';

/**
 * The classic theme every spec runs under, except `theme/block-theme.spec.ts`.
 *
 * Pinned rather than inherited, because the default theme varies by WordPress
 * version. Twenty Twenty-One needs WordPress 5.3, the plugin's own floor.
 */
export const THEME_SLUG = 'twentytwentyone';

/**
 * The block theme `theme/block-theme.spec.ts` switches to (WordPress 6.7+).
 */
export const BLOCK_THEME_SLUG = 'twentytwentyfive';

/**
 * Namespace of the mu-plugin test API.
 *
 * @see tests/e2e/fixtures/stf-test-api.php
 */
export const TEST_API = 'stf-test/v1';
