<?php
/**
 * Main Plugin File
 *
 * Plugin Name: Scripts-To-Footer
 * Plugin URI: http://wordpress.org/plugins/scripts-to-footerphp/
 * Description: Move your scripts to the footer to help speed up perceived page load times and improve user experience.
 * Version: 0.7.1
 * Author: Joshua David Nelson
 * Author URI: http://joshuadnelson.com
 * License: GPL2
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/scripts-to-footer
 * GitHub Branch: master
 *
 * @package   Scripts_To_Footer
 * @author    Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright Copyright (c) 2023, Joshua David Nelson
 * @license   http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link      https://github.com/joshuadavidnelson/scripts-to-footer
 */

/**
 * Prevent direct access to this file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

// Plugin Directory.
if ( ! defined( 'STF_DIR' ) ) {
	define( 'STF_DIR', __DIR__ );
}

// Plugin URL.
if ( ! defined( 'STF_URL' ) ) {
	define( 'STF_URL', plugins_url( '/', __FILE__ ) );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-stf-activator.php
 */
function activate_scripts_to_footer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-stf-activator.php';
	STF_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-stf-deactivator.php
 */
function deactivate_scripts_to_footer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-stf-deactivator.php';
	STF_Deactivator::deactivate();
}
register_activation_hook( __FILE__, 'activate_scripts_to_footer' );
register_deactivation_hook( __FILE__, 'deactivate_scripts_to_footer' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-scripts-to-footer.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 0.7.0
 */
function run_scripts_to_footer() {
	$plugin = new Scripts_To_Footer();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_scripts_to_footer', 10, 0 );
