/**
 * Shared knowledge about PHPMetrics summaries and quality-thresholds.json's
 * `metrics` block, used by check-metrics.js (the CI gate) and
 * render-pr-report.js (the PR comment).
 */

'use strict';

// quality-thresholds.json only stores numbers (metrics.max); this catalogue
// is the schema knowledge of where each thresholded metric lives in a
// PHPMetrics summary and what to call it in the table. A key present in
// metrics.max but missing here is a config error, not silently dropped.
const CATALOGUE = {
	critical: { section: 'Violations', label: 'Violations: critical' },
	error: { section: 'Violations', label: 'Violations: error' },
	avgCyclomaticComplexityByClass: { section: 'Complexity', label: 'Avg cyclomatic complexity / class' },
	logicalLinesByMethod: { section: 'LOC', label: 'Logical lines per method' },
	lackCohesionOfMethods: { section: 'OOP', label: 'Lack of cohesion of methods' },
	avgEfferentCoupling: { section: 'Coupling', label: 'Avg efferent coupling' },
};

/**
 * Reads one metric, treating anything non-numeric as absent.
 *
 * @param {Object} report  A PHPMetrics summary.
 * @param {string} section Top-level group, e.g. 'Complexity'.
 * @param {string} key     Metric name within the group.
 * @return {number|null} The value, or null when the report omits it.
 */
function value( report, section, key ) {
	const found = ( ( report && report[ section ] ) || {} )[ key ];
	return typeof found === 'number' ? found : null;
}

/**
 * Looks a threshold key up in the catalogue.
 *
 * @param {string} key A key from metrics.max or metrics.gate.
 * @return {{section: string, label: string}} The catalogue entry.
 * @throws {Error} When the key is not catalogued.
 */
function entryFor( key ) {
	const entry = CATALOGUE[ key ];
	if ( ! entry ) {
		throw new Error(
			`"${ key }" is in quality-thresholds.json's metrics block but has no entry in CATALOGUE (.github/scripts/metrics.js) — add one.`
		);
	}
	return entry;
}

/**
 * Checks the gated metrics against their ceilings.
 *
 * A gated metric the summary does not report is a failure, not a pass:
 * ignoring it would let a malformed summary switch the gate off.
 *
 * @param {Object}   summary A PHPMetrics summary.
 * @param {Object}   max     metrics.max, ceilings keyed by metric.
 * @param {string[]} gate    metrics.gate, the keys that fail the build.
 * @return {Array<{key: string, label: string, value: number|null, max: number, ok: boolean}>} One entry per gated key.
 */
function checkGate( summary, max, gate ) {
	return gate.map( ( key ) => {
		const { section, label } = entryFor( key );
		if ( typeof max[ key ] !== 'number' ) {
			throw new Error( `"${ key }" is in metrics.gate but has no ceiling in metrics.max.` );
		}
		const found = value( summary, section, key );
		return { key, label, value: found, max: max[ key ], ok: found !== null && found <= max[ key ] };
	} );
}

module.exports = { CATALOGUE, value, entryFor, checkGate };
