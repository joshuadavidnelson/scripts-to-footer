/**
 * Shared by the role specs: proves the settings page exists for an
 * administrator, so a denial for another role can only come from the plugin
 * (core returns the same 403 for any unregistered admin page).
 */
import { expect } from '@playwright/test';
import type { Browser } from '@playwright/test';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE } from '../../config/roles';

export const SETTINGS_URL = '/wp-admin/options-general.php?page=scripts-to-footer';

/**
 * Open the settings page in a fresh administrator context and assert its
 * form renders.
 *
 * @param browser Playwright browser.
 * @param baseURL Site base URL.
 */
export async function expectSettingsPageExistsForAdmin(
	browser: Browser,
	baseURL: string | undefined
): Promise< void > {
	const context = await browser.newContext( { baseURL, storageState: ADMIN_STORAGE_STATE } );
	try {
		const adminPage = await context.newPage();
		const response = await adminPage.goto( SETTINGS_URL );
		expect( response?.status(), 'the settings page exists for an administrator' ).toBe( 200 );
		await expect( adminPage.locator( 'input[name="scripts-to-footer[stf_exclude_home]"]' ) ).toBeVisible();
	} finally {
		await context.close();
	}
}
