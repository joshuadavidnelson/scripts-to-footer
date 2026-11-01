'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const { MARKER, renderCoverageSection, renderPrReport } = require( './render-pr-report' );

const SCRIPT = path.resolve( __dirname, 'render-pr-report.js' );
const HEAD_SHA = 'aaaaaaa1111111111111111111111111111111111';
const BASE_SHA = 'bbbbbbb2222222222222222222222222222222222';
const RUN_URL = 'https://github.com/o/r/actions/runs/1';
const FLOORS = { lines: 90 };

const metric = ( covered, total, pct ) => ( { covered, total, pct } );
const row = ( body, label ) => body.split( '\n' ).find( ( line ) => line.startsWith( `| ${ label } |` ) );

test( 'renders a five-column table against a comparable base', () => {
	const body = renderCoverageSection( {
		head: { lines: metric( 93, 100, 93 ), methods: metric( 8, 10, 80 ), branches: null },
		base: { lines: metric( 91, 100, 91 ), methods: metric( 8, 10, 80 ), branches: null },
		floors: FLOORS,
	} );

	assert.match( body, /### PHP coverage/ );
	assert.match( body, /\| Metric \| Base \| PR \| Δ \| Floor \|/ );
	assert.equal( row( body, 'Lines' ), '| Lines | 91.00% | 93.00% | +2.00% | 90.00% ✅ |' );
	assert.equal( row( body, 'Methods' ), '| Methods | 80.00% | 80.00% | — | — |' );
} );

test( 'marks a floor breach and a drop', () => {
	const body = renderCoverageSection( {
		head: { lines: metric( 89, 100, 89 ) },
		base: { lines: metric( 92, 100, 92 ) },
		floors: FLOORS,
	} );

	assert.equal( row( body, 'Lines' ), '| Lines | 92.00% | 89.00% | -3.00% | 90.00% ❌ |' );
} );

test( 'omits metrics neither side measured', () => {
	const body = renderCoverageSection( { head: { lines: metric( 1, 1, 100 ), branches: null }, base: null, floors: FLOORS } );

	assert.equal( row( body, 'Branches' ), undefined );
	assert.equal( row( body, 'Methods' ), undefined );
} );

test( 'falls back to a PR-only table when the base has no data', () => {
	const input = { head: { lines: metric( 95, 100, 95 ) }, floors: FLOORS };

	for ( const base of [ null, {}, { lines: null, methods: null, branches: null } ] ) {
		const body = renderCoverageSection( { ...input, base } );

		assert.match( body, /No coverage data for the base branch/ );
		assert.match( body, /\| Metric \| PR \| Floor \|/ );
		assert.equal( row( body, 'Lines' ), '| Lines | 95.00% | 90.00% ✅ |' );
	}
} );

test( 'computes the delta from the displayed values', () => {
	const body = renderCoverageSection( {
		head: { lines: metric( 1, 1, 90.004 ) },
		base: { lines: metric( 1, 1, 89.996 ) },
		floors: FLOORS,
	} );

	assert.equal( row( body, 'Lines' ), '| Lines | 90.00% | 90.00% | — | 90.00% ✅ |' );
} );

test( 'treats a metric without a floor as ungated', () => {
	const body = renderCoverageSection( { head: { methods: metric( 0, 4, 0 ) }, base: null, floors: FLOORS } );

	assert.equal( row( body, 'Methods' ), '| Methods | 0.00% | — |' );
} );

test( 'rejects a floor for a metric the report does not show', () => {
	assert.throws( () => renderCoverageSection( { head: {}, base: null, floors: { statements: 90 } } ), /php\.statements/ );
} );

test( 'returns an empty section when nothing was measured', () => {
	assert.equal( renderCoverageSection( { head: {}, base: null, floors: FLOORS } ), '' );
} );

test( 'renderPrReport starts with the marker and ends with the footer', () => {
	const coverage = { head: { lines: metric( 9, 10, 90 ) }, base: { lines: metric( 8, 10, 80 ) }, floors: FLOORS };
	const body = renderPrReport( { coverage, headSha: HEAD_SHA, baseSha: BASE_SHA, runUrl: RUN_URL } );
	const lines = body.split( '\n' );

	assert.equal( lines[ 0 ], MARKER );
	assert.ok( lines.includes( '`bbbbbbb` (base) → `aaaaaaa` (PR)' ) );
	assert.match( lines.at( -1 ), /^<sub>.*\]\(https:\/\/github\.com\/o\/r\/actions\/runs\/1\).*<\/sub>$/ );
} );

test( 'renderPrReport shows only the PR sha when there is no baseline', () => {
	const coverage = { head: { lines: metric( 9, 10, 90 ) }, base: null, floors: FLOORS };
	const body = renderPrReport( { coverage, headSha: HEAD_SHA, baseSha: BASE_SHA, runUrl: RUN_URL } );

	assert.ok( body.includes( '`aaaaaaa` (PR)' ) );
	assert.ok( ! body.includes( '(base)' ) );
} );

test( 'renderPrReport says so when nothing was measured', () => {
	const body = renderPrReport( { coverage: { head: {}, base: null, floors: FLOORS }, headSha: HEAD_SHA, baseSha: BASE_SHA, runUrl: RUN_URL } );

	assert.match( body, /No coverage was measured/ );
} );

/**
 * Runs the CLI against a temp dir, as the workflow does.
 *
 * @param {Object} files Map of file name to contents, written before the run.
 * @param {Object} env   Extra environment variables.
 * @return {{dir: string, output: string}} The temp dir and the markdown written.
 */
function runCli( files, env = {} ) {
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'stf-report-' ) );
	for ( const [ name, contents ] of Object.entries( files ) ) {
		fs.writeFileSync( path.join( dir, name ), contents );
	}
	const cloverFor = ( covered ) =>
		`<coverage><project><metrics files="1" methods="2" coveredmethods="2" statements="100" coveredstatements="${ covered }"/></project></coverage>`;
	fs.writeFileSync( path.join( dir, 'thresholds.json' ), JSON.stringify( { coverage: { php: FLOORS } } ) );
	fs.writeFileSync( path.join( dir, 'clover.xml' ), cloverFor( 95 ) );

	execFileSync( process.execPath, [ SCRIPT ], {
		env: {
			PATH: process.env.PATH,
			PHP_CLOVER: path.join( dir, 'clover.xml' ),
			PHP_BASE_CLOVER: path.join( dir, 'base-clover.xml' ),
			THRESHOLDS: path.join( dir, 'thresholds.json' ),
			OUTPUT: path.join( dir, 'out.md' ),
			HEAD_SHA,
			BASE_SHA,
			RUN_URL,
			...env,
		},
		stdio: 'pipe',
	} );
	return { dir, output: fs.readFileSync( path.join( dir, 'out.md' ), 'utf8' ), cloverFor };
}

test( 'CLI renders PR-only when no base report exists', () => {
	const { output } = runCli( {} );

	assert.ok( output.startsWith( MARKER ) );
	assert.match( output, /No coverage data for the base branch/ );
	assert.match( output, /\| Lines \| 95\.00% \| 90\.00% ✅ \|/ );
} );

test( 'CLI diffs against the base report when present', () => {
	const base = '<coverage><project><metrics files="1" methods="2" coveredmethods="2" statements="100" coveredstatements="92"/></project></coverage>';
	const { output } = runCli( { 'base-clover.xml': base } );

	assert.match( output, /\| Lines \| 92\.00% \| 95\.00% \| \+3\.00% \| 90\.00% ✅ \|/ );
} );

test( 'CLI ignores an unreadable base report', () => {
	const { output } = runCli( { 'base-clover.xml': 'not xml' } );

	assert.match( output, /No coverage data for the base branch/ );
} );

test( 'CLI fails when a required variable is missing', () => {
	assert.throws( () => runCli( {}, { HEAD_SHA: '' } ) );
} );
