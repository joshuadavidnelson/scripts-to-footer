<?php
/**
 * Fired during plugin activation
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer_Activator
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2023, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
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

	/**
	 * Activation hook.
	 *
	 * The primary sanity check, automatically disable the plugin on activation
	 * if it doesn't meet minimum requirements. If it does, then it does some version
	 * checks and updates the site version option.
	 *
	 * @since 0.6.0
	 * @since 0.7.0 moved into Activator class.
	 */
	public static function activation_check() {

		if ( ! self::compatible_version() ) {

			deactivate_plugins( plugin_basename( __FILE__ ) );
			// @codingStandardsIgnoreStart
			wp_die( __( 'Scripts-to-Footer requires WordPress 4.0 or higher', 'stf' ) );
			// @codingStandardsIgnoreEnd

		}

	}

	/**
	 * The backup sanity check.
	 *
	 * This is just in case the plugin is activated in a weird way,
	 * or the versions change after activation.
	 *
	 * @since 0.6.0
	 * @since 0.7.0 moved into Activator class.
	 */
	public function check_version() {

		if ( ! self::compatible_version() ) {
			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
				// @codingStandardsIgnoreStart
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
				// @codingStandardsIgnoreEnd
			}
		}

	}

	/**
	 * Display notice on deactivation.
	 *
	 * @since 0.6.0
	 * @since 0.7.0 moved into Activator class.
	 */
	public function disabled_notice() {

		echo '<strong>' . esc_html__( 'Scripts-to-Footer requires WordPress 4.0 or higher.', 'stf' ) . '</strong>';

	}

	/**
	 * Check everything is compatible.
	 *
	 * @since 0.6.0
	 * @since 0.7.0 moved into Activator class.
	 * @return bool
	 */
	public static function compatible_version() {

		if ( isset( $GLOBALS['wp_version'] )
			&& version_compare( $GLOBALS['wp_version'], '4.0', '<' ) ) {
			return false;
		}

		// Add sanity checks for other version requirements here.

		return true;

	}

}
