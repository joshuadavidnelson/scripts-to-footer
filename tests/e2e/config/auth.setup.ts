/**
 * External dependencies
 */
import { test as setup, expect } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE, ROLE_USERS, storageStatePath } from './roles';
import type { Role } from './roles';

interface UserRecord {
	username?: string;
}

function isExistingUserError( error: unknown ): boolean {
	return (
		typeof error === 'object' &&
		error !== null &&
		[ 'existing_user_login', 'existing_user_email' ].includes(
			( error as { code?: string } ).code ?? ''
		)
	);
}

/**
 * Create the non-admin role users and persist a signed-in state for each, so
 * a spec can switch identity with
 * `test.use( { storageState: storageStatePath( 'editor' ) } )`.
 * Idempotent: existing users are reused; storage state is always refreshed.
 */
setup( 'create role users and store their authenticated state', async ( { baseURL } ) => {
	const admin = await RequestUtils.setup( {
		baseURL,
		storageStatePath: ADMIN_STORAGE_STATE,
	} );
	await admin.setupRest();

	const existingUsers: UserRecord[] = await admin.rest( {
		path: '/wp/v2/users',
		params: { per_page: 100, context: 'edit', search: 'stf_' },
	} );
	const existingLogins = new Set( existingUsers.map( ( user ) => user.username ) );

	for ( const [ role, user ] of Object.entries( ROLE_USERS ) ) {
		if ( ! existingLogins.has( user.username ) ) {
			try {
				await admin.createUser( {
					username: user.username,
					email: user.email,
					password: user.password,
					roles: user.roles,
				} );
			} catch ( error ) {
				if ( ! isExistingUserError( error ) ) {
					throw error;
				}
			}
		}

		const roleUtils = await RequestUtils.setup( {
			baseURL,
			user: { username: user.username, password: user.password },
			storageStatePath: storageStatePath( role as Role ),
		} );

		const storageState = await roleUtils.setupRest();

		// No cookies means the login silently failed; every spec using this
		// role would then be testing a logged-out visitor.
		expect( storageState.cookies.length ).toBeGreaterThan( 0 );

		await roleUtils.request.dispose();
	}

	await admin.request.dispose();
} );
