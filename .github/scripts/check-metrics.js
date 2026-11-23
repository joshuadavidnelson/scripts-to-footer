/**
 * Fails the build when a gated PHPMetrics value exceeds its ceiling.
 *
 * The gated keys are quality-thresholds.json's `metrics.gate`; their
 * ceilings are in `metrics.max`. Other metrics in `metrics.max` only
 * annotate the PR report.
 */

'use strict';

const fs = require( 'fs' );

const { checkGate } = require( './metrics' );

/**
 * Runs the gate.
 *
 * @param {Object} opts     Run options.
 * @param {Object} opts.env Environment variables to read config from.
 * @param {Object} opts.fs  fs module (or a stand-in) to read with.
 * @return {boolean} Whether every gated metric is within its ceiling.
 */
function main( { env = process.env, fs: fsModule = fs } = {} ) {
	const { HEAD_SUMMARY = 'reports/metrics-summary.json', THRESHOLDS = 'quality-thresholds.json' } = env;

	if ( ! fsModule.existsSync( HEAD_SUMMARY ) ) {
		console.error( `::error::Metrics summary not found: ${ HEAD_SUMMARY }` );
		return false;
	}

	const { metrics = {} } = JSON.parse( fsModule.readFileSync( THRESHOLDS, 'utf8' ) );
	const summary = JSON.parse( fsModule.readFileSync( HEAD_SUMMARY, 'utf8' ) );

	let ok = true;
	for ( const result of checkGate( summary, metrics.max || {}, metrics.gate || [] ) ) {
		if ( result.value === null ) {
			ok = false;
			console.error( `::error::${ result.label } is missing from the metrics summary` );
		} else if ( ! result.ok ) {
			ok = false;
			console.error( `::error::${ result.label } is ${ result.value } (max ${ result.max })` );
		} else {
			console.log( `${ result.label }: ${ result.value } (max ${ result.max }) OK` );
		}
	}
	return ok;
}

module.exports = { main };

/* istanbul ignore next -- CLI wiring, exercised by the CLI test. */
if ( require.main === module ) {
	process.exit( main( {} ) ? 0 : 1 );
}
