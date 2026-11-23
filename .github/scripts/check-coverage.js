/**
 * Fails the build when PHP coverage drops below the floors in
 * quality-thresholds.json.
 *
 * Run as a CI step after the test suite has written its clover report.
 */

'use strict';

const fs = require( 'fs' );

const { parseClover, checkFloors } = require( './coverage' );

/**
 * Formats one checkFloors() result as a single readable output line.
 *
 * @param {Object} result One entry from checkFloors().
 * @return {string} The line to print.
 */
function formatResult( result ) {
	const { metric, pct, floor, ok } = result;
	if ( pct === null ) {
		return `PHP ${ metric }: n/a — not measured`;
	}
	return `PHP ${ metric }: ${ pct.toFixed( 2 ) }% (floor ${ floor }%) ${ ok ? 'OK' : 'FAIL' }`;
}

/**
 * Reads, parses and floor-checks the clover report.
 *
 * @param {Object} opts     Run options.
 * @param {Object} opts.env Environment variables to read config from.
 * @param {Object} opts.fs  fs module (or a stand-in) to read with.
 * @return {boolean} Whether every floor passed.
 */
function main( { env = process.env, fs: fsModule = fs } = {} ) {
	const { PHP_CLOVER = 'coverage/php-clover.xml', THRESHOLDS = 'quality-thresholds.json' } = env;

	// A missing report means the test step never ran, or ran and silently
	// failed to write it. Skipping the check would let a broken pipeline
	// report green, so it is a hard failure.
	if ( ! fsModule.existsSync( PHP_CLOVER ) ) {
		console.error( `::error::PHP coverage file not found: ${ PHP_CLOVER }` );
		return false;
	}

	const thresholds = JSON.parse( fsModule.readFileSync( THRESHOLDS, 'utf8' ) );
	const actual = parseClover( fsModule.readFileSync( PHP_CLOVER, 'utf8' ) );

	let ok = true;
	for ( const result of checkFloors( actual, thresholds.coverage.php ) ) {
		console.log( formatResult( result ) );
		if ( ! result.ok ) {
			ok = false;
			console.error( `::error::PHP ${ result.metric } is ${ result.pct }% (floor ${ result.floor }%)` );
		}
	}
	return ok;
}

module.exports = { main };

/* istanbul ignore next -- CLI wiring, exercised by the CLI test. */
if ( require.main === module ) {
	process.exit( main( {} ) ? 0 : 1 );
}
