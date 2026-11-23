<?php
/**
 * Global WordPress function stand-ins that WP_Mock::userFunction() cannot express.
 *
 * Keep this file minimal: only declare a global function here when it needs real
 * behavior (control flow via an exception) rather than a per-test return value.
 * Everything else is stubbed per test with WP_Mock::userFunction().
 *
 * The exception class lives in class-stf-test-wp-die-exception.php.
 *
 * @package Scripts_To_Footer
 */

/**
 * Wp_die() terminates the request with exit() in WordPress. Tests can't survive exit(),
 * so this throws instead, letting the exit-terminated paths in uninstall.php be asserted
 * with expectException()/expectExceptionMessage().
 *
 * @param string $message Error message.
 * @param string $title   Error title.
 * @param array  $args    Arguments.
 *
 * @throws STF_Test_Wp_Die_Exception Always, carrying $message, $title and $args.
 */
function wp_die( $message = '', $title = '', $args = array() ) {
	$exception = STF_Test_Wp_Die_Exception::from_wp_die( $message, $title, $args );
	throw $exception;
}
