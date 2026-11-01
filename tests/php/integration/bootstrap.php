<?php
/**
 * PHPUnit bootstrap for the WordPress integration suite (real WordPress and MySQL).
 *
 * The WordPress test library is resolved in this order:
 * 1. WP_TESTS_DIR, as laid out by bin/install-wp-tests.sh.
 * 2. The wp-phpunit Composer package, configured by tests/php/integration/wp-tests-config.php.
 *
 * @package Scripts_To_Footer
 */

$stf_plugin_dir = dirname( __DIR__, 3 );

require $stf_plugin_dir . '/vendor/autoload.php';

// The WordPress test library needs the path to the Yoast PHPUnit Polyfills.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $stf_plugin_dir . '/vendor/yoast/phpunit-polyfills' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant required by the WordPress test library.
}

$stf_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $stf_tests_dir || '' === $stf_tests_dir ) {
	$stf_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
	if ( false === $stf_tests_dir || '' === $stf_tests_dir ) {
		$stf_tests_dir = $stf_plugin_dir . '/vendor/wp-phpunit/wp-phpunit';
	}

	// Point wp-phpunit at our config, unless the caller already did.
	if ( false === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) || '' === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
		putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Test bootstrap only.
	}
}

if ( ! file_exists( $stf_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test library at ' . $stf_tests_dir . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter().
require_once $stf_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $stf_plugin_dir ) {
		require $stf_plugin_dir . '/scripts-to-footer.php';
	}
);

// Start up the WordPress testing environment.
require $stf_tests_dir . '/includes/bootstrap.php';
