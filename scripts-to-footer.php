<?php
/**
 * Main Plugin File
 *
 * Plugin Name: Scripts-To-Footer
 * Plugin URI: http://wordpress.org/plugins/scripts-to-footerphp/
 * Description: Moves scripts to the footer to decrease page load times, while keeping stylesheets in the header. Requires that plugins and theme correctly utilizes wp_enqueue_scripts hook. Can be disabled via a checkbox on specific pages and posts.
 * Version: 0.6.5
 * Author: Joshua David Nelson
 * Author URI: http://joshuadnelson.com
 * License: GPL2
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/scripts-to-footer
 * GitHub Branch: master
 *
 * @package   Scripts_To_Footer
 * @author    Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright Copyright (c) 2021, Joshua David Nelson
 * @license   http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link      https://github.com/joshuadavidnelson/scripts-to-footer
 */

/**
 * Prevent direct access to this file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

// Plugin Settings Field.
if ( ! defined( 'STF_SETTINGS_FIELD' ) ) {
	define( 'STF_SETTINGS_FIELD', 'scripts-to-footer' );
}

// Plugin Directory.
if ( ! defined( 'STF_DIR' ) ) {
	define( 'STF_DIR', dirname( __FILE__ ) );
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
 * @since 0.6.5
 */
function run_scripts_to_footer() {
	$plugin = new Scripts_To_Footer();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_scripts_to_footer', 10, 0 );
