/**
 * Renders the "PR coverage report" sticky comment.
 *
 * Called by the report job in .github/workflows/integrate.yaml, after the
 * coverage job has uploaded the PR's clover report and, when the base branch
 * could be measured, the base's. Kept out of the workflow so it can be unit
 * tested; see render-pr-report.test.js. Node built-ins only.
 */

'use strict';

/**
 * Hidden marker the posting step searches for, so a re-run updates the one
 * comment instead of adding another. Changing it orphans existing comments.
 */
const MARKER = '<!-- stf-pr-coverage-report -->';

// Every metric the report can show. A metric neither side measured is left
// out rather than shown as n/a: PHP branch coverage is permanently
// unmeasured under Xdebug line coverage, and a row that can only say "no
// data" is noise.
const FIELDS = [
	[ 'lines', 'Lines' ],
	[ 'methods', 'Methods' ],
	[ 'branches', 'Branches' ],
];

/**
 * Formats a number to two decimals.
 *
 * @param {number} n The value.
 * @return {string} The rendered number.
 */
const format = ( n ) => n.toFixed( 2 );

/**
 * Formats a coverage metric as a percentage cell.
 *
 * @param {{pct: number}|null|undefined} metric The metric, or null when unmeasured.
 * @return {string} The rendered cell.
 */
const pct = ( metric ) => ( metric ? `${ format( metric.pct ) }%` : 'n/a' );

/**
 * The change between two percentages, measured on what the table shows.
 *
 * Diffing the rounded numbers keeps the Base, PR and Δ cells telling one
 * story: 89.996 and 90.004 render as 90.00 and 90.00, so Δ must be 0.
 *
 * @param {number} before Base percentage.
 * @param {number} after  PR percentage.
 * @return {number} The change, to the precision of the cells.
 */
const shownChange = ( before, after ) => Number( ( Number( format( after ) ) - Number( format( before ) ) ).toFixed( 2 ) );

/**
 * Whether a coverage report has any measured data (the no-baseline guard).
 *
 * @param {Object|null} report Coverage keyed by metric name.
 * @return {boolean} True when at least one metric was measured.
 */
const hasCoverage = ( report ) => Boolean( report ) && Object.values( report ).some( ( m ) => m !== null && m !== undefined );

/**
 * Rejects a floor for a metric this renderer would never show.
 *
 * An unknown key would otherwise render as "no floor configured", which is
 * indistinguishable from a deliberate choice not to gate it, so a typo in
 * quality-thresholds.json would silently stop reporting a floor.
 *
 * @param {Object} floors quality-thresholds.json's coverage.php.
 * @throws {Error} When a key is not a known metric.
 */
function assertKnownFloors( floors ) {
	const known = FIELDS.map( ( [ key ] ) => key );
	for ( const key of Object.keys( floors || {} ) ) {
		if ( ! known.includes( key ) ) {
			throw new Error( `render-pr-report: "php.${ key }" is not a metric this report shows (expected ${ known.join( ', ' ) }).` );
		}
	}
}

/**
 * Builds the coverage table.
 *
 * @param {Object}      input        Render input.
 * @param {Object}      input.head   Coverage of the PR branch.
 * @param {Object|null} input.base   Coverage of the base branch, or null.
 * @param {Object}      input.floors Floors keyed by metric name.
 * @return {string} Markdown, or '' when nothing was measured.
 */
function renderCoverageSection( { head, base, floors } ) {
	assertKnownFloors( floors );

	// A base with no coverage data (not measurable yet, or the step failed)
	// would otherwise render every row as a drop to 0%.
	const comparable = hasCoverage( base );

	const rows = [];
	for ( const [ key, label ] of FIELDS ) {
		const before = ( base && base[ key ] ) || null;
		const after = ( head && head[ key ] ) || null;
		if ( before === null && after === null ) {
			continue;
		}

		// No measurement or no configured floor means nothing to gate on:
		// render a dash, never a false failure.
		const floor = ( floors || {} )[ key ];
		let floorCell = '—';
		if ( after !== null && typeof floor === 'number' ) {
			floorCell = `${ format( floor ) }% ${ after.pct >= floor ? '✅' : '❌' }`;
		}

		if ( ! comparable ) {
			rows.push( `| ${ label } | ${ pct( after ) } | ${ floorCell } |` );
			continue;
		}

		let delta = '—';
		if ( before !== null && after !== null ) {
			const change = shownChange( before.pct, after.pct );
			if ( change !== 0 ) {
				delta = `${ change > 0 ? '+' : '-' }${ format( Math.abs( change ) ) }%`;
			}
		}
		rows.push( `| ${ label } | ${ pct( before ) } | ${ pct( after ) } | ${ delta } | ${ floorCell } |` );
	}

	if ( ! rows.length ) {
		return '';
	}

	return [
		'### PHP coverage',
		...( comparable ? [] : [ "ℹ️ No coverage data for the base branch. Showing the PR's numbers alone.", '' ] ),
		...( comparable
			? [ '| Metric | Base | PR | Δ | Floor |', '| --- | ---: | ---: | ---: | ---: |' ]
			: [ '| Metric | PR | Floor |', '| --- | ---: | ---: |' ] ),
		...rows,
	].join( '\n' );
}

/**
 * Assembles the full comment body, marker first.
 *
 * @param {Object} input          Render input.
 * @param {Object} input.coverage `{ head, base, floors }` for renderCoverageSection.
 * @param {string} input.headSha  Full SHA of the PR head.
 * @param {string} input.baseSha  Full SHA of the base.
 * @param {string} input.runUrl   Link to the workflow run holding the artifact.
 * @return {string} Markdown body.
 */
function renderPrReport( { coverage, headSha, baseSha, runUrl } ) {
	const section = renderCoverageSection( coverage );
	const comparable = hasCoverage( coverage.base );

	return [
		MARKER,
		'## 📊 PR coverage report',
		'',
		comparable ? `\`${ baseSha.slice( 0, 7 ) }\` (base) → \`${ headSha.slice( 0, 7 ) }\` (PR)` : `\`${ headSha.slice( 0, 7 ) }\` (PR)`,
		'',
		section || 'No coverage was measured for this run.',
		'',
		`<sub>PHPUnit line coverage via Xdebug. The clover reports are attached to [the Integrate run](${ runUrl }) as \`pr-coverage\`.</sub>`,
	].join( '\n' );
}

module.exports = { MARKER, renderCoverageSection, renderPrReport };

/* CLI wiring: reads files named by the environment and writes the markdown. */
if ( require.main === module ) {
	const fs = require( 'fs' );
	const { parseClover } = require( './coverage' );

	const {
		PHP_CLOVER = 'pr-coverage/clover.xml',
		PHP_BASE_CLOVER = 'pr-coverage/base-clover.xml',
		THRESHOLDS = 'quality-thresholds.json',
		OUTPUT = 'pr-coverage/pr-report.md',
		HEAD_SHA,
		BASE_SHA,
		RUN_URL,
	} = process.env;

	for ( const [ name, set ] of Object.entries( { HEAD_SHA, BASE_SHA, RUN_URL } ) ) {
		if ( ! set ) {
			console.error( `::error::${ name } is not set` );
			process.exit( 1 );
		}
	}

	// The PR report is required; the base is optional and degrades the
	// table to PR-only when absent.
	const read = ( file ) => parseClover( fs.readFileSync( file, 'utf8' ) );
	const readBase = ( file ) => {
		if ( ! fs.existsSync( file ) ) {
			return null;
		}
		try {
			return read( file );
		} catch ( error ) {
			console.warn( `::warning::Ignoring unreadable base coverage: ${ error.message }` );
			return null;
		}
	};
	const thresholds = JSON.parse( fs.readFileSync( THRESHOLDS, 'utf8' ) );

	fs.writeFileSync(
		OUTPUT,
		renderPrReport( {
			coverage: {
				head: read( PHP_CLOVER ),
				base: readBase( PHP_BASE_CLOVER ),
				floors: thresholds.coverage.php,
			},
			headSha: HEAD_SHA,
			baseSha: BASE_SHA,
			runUrl: RUN_URL,
		} )
	);
}
