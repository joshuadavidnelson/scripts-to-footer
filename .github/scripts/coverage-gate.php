<?php
/**
 * Coverage gate for CI: fails when PHP line or method coverage in a Clover
 * report is below the floors in quality-thresholds.json.
 *
 * Usage: php .github/scripts/coverage-gate.php [clover.xml] [thresholds.json] [report.md]
 *
 * Defaults: coverage/php-clover.xml, quality-thresholds.json, coverage/report.md.
 * Also appends the Markdown report to $GITHUB_STEP_SUMMARY when that is set.
 * A missing or malformed input is a hard failure, so a broken coverage step
 * cannot report green.
 *
 * @package Scripts_To_Footer
 */

/**
 * Prints a line to a stream.
 *
 * @param string $stream Stream URI.
 * @param string $text   Text to write.
 */
function stf_gate_write( $stream, $text ) {
	$handle = new SplFileObject( $stream, 'a' );
	$handle->fwrite( $text );
}

/**
 * Reports a failure and stops.
 *
 * @param string $message Error message.
 */
function stf_gate_fail( $message ) {
	stf_gate_write( 'php://stderr', '::error::' . $message . "\n" );
	exit( 1 );
}

/**
 * Reads a file or fails.
 *
 * @param string $path File path.
 * @return string File contents.
 */
function stf_gate_read( $path ) {
	if ( ! is_readable( $path ) ) {
		stf_gate_fail( 'Cannot read ' . $path );
	}
	$file = new SplFileObject( $path, 'r' );
	return (string) $file->fread( max( 1, (int) $file->getSize() ) );
}

$stf_clover_path = isset( $argv[1] ) ? $argv[1] : 'coverage/php-clover.xml';
$stf_floors_path = isset( $argv[2] ) ? $argv[2] : 'quality-thresholds.json';
$stf_report_path = isset( $argv[3] ) ? $argv[3] : 'coverage/report.md';

$stf_thresholds = json_decode( stf_gate_read( $stf_floors_path ), true );
$stf_floors     = array();
foreach ( array( 'lines', 'methods' ) as $stf_key ) {
	if ( ! isset( $stf_thresholds['coverage']['php'][ $stf_key ] ) || ! is_numeric( $stf_thresholds['coverage']['php'][ $stf_key ] ) ) {
		stf_gate_fail( 'coverage.php.' . $stf_key . ' is missing from ' . $stf_floors_path );
	}
	$stf_floors[ $stf_key ] = (float) $stf_thresholds['coverage']['php'][ $stf_key ];
}

$stf_xml = simplexml_load_string( stf_gate_read( $stf_clover_path ) );
if ( false === $stf_xml || ! isset( $stf_xml->project->metrics ) ) {
	stf_gate_fail( 'Not a Clover report: ' . $stf_clover_path );
}

$stf_metrics = $stf_xml->project->metrics;
$stf_actual  = array(
	'lines'   => array( (int) $stf_metrics['coveredstatements'], (int) $stf_metrics['statements'] ),
	'methods' => array( (int) $stf_metrics['coveredmethods'], (int) $stf_metrics['methods'] ),
);

$stf_failed = false;
$stf_rows   = array();
foreach ( $stf_actual as $stf_key => $stf_pair ) {
	if ( 0 === $stf_pair[1] ) {
		stf_gate_fail( 'Clover report has no measured ' . $stf_key . '.' );
	}
	$stf_pct = round( $stf_pair[0] / $stf_pair[1] * 100, 2 );
	$stf_ok  = $stf_pct >= $stf_floors[ $stf_key ];
	if ( ! $stf_ok ) {
		$stf_failed = true;
		stf_gate_write( 'php://stderr', sprintf( "::error::PHP %s coverage %.2f%% is below the %.2f%% floor\n", $stf_key, $stf_pct, $stf_floors[ $stf_key ] ) );
	}
	$stf_rows[] = sprintf( '| %s | %.2f%% (%d/%d) | %.2f%% | %s |', ucfirst( $stf_key ), $stf_pct, $stf_pair[0], $stf_pair[1], $stf_floors[ $stf_key ], $stf_ok ? 'Pass' : 'Fail' );
}

$stf_markdown = "## PHP coverage\n\n| Metric | Covered | Floor | Status |\n| --- | --- | --- | --- |\n" . implode( "\n", $stf_rows ) . "\n\nFloors come from `quality-thresholds.json`.\n";

stf_gate_write( 'php://stdout', $stf_markdown );

if ( is_dir( dirname( $stf_report_path ) ) ) {
	$stf_report = new SplFileObject( $stf_report_path, 'w' );
	$stf_report->fwrite( $stf_markdown );
}

$stf_summary = getenv( 'GITHUB_STEP_SUMMARY' );
if ( is_string( $stf_summary ) && '' !== $stf_summary ) {
	stf_gate_write( $stf_summary, $stf_markdown );
}

exit( $stf_failed ? 1 : 0 );
