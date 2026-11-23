'use strict';

const { test, describe } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const { checkGate } = require( './metrics' );
const { main } = require( './check-metrics' );

const MAX = { critical: 0, error: 2, avgCyclomaticComplexityByClass: 14 };
const summary = ( critical, error ) => ( { Violations: { critical, error }, Complexity: { avgCyclomaticComplexityByClass: 99 } } );

describe( 'checkGate', () => {
	test( 'passes values at or under their ceilings', () => {
		const results = checkGate( summary( 0, 2 ), MAX, [ 'critical', 'error' ] );
		assert.deepEqual( results.map( ( r ) => r.ok ), [ true, true ] );
	} );

	test( 'fails a gated value over its ceiling', () => {
		const results = checkGate( summary( 1, 2 ), MAX, [ 'critical', 'error' ] );
		assert.deepEqual( results.map( ( r ) => r.ok ), [ false, true ] );
	} );

	test( 'does not gate a metric that is not listed in gate', () => {
		assert.equal( checkGate( summary( 0, 0 ), MAX, [ 'critical' ] ).length, 1 );
	} );

	test( 'fails a gated metric the summary does not report', () => {
		const [ result ] = checkGate( {}, MAX, [ 'error' ] );
		assert.equal( result.value, null );
		assert.equal( result.ok, false );
	} );

	test( 'throws for a gate key with no catalogue entry or no ceiling', () => {
		assert.throws( () => checkGate( summary( 0, 0 ), MAX, [ 'nope' ] ), /no entry in CATALOGUE/ );
		assert.throws( () => checkGate( summary( 0, 0 ), {}, [ 'critical' ] ), /no ceiling/ );
	} );
} );

describe( 'check-metrics', () => {
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'metrics-' ) );
	const thresholds = path.join( dir, 't.json' );
	fs.writeFileSync( thresholds, JSON.stringify( { metrics: { max: MAX, gate: [ 'critical', 'error' ] } } ) );
	const script = path.join( __dirname, 'check-metrics.js' );
	const run = ( env ) => spawnSync( 'node', [ script ], { env: { ...process.env, THRESHOLDS: thresholds, ...env }, encoding: 'utf8' } );
	const write = ( name, data ) => {
		const file = path.join( dir, name );
		fs.writeFileSync( file, JSON.stringify( data ) );
		return file;
	};

	test( 'exits 0 when every gated metric is within its ceiling', () => {
		const result = run( { HEAD_SUMMARY: write( 'ok.json', summary( 0, 1 ) ) } );
		assert.equal( result.status, 0 );
		assert.match( result.stdout, /Violations: error: 1 \(max 2\) OK/ );
	} );

	test( 'exits 1 with an ::error:: annotation when a gated metric is over', () => {
		const result = run( { HEAD_SUMMARY: write( 'bad.json', summary( 0, 3 ) ) } );
		assert.equal( result.status, 1 );
		assert.match( result.stderr, /::error::Violations: error is 3 \(max 2\)/ );
	} );

	test( 'exits 1 when the summary file is missing', () => {
		const result = run( { HEAD_SUMMARY: path.join( dir, 'nope.json' ) } );
		assert.equal( result.status, 1 );
		assert.match( result.stderr, /Metrics summary not found/ );
	} );

	test( 'main takes its inputs from env and fs', () => {
		const files = { 't.json': JSON.stringify( { metrics: { max: MAX, gate: [ 'critical' ] } } ), 's.json': JSON.stringify( summary( 0, 50 ) ) };
		const stub = { existsSync: ( p ) => p in files, readFileSync: ( p ) => files[ p ] };
		assert.equal( main( { env: { THRESHOLDS: 't.json', HEAD_SUMMARY: 's.json' }, fs: stub } ), true );
	} );
} );
