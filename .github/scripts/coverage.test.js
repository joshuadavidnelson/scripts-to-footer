'use strict';

const { test, describe } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { execFileSync, spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const { parseClover, checkFloors } = require( './coverage' );
const { main } = require( './check-coverage' );

const clover = ( project, extra = '' ) =>
	`<?xml version="1.0"?><coverage><project timestamp="1">${ extra }<metrics ${ project }/></project></coverage>`;

const PROJECT = 'files="3" loc="100" ncloc="90" classes="2" methods="10" coveredmethods="9" statements="100" coveredstatements="95" conditionals="0" coveredconditionals="0" elements="110" coveredelements="104"';

describe( 'parseClover', () => {
	test( 'parses project-level lines and methods, and leaves unmeasured branches null', () => {
		const result = parseClover( clover( PROJECT ) );
		assert.deepEqual( result.lines, { covered: 95, total: 100, pct: 95 } );
		assert.deepEqual( result.methods, { covered: 9, total: 10, pct: 90 } );
		assert.equal( result.branches, null );
	} );

	test( 'does not mistake a file-level <metrics> that precedes the project total', () => {
		const file = '<file name="a.php"><metrics loc="5" methods="1" coveredmethods="0" statements="5" coveredstatements="0"/></file>';
		const result = parseClover( clover( PROJECT, file ) );
		assert.equal( result.lines.pct, 95 );
	} );

	test( 'rounds to two decimal places', () => {
		const result = parseClover( clover( 'files="1" statements="3" coveredstatements="2"' ) );
		assert.equal( result.lines.pct, 66.67 );
	} );

	test( 'throws when there is no <project> element', () => {
		assert.throws( () => parseClover( '<coverage/>' ), /no <project>/ );
	} );

	test( 'throws when the project has no metrics element', () => {
		assert.throws( () => parseClover( '<coverage><project></project></coverage>' ), /no project-level/ );
	} );
} );

describe( 'checkFloors', () => {
	const actual = { lines: { pct: 90 }, methods: { pct: 80 }, branches: null };

	test( 'passes a metric on or above its floor and fails one below', () => {
		const [ lines, methods ] = checkFloors( actual, { lines: 90, methods: 85 } );
		assert.equal( lines.ok, true );
		assert.equal( methods.ok, false );
	} );

	test( 'treats an unmeasured metric as a pass', () => {
		assert.deepEqual( checkFloors( actual, { branches: 50 } ), [ { metric: 'branches', pct: null, floor: 50, ok: true } ] );
	} );

	test( 'throws for a floor key that is not a coverage metric', () => {
		assert.throws( () => checkFloors( actual, { lnes: 90 } ), /not a known coverage metric/ );
	} );
} );

describe( 'check-coverage main', () => {
	const files = ( cloverXml ) => ( {
		existsSync: ( p ) => p !== 'missing.xml' && ( p === 'thresholds.json' || cloverXml !== null ),
		readFileSync: ( p ) => ( p === 'thresholds.json' ? JSON.stringify( { coverage: { php: { lines: 90, methods: 90 } } } ) : cloverXml ),
	} );
	const quiet = ( fn ) => {
		const { log, error } = console;
		console.log = console.error = () => {};
		try {
			return fn();
		} finally {
			console.log = log;
			console.error = error;
		}
	};
	const env = { PHP_CLOVER: 'c.xml', THRESHOLDS: 'thresholds.json' };

	test( 'passes above the floors', () => {
		assert.equal( quiet( () => main( { env, fs: files( clover( PROJECT ) ) } ) ), true );
	} );

	test( 'fails below a floor', () => {
		const low = clover( 'files="1" statements="100" coveredstatements="50" methods="10" coveredmethods="10"' );
		assert.equal( quiet( () => main( { env, fs: files( low ) } ) ), false );
	} );

	test( 'fails when the clover file is missing', () => {
		assert.equal( quiet( () => main( { env: { ...env, PHP_CLOVER: 'missing.xml' }, fs: files( null ) } ) ), false );
	} );
} );

describe( 'check-coverage CLI', () => {
	const script = path.join( __dirname, 'check-coverage.js' );
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'cov-' ) );
	const thresholds = path.join( dir, 't.json' );
	fs.writeFileSync( thresholds, JSON.stringify( { coverage: { php: { lines: 90 } } } ) );

	test( 'exits 0 and prints a line per metric when above the floor', () => {
		const file = path.join( dir, 'ok.xml' );
		fs.writeFileSync( file, clover( PROJECT ) );
		const out = execFileSync( 'node', [ script ], { env: { ...process.env, PHP_CLOVER: file, THRESHOLDS: thresholds }, encoding: 'utf8' } );
		assert.match( out, /PHP lines: 95\.00% \(floor 90%\) OK/ );
	} );

	test( 'exits non-zero with an ::error:: annotation below the floor', () => {
		const file = path.join( dir, 'low.xml' );
		fs.writeFileSync( file, clover( 'files="1" statements="100" coveredstatements="10"' ) );
		const result = spawnSync( 'node', [ script ], { env: { ...process.env, PHP_CLOVER: file, THRESHOLDS: thresholds }, encoding: 'utf8' } );
		assert.equal( result.status, 1 );
		assert.match( result.stderr, /::error::PHP lines is 10%/ );
	} );
} );
