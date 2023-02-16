<?php
/**
 * Fired during plugin deactivation
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage STF_Deactivator
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2021, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
 */

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
