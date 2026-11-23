<?php
/**
 * Fired during plugin deactivation
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage STF_Deactivator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 0.7.0
 */
class STF_Deactivator {

	/**
	 * Clear the global comment cache.
	 *
	 * @since 0.7.0
	 */
	public static function deactivate() {

		flush_rewrite_rules();
	}
}
