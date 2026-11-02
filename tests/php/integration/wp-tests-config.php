<?php
/**
 * WordPress test configuration for the wp-phpunit based integration suite.
 *
 * Used through WP_PHPUNIT__TESTS_CONFIG when WP_TESTS_DIR is not set. Database
 * credentials and the WordPress core path come from environment variables.
 *
 * @package Scripts_To_Footer
 */

$stf_core_dir = getenv( 'WP_CORE_DIR' );
if ( false === $stf_core_dir || '' === $stf_core_dir ) {
	$stf_core_dir = '/tmp/wordpress/';
}

define( 'ABSPATH', rtrim( $stf_core_dir, '/\\' ) . '/' );

define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME', false !== getenv( 'WP_DB_NAME' ) ? getenv( 'WP_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', false !== getenv( 'WP_DB_USER' ) ? getenv( 'WP_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', false !== getenv( 'WP_DB_PASSWORD' ) ? getenv( 'WP_DB_PASSWORD' ) : '' );
define( 'DB_HOST', false !== getenv( 'WP_DB_HOST' ) ? getenv( 'WP_DB_HOST' ) : 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY', 'put your unique phrase here' );
define( 'LOGGED_IN_KEY', 'put your unique phrase here' );
define( 'NONCE_KEY', 'put your unique phrase here' );
define( 'AUTH_SALT', 'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT', 'put your unique phrase here' );
define( 'NONCE_SALT', 'put your unique phrase here' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

// Older test libraries (before WordPress 6.3) require this global; newer ones default it.
$table_prefix = 'wptests_';
