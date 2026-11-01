<?php
/**
 * Global WordPress function stand-ins that WP_Mock::userFunction() cannot express.
 *
 * Keep this file minimal: only declare a global function here when it needs real
 * behavior (control flow via an exception) rather than a per-test return value.
 * Everything else is stubbed per test with WP_Mock::userFunction().
 *
 * @package Scripts_To_Footer
 */

/**
 * The exception wp_die() throws in the unit suite.
 */
class STF_Test_Wp_Die_Exception extends Exception {

	/**
	 * The title passed to wp_die().
	 *
	 * @var mixed
	 */
	public $title;

	/**
	 * The args passed to wp_die().
	 *
	 * @var mixed
	 */
	public $args;
}

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
	// $message only carries the string a test asserts against, inside the same process.
	$e        = new STF_Test_Wp_Die_Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	$e->title = $title;
	$e->args  = $args;
	throw $e;
}
