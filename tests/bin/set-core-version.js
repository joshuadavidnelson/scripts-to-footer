#!/usr/bin/env node

/**
 * Set (or clear) the WordPress core version of the e2e wp-env environment.
 *
 * Writes `core` into `.wp-env.test.override.json`, the override file wp-env
 * pairs with `--config=.wp-env.test.json`. Other keys in an existing override
 * file are kept.
 *
 * Usage:
 *   node ./tests/bin/set-core-version.js <version|branch>   e.g. 6.8, master
 *   node ./tests/bin/set-core-version.js latest             drop the pin
 *   node ./tests/bin/set-core-version.js                    no-op
 */

const fs = require( 'fs' );
const path = require( 'path' );

const overridePath = path.join( process.cwd(), '.wp-env.test.override.json' );

/** @type {Record<string, unknown>} */
const config = fs.existsSync( overridePath )
	? JSON.parse( fs.readFileSync( overridePath, 'utf8' ) )
	: {};

const [ version ] = process.argv.slice( 2 );

if ( ! version ) {
	process.exit( 0 );
}

if ( version === 'latest' ) {
	delete config.core;
} else {
	config.core = /^WordPress\/WordPress#/.test( version )
		? version
		: `WordPress/WordPress#${ version }`;
}

try {
	if ( Object.keys( config ).length ) {
		fs.writeFileSync( overridePath, JSON.stringify( config, null, '\t' ) + '\n' );
	} else if ( fs.existsSync( overridePath ) ) {
		fs.unlinkSync( overridePath );
	}
} catch ( error ) {
	// Exit non-zero: a swallowed failure leaves no override, which reads as
	// "latest stable" and silently tests the wrong WordPress on a pinned leg.
	console.error( error );
	process.exit( 1 );
}
