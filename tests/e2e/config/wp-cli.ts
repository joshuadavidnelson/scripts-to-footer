/**
 * WP-CLI bridge for specs that need genuine command-line semantics
 * (activation, deactivation, uninstall, raw meta). Used sparingly: each call
 * starts a container process, so routine seeding goes through REST instead.
 *
 * Always targets the e2e environment defined by `.wp-env.test.json`.
 */
import { execFile } from 'node:child_process';
import path from 'node:path';
import { promisify } from 'node:util';
import { expect } from '@playwright/test';

const execFileAsync = promisify( execFile );

/**
 * Repo root: `wp-env` resolves its config relative to the process cwd.
 */
export const REPO_ROOT = path.join( __dirname, '..', '..', '..' );

/**
 * The wp-env config of the e2e environment.
 *
 * wp-env 12 deprecates the built-in `tests` environment (`testsEnvironment`,
 * `tests-cli`) in favour of a separate config file, so the suite runs against
 * its own environment on port 8889 and the dev site on 8888 is untouched.
 */
export const WP_ENV_CONFIG = '.wp-env.test.json';

/**
 * Local `wp-env` binary, invoked directly to skip npx resolution per call.
 */
const WP_ENV_BIN = path.join( REPO_ROOT, 'node_modules', '.bin', 'wp-env' );

/**
 * Upper bound for one CLI call, so a hung container fails the test instead
 * of stalling the run until the global timeout.
 */
const CLI_TIMEOUT = 120_000;

export interface WpCliResult {
	exitCode: number;
	stdout: string;
	stderr: string;
}

/**
 * Run a command in the e2e environment's `cli` container. Never throws on a
 * non-zero exit; the caller asserts the returned exit code.
 *
 * @param command Command and arguments, already split (no shell quoting).
 */
export async function runInCli( command: string[] ): Promise< WpCliResult > {
	try {
		const { stdout, stderr } = await execFileAsync(
			WP_ENV_BIN,
			[ `--config=${ WP_ENV_CONFIG }`, 'run', 'cli', '--', ...command ],
			{ cwd: REPO_ROOT, maxBuffer: 10 * 1024 * 1024, timeout: CLI_TIMEOUT }
		);

		return { exitCode: 0, stdout: stdout.trim(), stderr: stderr.trim() };
	} catch ( error ) {
		const failure = error as {
			code?: number | string;
			signal?: string | null;
			message?: string;
			stdout?: string;
			stderr?: string;
		};
		const stderr = ( failure.stderr ?? '' ).trim();

		return {
			exitCode: typeof failure.code === 'number' ? failure.code : 1,
			stdout: ( failure.stdout ?? '' ).trim(),
			// A spawn failure (ENOENT) or a timeout kill has no stderr of its
			// own; surface the reason instead of an empty string.
			stderr: stderr || `${ failure.message ?? 'wp-env failed' }${ failure.signal ? ` (signal ${ failure.signal })` : '' }`,
		};
	}
}

/**
 * Run a `wp` command in the e2e environment.
 *
 * @param args `wp` arguments, already split.
 */
export async function wpCli( args: string[] ): Promise< WpCliResult > {
	return runInCli( [ 'wp', ...args ] );
}

/**
 * Run a `wp` command and throw unless it exited cleanly.
 *
 * @param args `wp` arguments.
 * @return Trimmed stdout.
 */
export async function wpCliOk( args: string[] ): Promise< string > {
	const result = await wpCli( args );

	if ( 0 !== result.exitCode ) {
		throw new Error(
			`wp ${ args.join( ' ' ) } exited ${ result.exitCode }\n${ result.stderr }`
		);
	}

	return result.stdout;
}

/**
 * Assert a WP-CLI command exited cleanly with no PHP fatal in its output.
 *
 * Exit code alone is not enough: a bare `exit()` in loaded PHP ends the
 * process at 0, taking the command's own report with it.
 *
 * @param result Result of a {@link wpCli} call.
 * @param label  Label for the assertion message.
 */
export function expectCliSuccess( result: WpCliResult, label: string ): void {
	expect(
		result.exitCode,
		`${ label } exited ${ result.exitCode }:\n${ result.stderr }`
	).toBe( 0 );
	expect( result.stdout + result.stderr ).not.toMatch( /fatal error/i );
}

/**
 * Absolute path of the debug log inside the containers.
 */
export const DEBUG_LOG = '/var/www/html/wp-content/debug.log';

/**
 * Current size of the debug log in bytes (0 when it does not exist yet).
 * Pair with {@link readDebugLogSince} to read only what a test produced.
 *
 * Throws if the size cannot be read: silently falling back to 0 would make a
 * later {@link readDebugLogSince} return the whole log, so an assertion could
 * match a message logged by an earlier request.
 */
export async function debugLogSize(): Promise< number > {
	const result = await runInCli( [ 'sh', '-c', `if [ -f ${ DEBUG_LOG } ]; then wc -c < ${ DEBUG_LOG }; else echo 0; fi` ] );
	const size = result.stdout.trim().split( /\s+/ ).pop() ?? '';

	if ( 0 !== result.exitCode || ! /^\d+$/.test( size ) ) {
		throw new Error( `Could not read the size of ${ DEBUG_LOG } (exit ${ result.exitCode }): ${ result.stderr || result.stdout }` );
	}

	return parseInt( size, 10 );
}

/**
 * Debug log contents written after byte `offset` ('' when the log does not
 * exist). Throws if the log exists but cannot be read, so a failed read can't
 * pass a "nothing was logged" assertion.
 *
 * @param offset Byte offset returned by {@link debugLogSize}.
 */
export async function readDebugLogSince( offset: number ): Promise< string > {
	const result = await runInCli( [ 'sh', '-c', `if [ -f ${ DEBUG_LOG } ]; then tail -c +${ offset + 1 } ${ DEBUG_LOG }; fi` ] );

	if ( 0 !== result.exitCode ) {
		throw new Error( `Could not read ${ DEBUG_LOG } (exit ${ result.exitCode }): ${ result.stderr }` );
	}

	return result.stdout;
}
