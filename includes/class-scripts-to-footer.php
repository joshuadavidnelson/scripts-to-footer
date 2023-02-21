<?php
/**
 * Main Plugin File
 *
 * Plugin Name: Scripts-To-Footer
 * Plugin URI: http://wordpress.org/plugins/scripts-to-footerphp/
 * Description: Moves scripts from the head to the footer of your site.
 * Version: 0.6.4.1
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

/**
 * Scripts to Footer Class.
 *
 * Forces scripts to the footer, unless excluded in the settings page.
 *
 * @since 0.2.0
 * @since 0.7.0 placed in includes/ folder.
 */
class Scripts_To_Footer {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var STF_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The plugin filtename.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var string $plugin_file The plugin file.
	 */
	protected $plugin_file;
	/**
	 * The current version of the plugin.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * The functions class contains helper functions.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var STF_Functions $functions Contains functions used by multiple classes.
	 */
	protected $functions;

	/**
	 * An array of script slugs that should remain in the header.
	 *
	 * @since 0.6.0
	 *
	 * @var array
	 */
	protected $header_scripts;

	/**
	 * Construct.
	 *
	 * Registers our activation hook and init hook.
	 *
	 * @since 0.2.0
	 */
	public function __construct() {

		$this->plugin_name = 'scripts-to-footer';
		$this->version     = '0.7.0';
		$this->plugin_file = 'scripts-to-footer/scripts-to-footer.php';

		do_action( 'stf_init' );

		$this->setup_constants();
		$this->upgrade_check();
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Define our plugin constants.
	 *
	 * @since 0.2.0
	 * @since 0.7.0 moved into this class.
	 */
	private function setup_constants() {

		// Custom Debug Constant, intented for developer use.
		if ( ! defined( 'STF_DEBUG' ) ) {
			define( 'STF_DEBUG', false );
		}

		// Plugin Verison.
		define( 'STF_VERSION', $this->version );

	}

	/**
	 * Upgrade check.
	 *
	 * @since 0.7.0
	 *
	 * @access private
	 */
	private static function upgrade_check() {

		// let's only run these checks on the admin page load.
		if ( ! is_admin() ) {
			return;
		}

		// Get the current version option.
		$current_version = get_option( 'stf_version', false );

		// Update the previous version if we're upgrading.
		if ( $current_version && STF_VERSION !== $current_version ) {
			update_option( 'stf_previous_version', $current_version, false );
		}

		// See if it's a previous version, which may not have set the version option.
		if ( false === $current_version || STF_VERSION !== $current_version ) {
			// do things on update.

			// Save current version.
			update_option( 'stf_version', STF_VERSION, false );
		}

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Scripts_To_Footer_Loader. Orchestrates the hooks of the plugin.
	 * - Scripts_To_Footer_I18n. Defines internationalization functionality.
	 * - Scripts_To_Footer_Admin. Defines all hooks for the admin area.
	 * - Scripts_To_Footer_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since 0.7.0
	 * @access private
	 */
	private function load_dependencies() {

		$includes_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once $includes_dir . '/class-stf-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once $includes_dir . '/class-stf-i18n.php';

		/**
		 * The class containing functions used by multiple classes.
		 */
		require_once $includes_dir . '/class-stf-functions.php';

		/**
		 * Public functions.
		 */
		require_once $includes_dir . '/functions.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once $includes_dir . '/class-stf-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once $includes_dir . '/class-stf-public.php';

		/**
		 * The class responsible for the admin settings page.
		 */
		require_once $includes_dir . '/class-stf-admin-settings.php';

		$this->loader    = new STF_Loader();
		$this->functions = new STF_Functions();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Scripts_To_Footer_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since  0.7.0
	 * @access private
	 */
	private function set_locale() {

		$plugin_i18n = new STF_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since  0.7.0
	 * @access private
	 */
	private function define_admin_hooks() {

		$plugin_admin   = new STF_Admin( $this->get_plugin_name(), $this->get_version() );
		$admin_settings = new STF_Admin_Settings();

		// Add Links to Plugin Bar.
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'plugin_links', 10, 2 );

		// Add setting link to plugin bar.
		$this->loader->add_filter( 'plugin_action_links_' . $this->plugin_file, $plugin_admin, 'plugin_settings_link' );

		// Metabox on Edit screen, for Page/Post Override.
		$this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'metabox_register' );
		$this->loader->add_action( 'save_post', $plugin_admin, 'metabox_save', 1, 2 );

		// Add the page to the menu.
		$this->loader->add_action( 'admin_menu', $admin_settings, 'add_plugin_page' );

		// Create the admin page.
		$this->loader->add_action( 'admin_init', $admin_settings, 'page_init' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since  0.7.0
	 * @access private
	 */
	private function define_public_hooks() {

		$plugin_public = new STF_Public( $this->get_plugin_name(), $this->get_version() );

		// Run the plugin.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'clean_head' );
		$this->loader->add_filter( 'stf_include', $plugin_public, 'stf_includes' );

		// Set the header scripts to be forced into the header.
		$this->loader->add_action( 'wp_head', $plugin_public, 'set_header_scripts', 1 );

		// Add select scripts into the header.
		$this->loader->add_action( 'wp_head', $plugin_public, 'print_head_scripts', 10 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 0.7.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since 0.7.0
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since 0.7.0
	 * @return STF_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since 0.7.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
