<?php
/**
 * Fired during plugin activation
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer_Activator
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 0.7.0
 */
class STF_Activator {

	/**
	 * Clear the global comment count cache.
	 *
	 * @since 0.7.0
	 */
	public static function activate() {

		flush_rewrite_rules();
	}
}
