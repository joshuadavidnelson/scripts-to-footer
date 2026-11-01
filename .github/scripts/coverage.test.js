'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );

const { toMetric, parseClover } = require( './coverage' );

// File- and class-level <metrics> precede the project total, as in real
// PHPUnit output; only the project one carries a files attribute.
const CLOVER = `<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1">
    <file name="/a.php">
      <class name="A" namespace="global"><metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="0" elements="3" coveredelements="0"/></class>
      <metrics loc="9" ncloc="9" classes="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="0" elements="3" coveredelements="0"/>
    </file>
    <metrics files="1" loc="9" ncloc="9" classes="1" methods="4" coveredmethods="3" conditionals="0" coveredconditionals="0" statements="200" coveredstatements="185" elements="204" coveredelements="188"/>
  </project>
</coverage>`;

test( 'toMetric returns null when nothing was measured', () => {
	assert.equal( toMetric( 0, 0 ), null );
	assert.equal( toMetric( undefined, undefined ), null );
} );

test( 'toMetric rounds the percentage to two decimals', () => {
	assert.deepEqual( toMetric( 2, 3 ), { covered: 2, total: 3, pct: 66.67 } );
	assert.equal( toMetric( 0, 5 ).pct, 0 );
} );

test( 'parseClover reads the project total, not file or class metrics', () => {
	const result = parseClover( CLOVER );

	assert.deepEqual( result.lines, { covered: 185, total: 200, pct: 92.5 } );
	assert.deepEqual( result.methods, { covered: 3, total: 4, pct: 75 } );
} );

test( 'parseClover reports unmeasured branches as null', () => {
	assert.equal( parseClover( CLOVER ).branches, null );
} );

test( 'parseClover throws on input that is not a clover report', () => {
	assert.throws( () => parseClover( '<html></html>' ), /no <project>/ );
	assert.throws( () => parseClover( '<project><file><metrics statements="1"/></file></project>' ), /no project-level/ );
} );
