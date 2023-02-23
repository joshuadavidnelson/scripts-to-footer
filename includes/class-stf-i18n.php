<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage STF_I18n
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.7.0
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
