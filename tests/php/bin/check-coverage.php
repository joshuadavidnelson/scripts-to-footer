<?php
/**
 * Fails when line coverage in a Clover report drops below a floor.
 *
 * Usage: php tests/php/bin/check-coverage.php <clover.xml> <min-line-percent>
 *
 * A missing or unreadable report is a hard failure: skipping the check would let a
 * broken coverage step report green.
 *
 * @package Scripts_To_Footer
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script, not WordPress.

$stf_file  = $argv[1] ?? 'coverage/clover.xml';
$stf_floor = isset( $argv[2] ) ? (float) $argv[2] : 90.0;

if ( ! is_readable( $stf_file ) ) {
	fwrite( STDERR, "::error::Coverage report not found: {$stf_file}\n" );
	exit( 1 );
}

$stf_xml = simplexml_load_file( $stf_file );
if ( false === $stf_xml || ! isset( $stf_xml->project->metrics ) ) {
	fwrite( STDERR, "::error::Coverage report is not a Clover file: {$stf_file}\n" );
	exit( 1 );
}

$stf_metrics = $stf_xml->project->metrics;
$stf_total   = (int) $stf_metrics['statements'];
$stf_covered = (int) $stf_metrics['coveredstatements'];

if ( 0 === $stf_total ) {
	fwrite( STDERR, "::error::Coverage report has no measured lines.\n" );
	exit( 1 );
}

$stf_pct = round( $stf_covered / $stf_total * 100, 2 );
$stf_ok  = $stf_pct >= $stf_floor;

printf( "PHP line coverage: %.2f%% (%d/%d, floor %.2f%%) %s\n", $stf_pct, $stf_covered, $stf_total, $stf_floor, $stf_ok ? 'OK' : 'FAIL' );

if ( ! $stf_ok ) {
	fwrite( STDERR, sprintf( "::error::PHP line coverage %.2f%% is below the %.2f%% floor\n", $stf_pct, $stf_floor ) );
	exit( 1 );
}
