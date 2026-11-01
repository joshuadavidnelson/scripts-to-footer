/**
 * Parses a PHPUnit Clover report into project-level coverage metrics.
 *
 * Used by render-pr-report.js. Kept apart from it, and free of any file or
 * process access, so it can be unit tested directly. Node built-ins only:
 * the workflow runs these scripts without an npm install.
 */

'use strict';

/**
 * Builds a metric, or null when nothing was measured.
 *
 * Xdebug line coverage never populates clover's conditionals attributes, so
 * a total of 0 is the normal case for branches, not an error. Reporting 0%
 * for an unmeasured metric would read as a collapse instead of "no signal".
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
 * Parses a Clover XML report into project-level metrics.
 *
 * Clover nests a <metrics> element inside every <file> and <class>, ahead of
 * the project total in document order. Only the project-level element
 * carries a "files" attribute (a count of files in the report), so that
 * attribute identifies it however many file or class metrics precede it.
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

module.exports = { toMetric, parseClover };
