/**
 * Parses a PHPUnit clover report into a common shape and checks it against
 * the floors in quality-thresholds.json.
 *
 * Used by check-coverage.js and render-pr-report.js — split out so the
 * parsing and floor logic can be unit tested without touching the
 * filesystem or process.exit().
 */

'use strict';

/**
 * Builds a Metric, or null when nothing was measured.
 *
 * Xdebug line coverage never populates clover's conditionals attributes, so
 * total === 0 is the normal case for branches, not an error. A 0% for an
 * unmeasured metric would read as a catastrophic regression when it means
 * "no signal either way", so floor checks treat it as a pass.
 *
 * @param {number} covered Covered count.
 * @param {number} total   Total count.
 * @return {{covered: number, total: number, pct: number}|null} The metric, or null.
 */
function toMetric( covered, total ) {
	if ( ! total ) {
		return null;
	}
	return {
		covered,
		total,
		pct: Math.round( ( covered / total ) * 10000 ) / 100,
	};
}

/**
 * Parses a PHPUnit clover XML report into project-level coverage metrics.
 *
 * Clover nests a <metrics> element inside every <file> and <class>, and
 * those come before the project total in document order. "files" is an
 * attribute only the project-level total carries, so matching on it finds
 * the right element however many file/class metrics precede it.
 *
 * @param {string} xml Clover XML report contents.
 * @return {{lines: Object|null, methods: Object|null, branches: Object|null}} Project coverage.
 */
function parseClover( xml ) {
	const projectIndex = xml.indexOf( '<project' );
	if ( projectIndex === -1 ) {
		throw new Error( 'parseClover: no <project> element found in clover XML' );
	}

	const match = /<metrics\s+[^>]*\bfiles="[^"]*"[^>]*\/>/.exec( xml.slice( projectIndex ) );
	if ( ! match ) {
		throw new Error( 'parseClover: no project-level <metrics> element found' );
	}

	const attrs = {};
	const attrPattern = /(\w+)="([^"]*)"/g;
	let attrMatch;
	while ( ( attrMatch = attrPattern.exec( match[ 0 ] ) ) !== null ) {
		attrs[ attrMatch[ 1 ] ] = Number( attrMatch[ 2 ] );
	}

	return {
		lines: toMetric( attrs.coveredstatements, attrs.statements ),
		methods: toMetric( attrs.coveredmethods, attrs.methods ),
		branches: toMetric( attrs.coveredconditionals, attrs.conditionals ),
	};
}

/**
 * Checks parsed coverage against a set of floors.
 *
 * A metric that was not measured (null) is never a failure. A floor key with
 * no matching entry in actual is a configuration error, not a pass: ignoring
 * it would let a typo in quality-thresholds.json disable a floor silently.
 *
 * @param {Object} actual Parsed coverage, as returned by parseClover.
 * @param {Object} floors Floor percentages keyed by metric name.
 * @return {Array<{metric: string, pct: number|null, floor: number, ok: boolean}>} One entry per floor key.
 */
function checkFloors( actual, floors ) {
	return Object.keys( floors ).map( ( metric ) => {
		if ( ! ( metric in actual ) ) {
			throw new Error( `checkFloors: "${ metric }" is not a known coverage metric` );
		}

		const floor = floors[ metric ];
		const data = actual[ metric ];

		if ( data === null ) {
			return { metric, pct: null, floor, ok: true };
		}

		return { metric, pct: data.pct, floor, ok: data.pct >= floor };
	} );
}

module.exports = { parseClover, checkFloors };
