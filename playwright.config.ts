/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import type { PlaywrightTestConfig } from '@playwright/test';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE } from './tests/e2e/config/roles';

/**
 * Port of the e2e wp-env environment (`.wp-env.test.json`). The dev site on
 * 8888 (`.wp-env.json`) is never touched by the suite.
 */
const TESTS_PORT = 8889;

// Must be set before `@wordpress/scripts`' shared config is required below:
// it reads these at require time.
process.env.WP_BASE_URL ??= `http://localhost:${ TESTS_PORT }`;
process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, 'artifacts' );
process.env.STORAGE_STATE_PATH ??= ADMIN_STORAGE_STATE;

// eslint-disable-next-line @typescript-eslint/no-var-requires -- deliberately loaded after the env vars above.
const baseConfig: PlaywrightTestConfig = require( '@wordpress/scripts/config/playwright.config.js' );

const chrome = {
	...devices[ 'Desktop Chrome' ],
	storageState: ADMIN_STORAGE_STATE,
};

/**
 * E2E configuration for Scripts to Footer.
 *
 * Starts from the WordPress defaults shipped by `@wordpress/scripts` and
 * overrides only what this plugin needs: its own test tree and global setup,
 * serial execution against one shared site, and the e2e wp-env environment
 * as the web server.
 */
export default defineConfig( {
	...baseConfig,

	testDir: path.join( __dirname, 'tests', 'e2e' ),

	globalSetup: './tests/e2e/config/global-setup.ts',

	// Pinned, not inherited: every spec shares one site and its options.
	workers: 1,
	fullyParallel: false,

	use: {
		...baseConfig.use,
		storageState: ADMIN_STORAGE_STATE,
		trace: 'retain-on-failure',
	},

	webServer: {
		command: 'npm run env:test:start',
		port: TESTS_PORT,
		timeout: 600_000,
		reuseExistingServer: true,
	},

	projects: [
		{
			name: 'auth',
			testMatch: /auth\.setup\.ts$/,
		},
		// The gate: proves the environment, plugin, theme and fixtures are up
		// before anything else runs, so a broken harness fails once, fast,
		// instead of as dozens of misleading assertion failures.
		{
			name: 'smoke',
			use: chrome,
			testMatch: /\/smoke\.spec\.ts$/,
			dependencies: [ 'auth' ],
		},
		{
			name: 'chromium',
			use: chrome,
			testMatch: /.*\.spec\.ts$/,
			testIgnore: [ /\/smoke\.spec\.ts$/, /\/specs\/lifecycle\// ],
			dependencies: [ 'smoke' ],
		},
		// Deactivates, reactivates and uninstalls the plugin, so it runs
		// only after every other spec has finished. A new state-mutating spec
		// belongs in specs/lifecycle/ and needs no change here.
		{
			name: 'lifecycle',
			use: chrome,
			testMatch: /\/specs\/lifecycle\/.*\.spec\.ts$/,
			dependencies: [ 'chromium' ],
		},
	],
} );
