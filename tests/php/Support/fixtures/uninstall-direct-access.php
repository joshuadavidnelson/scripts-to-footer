<?php
/**
 * Subprocess script: runs uninstall.php the way a direct web request would.
 *
 * ABSPATH is defined but WP_UNINSTALL_PLUGIN is not. Every WordPress function that
 * uninstall.php could reach after the guard is replaced by a stub that prints a
 * marker, so the parent test can tell whether the guard let execution continue.
 * A shutdown function prints the HTTP response code that was set.
 *
 * Usage: php uninstall-direct-access.php /path/to/uninstall.php
 *
 * @package Scripts_To_Footer
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Stub.
 *
 * @return bool
 */
function is_user_logged_in() {
	echo "MARK:is_user_logged_in\n";
	return true;
}

/**
 * Stub.
 *
 * @return bool
 */
function current_user_can() {
	echo "MARK:current_user_can\n";
	return true;
}

/**
 * Stub.
 *
 * @return void
 */
function wp_die() {
	echo "MARK:wp_die\n";
}

/**
 * Stub.
 *
 * @return bool
 */
function is_multisite() {
	echo "MARK:is_multisite\n";
	return false;
}

/**
 * Stub.
 *
 * @return void
 */
function delete_option() {
	echo "MARK:delete_option\n";
}

/**
 * Stub.
 *
 * @return void
 */
function delete_post_meta_by_key() {
	echo "MARK:delete_post_meta_by_key\n";
}

/**
 * Stub.
 *
 * @return void
 */
function switch_to_blog() {
	echo "MARK:switch_to_blog\n";
}

/**
 * Stub.
 *
 * @return void
 */
function restore_current_blog() {
	echo "MARK:restore_current_blog\n";
}

/**
 * Stub for the translation function uninstall.php calls while building wp_die() arguments.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

register_shutdown_function(
	function () {
		echo 'CODE:' . http_response_code() . "\n";
	}
);

require $argv[1];

echo "MARK:fell_through\n";
