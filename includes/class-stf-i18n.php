<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.6.5
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer/includes
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.6.5
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer/includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */
class STF_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 0.4.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'stf',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

}
