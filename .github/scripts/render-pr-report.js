/**
 * Renders the "PR Report" sticky comment: PHP code quality, code metric
 * deltas and coverage deltas in one comment.
 *
 * Called by the report job in .github/workflows/ci.yml, which downloads the
 * report-* artifacts the Quality and Tests workflows upload. Each section is
 * left out when its artifact is absent. Kept out of the workflow so it can
 * be unit tested — see render-pr-report.test.js.
 */

'use strict';

const { CATALOGUE, value, entryFor } = require( './metrics' );

// Every field the PHP suite can report. A field neither side measured is
// left out of the table rather than shown as `n/a`: PHP branch coverage is
// permanently unmeasured (Xdebug does not populate clover's conditionals),
// and a row that can only ever say "no data" is noise.
const COVERAGE_SUITES = [ [ 'php', 'PHP', [ [ 'lines', 'Lines' ], [ 'methods', 'Methods' ], [ 'branches', 'Branches' ] ] ] ];

// The tools in the PHP code quality table, in display order, keyed by their
// property in the report-quality artifact.
const QUALITY_CHECKS = [
	[ 'phpcs', 'PHPCS' ],
	[ 'phpstan', 'PHPStan' ],
	[ 'phpmd', 'PHPMD' ],
];

const OUTCOME_CELLS = {
	success: '✅',
	failure: '❌',
	skipped: '⏭️ Skipped',
	cancelled: '🚫 Cancelled',
};

/**
 * Formats a number for the table: integers bare, everything else to 2dp.
 *
 * @param {number|null} n The value.
 * @return {string} The rendered cell.
 */
function format( n ) {
	if ( n === null || n === undefined ) {
		return 'n/a';
	}
	return Number.isInteger( n ) ? String( n ) : n.toFixed( 2 );
}

/**
 * The change between two values, measured on what the table actually shows.
 *
 * Rounding the raw values and rounding the difference are not the same
 * operation, so computing the delta from the inputs lets it contradict the
 * cells beside it. Diffing the displayed numbers keeps the columns telling
 * one story.
 *
 * @param {number} before Base value.
 * @param {number} after  PR value.
 * @return {number} The change, to the same precision as the cells.
 */
function shownChange( before, after ) {
	return Number( ( Number( format( after ) ) - Number( format( before ) ) ).toFixed( 2 ) );
}

/**
 * Formats a coverage Metric as a percentage cell.
 *
 * @param {{pct: number}|null} metric The coverage metric, or null if unmeasured.
 * @return {string} The rendered cell.
 */
function pct( metric ) {
	return metric === null || metric === undefined ? 'n/a' : `${ format( metric.pct ) }%`;
}

/**
 * Whether the base summary can be diffed against.
 *
 * Every metric PHPMetrics summarises derives from classes, so a base with
 * none reports 0 across the board. Diffing against that would render the
 * whole table as a regression from zero.
 *
 * @param {Object} base A PHPMetrics summary for the base branch.
 * @return {boolean} True when the base declares classes to measure.
 */
function hasMetricsBaseline( base ) {
	return value( base, 'OOP', 'classes' ) > 0;
}

/**
 * Whether a coverage report has any real data.
 *
 * @param {Object} report A `{ php }` coverage report.
 * @return {boolean} True when at least one metric was measured.
 */
function hasCoverage( report ) {
	if ( ! report ) {
		return false;
	}
	return Object.values( report.php || {} ).some( ( m ) => m !== null && m !== undefined );
}

/**
 * Builds the PHP code quality section: one pass/fail row per tool.
 *
 * @param {Object} quality The report-quality artifact: `{ phpcs: { outcome }, ... }`.
 * @return {string} Markdown for the section.
 */
function renderQualitySection( quality ) {
	const rows = QUALITY_CHECKS.map( ( [ key, label ] ) => {
		const outcome = quality && quality[ key ] && quality[ key ].outcome;
		return `| ${ label } | ${ OUTCOME_CELLS[ outcome ] || '—' } |`;
	} );

	return [ '### PHP code quality', '| Check | Result |', '| --- | --- |', ...rows ].join( '\n' );
}

/**
 * Builds the Code Metrics section: PHPMetrics values against their
 * configured ceilings.
 *
 * @param {Object} input      Render input.
 * @param {Object} input.head PHPMetrics summary for the PR branch.
 * @param {Object} input.base PHPMetrics summary for the base branch.
 * @param {Object} input.max  quality-thresholds.json's `metrics.max`.
 * @return {string} Markdown for the section, including its own headline.
 */
function renderMetricsSection( { head, base, max } ) {
	const comparable = hasMetricsBaseline( base );

	const rows = [];
	let regressions = 0;
	let breaches = 0;

	for ( const key of Object.keys( max ) ) {
		const { section, label } = entryFor( key );
		const ceiling = max[ key ];

		const before = value( base, section, key );
		const after = value( head, section, key );
		if ( before === null && after === null ) {
			continue;
		}

		// Distinct from the Δ column: a metric can regress against the base
		// while still sitting under its ceiling, and both need to show.
		const breached = after !== null && after > ceiling;
		if ( breached ) {
			breaches++;
		}
		const maxCell = after === null ? format( ceiling ) : `${ format( ceiling ) } ${ breached ? '❌' : '✅' }`;

		if ( ! comparable ) {
			rows.push( `| ${ label } | ${ format( after ) } | ${ maxCell } |` );
			continue;
		}

		let delta = '—';
		if ( before !== null && after !== null ) {
			const change = shownChange( before, after );
			if ( change !== 0 ) {
				// Every thresholded metric is a ceiling, so worse means higher.
				const worse = change > 0;
				if ( worse ) {
					regressions++;
				}
				delta = `${ worse ? '⚠️ ' : '' }${ change > 0 ? '+' : '-' }${ format( Math.abs( change ) ) }`;
			}
		}
		rows.push( `| ${ label } | ${ format( before ) } | ${ format( after ) } | ${ delta } | ${ maxCell } |` );
	}

	const icon = breaches > 0 ? '❌' : comparable ? ( regressions > 0 ? '⚠️' : '✅' ) : 'ℹ️';
	const baselineSentence = comparable
		? regressions === 0
			? 'No metric moved in the wrong direction.'
			: `${ regressions } metric${ regressions === 1 ? '' : 's' } moved in the wrong direction.`
		: 'No class-based baseline to diff against (a push, or a base with no classes). Showing this branch on its own.';
	const thresholdSentence =
		breaches === 0 ? 'All within threshold.' : `${ breaches } metric${ breaches === 1 ? '' : 's' } breached threshold.`;

	return [
		'### Code Metrics',
		`${ icon } ${ baselineSentence } ${ thresholdSentence }`,
		'',
		...( comparable
			? [ '| Metric | Base | PR | Δ | Max |', '| --- | ---: | ---: | ---: | ---: |' ]
			: [ '| Metric | PR | Max |', '| --- | ---: | ---: |' ] ),
		...rows,
	].join( '\n' );
}

/**
 * Rejects a floor this renderer would not apply to anything.
 *
 * An unrecognised suite or metric key renders as "no floor configured",
 * which is indistinguishable from a deliberate decision not to gate it, so a
 * typo in quality-thresholds.json fails loudly instead.
 *
 * @param {Object} floors quality-thresholds.json's `coverage`.
 * @throws {Error} When a suite or metric key is not one this renderer knows.
 */
function assertKnownFloors( floors ) {
	for ( const [ suiteKey, suiteFloors ] of Object.entries( floors || {} ) ) {
		const suite = COVERAGE_SUITES.find( ( [ key ] ) => key === suiteKey );
		if ( ! suite ) {
			throw new Error(
				`render-pr-report: "${ suiteKey }" is not a coverage suite (expected ${ COVERAGE_SUITES.map( ( [ key ] ) => key ).join( ', ' ) }).`
			);
		}

		const known = suite[ 2 ].map( ( [ key ] ) => key );
		for ( const metricKey of Object.keys( suiteFloors || {} ) ) {
			if ( ! known.includes( metricKey ) ) {
				throw new Error(
					`render-pr-report: "${ suiteKey }.${ metricKey }" is not a metric this suite reports (expected ${ known.join( ', ' ) }).`
				);
			}
		}
	}
}

/**
 * Builds the Coverage section: coverage against its configured floor.
 *
 * @param {Object} input        Render input.
 * @param {Object} input.head   `{ php }` coverage for the PR branch.
 * @param {Object} input.base   `{ php }` coverage for the base branch.
 * @param {Object} input.floors quality-thresholds.json's `coverage`.
 * @return {string} Markdown for the section, or '' when nothing was measured.
 */
function renderCoverageSection( { head, base, floors } ) {
	assertKnownFloors( floors );

	// A base with no coverage data at all would otherwise render every row
	// as a drop to 0%.
	const comparable = hasCoverage( base );

	const rows = [];
	for ( const [ suiteKey, , fields ] of COVERAGE_SUITES ) {
		const suiteFloors = ( floors && floors[ suiteKey ] ) || {};

		for ( const [ metricKey, metricLabel ] of fields ) {
			const before = ( ( base && base[ suiteKey ] ) || {} )[ metricKey ] ?? null;
			const after = ( ( head && head[ suiteKey ] ) || {} )[ metricKey ] ?? null;
			const floor = suiteFloors[ metricKey ];

			if ( before === null && after === null ) {
				continue;
			}

			// An unmeasured metric has nothing to gate on, and a metric with
			// no configured floor isn't gated either — both render as "—".
			let floorCell = '—';
			if ( after !== null && typeof floor === 'number' ) {
				floorCell = `${ format( floor ) }% ${ after.pct >= floor ? '✅' : '❌' }`;
			}

			if ( ! comparable ) {
				rows.push( `| ${ metricLabel } | ${ pct( after ) } | ${ floorCell } |` );
				continue;
			}

			let delta = '—';
			if ( before !== null && after !== null ) {
				const change = shownChange( before.pct, after.pct );
				if ( change !== 0 ) {
					delta = `${ change > 0 ? '+' : '-' }${ format( Math.abs( change ) ) }%`;
				}
			}
			rows.push( `| ${ metricLabel } | ${ pct( before ) } | ${ pct( after ) } | ${ delta } | ${ floorCell } |` );
		}
	}

	if ( ! rows.length ) {
		return '';
	}

	return [
		'### Coverage',
		...( comparable ? [] : [ "ℹ️ No coverage data for the base branch. Showing the PR's numbers alone.", '' ] ),
		...( comparable
			? [ '| Metric | Base | PR | Δ | Floor |', '| --- | ---: | ---: | ---: | ---: |' ]
			: [ '| Metric | PR | Floor |', '| --- | ---: | ---: |' ] ),
		...rows,
	].join( '\n' );
}

/**
 * Assembles the full PR Report comment from whichever sections have data.
 *
 * @param {Object}      input               Render input.
 * @param {Object|null} input.quality       The report-quality artifact, or null to omit the section.
 * @param {Object|null} input.metrics       `{ head, base, max }` for renderMetricsSection, or null to omit the section.
 * @param {Object|null} input.coverage      `{ head, base, floors }` for renderCoverageSection, or null to omit the section.
 * @param {string}      input.headSha       Full SHA of the PR head.
 * @param {string}      input.baseSha       Full SHA of the base.
 * @param {string}      input.runUrl        Link to the workflow run holding the report artifacts.
 * @param {string}      [input.qualityResult] Result of the Quality workflow job (success, failure, ...).
 * @param {string}      [input.testsResult]   Result of the Tests workflow job.
 * @return {string} Markdown body.
 */
function renderPrReport( { quality, metrics, coverage, headSha, baseSha, runUrl, qualityResult, testsResult } ) {
	const shortHead = headSha.slice( 0, 7 );
	const shortBase = baseSha.slice( 0, 7 );

	const sections = [];
	if ( quality ) {
		sections.push( renderQualitySection( quality ), '' );
	}
	if ( metrics ) {
		sections.push( renderMetricsSection( metrics ), '' );
	}
	if ( coverage ) {
		const section = renderCoverageSection( coverage );
		if ( section ) {
			sections.push( section, '' );
		}
	}

	const notes = [];
	if ( testsResult === 'skipped' && qualityResult === 'failure' ) {
		notes.push( '> ⏭️ Tests skipped because Quality failed.', '' );
	}

	return [
		'## 📊 PR Report',
		'',
		`\`${ shortBase }\` (base) → \`${ shortHead }\` (PR)`,
		'',
		...notes,
		...sections,
		`<sub>Report data is attached to [the CI run](${ runUrl }) as \`report-*\` artifacts.</sub>`,
	].join( '\n' );
}

module.exports = {
	CATALOGUE,
	renderQualitySection,
	renderMetricsSection,
	renderCoverageSection,
	renderPrReport,
};

/* istanbul ignore next -- CLI wiring, exercised by the CLI test. */
if ( require.main === module ) {
	const fs = require( 'fs' );
	const path = require( 'path' );

	const {
		REPORTS_DIR = 'reports',
		THRESHOLDS = 'quality-thresholds.json',
		OUTPUT = path.join( REPORTS_DIR, 'pr-report.md' ),
		HEAD_SHA,
		BASE_SHA,
		RUN_URL,
		QUALITY_RESULT,
		TESTS_RESULT,
	} = process.env;

	for ( const [ name, set ] of Object.entries( { HEAD_SHA, BASE_SHA, RUN_URL } ) ) {
		if ( ! set ) {
			console.error( `::error::${ name } is not set` );
			process.exit( 1 );
		}
	}

	// download-artifact puts each report-* artifact in its own subdirectory
	// of REPORTS_DIR. A file directly in REPORTS_DIR works too, for local runs.
	const find = ( artifact, file ) =>
		[ path.join( REPORTS_DIR, artifact, file ), path.join( REPORTS_DIR, file ) ].find( ( p ) => fs.existsSync( p ) );
	const readJsonIfExists = ( artifact, file ) => {
		const found = find( artifact, file );
		return found ? JSON.parse( fs.readFileSync( found, 'utf8' ) ) : null;
	};

	const thresholds = JSON.parse( fs.readFileSync( THRESHOLDS, 'utf8' ) );

	const quality = readJsonIfExists( 'report-quality', 'quality.json' );

	const headSummary = readJsonIfExists( 'report-metrics', 'metrics-summary.json' );
	const baseSummary = readJsonIfExists( 'report-metrics', 'base-summary.json' );
	const metrics = headSummary ? { head: headSummary, base: baseSummary || {}, max: thresholds.metrics.max } : null;

	// Absent until the Tests workflow uploads report-coverage.
	let coverage = null;
	const headClover = find( 'report-coverage', 'php-clover.xml' );
	if ( headClover ) {
		const { parseClover } = require( './coverage' );
		const baseClover = find( 'report-coverage', 'php-base-clover.xml' );
		coverage = {
			head: { php: parseClover( fs.readFileSync( headClover, 'utf8' ) ) },
			base: { php: baseClover ? parseClover( fs.readFileSync( baseClover, 'utf8' ) ) : {} },
			floors: thresholds.coverage,
		};
	}

	fs.writeFileSync(
		OUTPUT,
		renderPrReport( {
			quality,
			metrics,
			coverage,
			headSha: HEAD_SHA,
			baseSha: BASE_SHA,
			runUrl: RUN_URL,
			qualityResult: QUALITY_RESULT,
			testsResult: TESTS_RESULT,
		} )
	);
}
