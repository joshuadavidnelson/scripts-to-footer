<?php
/**
 * Main Plugin File
 *
 * @package    Scripts_To_Footer
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2014, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       http://joshuadnelson.com/scripts-to-footer-plugin
 *
 * Plugin Name: Scripts-To-Footer
 * Plugin URI: http://wordpress.org/plugins/scripts-to-footerphp/
 * Description: Moves scripts to the footer to decrease page load times, while keeping stylesheets in the header. Requires that plugins and theme correctly utilizes wp_enqueue_scripts hook. Can be disabled via a checkbox on specific pages and posts.
 * Version: 0.6
 * Author: Joshua David Nelson
 * Author URI: http://joshuadnelson.com
 * License: GPL2
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/scripts-to-footer 
 * GitHub Branch: develop
 *
 **/

/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 **/
if( !defined( 'ABSPATH' ) ) {
        exit( 'You are not allowed to access this file directly.' );
}

/**
 * Define our plugin variables
 *
 * @since 0.2
 **/
// Plugin Settings Field
if( !defined( 'STF_SETTINGS_FIELD' ) )
	define( 'STF_SETTINGS_FIELD', 'scripts-to-footer' );

// Plugin Domain
if( !defined( 'STF_DOMAIN' ) )
	define( 'STF_DOMAIN', 'stf' );

// Plugin Verison
if( !defined( 'STF_VERSION' ) )
	define( 'STF_VERSION', '0.6' );

// Plugin name
if( !defined( 'STF_NAME' ) )
    define( 'STF_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );

// Plugin Directory
if( !defined( 'STF_DIR' ) )
    define( 'STF_DIR', WP_PLUGIN_DIR . '/' . STF_NAME );

// Plugin URL
if( !defined( 'STF_URL' ) )
    define( 'STF_URL', WP_PLUGIN_URL . '/' . STF_NAME );

/**
 * Scripts to Footer Class.
 *
 * Forces scripts to the footer, unless excluded in the settings page.
 *
 * @since 0.2
 */
global $stf_scripts_to_footer;
$stf_scripts_to_footer = new Scripts_To_Footer();

class Scripts_To_Footer {
	
	/**
	 * An array of script slugs that should remain in the header.
	 *
	 * @since 0.6
	 *
	 * @var array
	 */
	protected $header_scripts;

	/**
	 * Construct.
	 *
	 * Registers our activation hook and init hook.
	 *
	 * @since 0.2
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'check_version' ) );

		// Don't run anything else in the plugin, if we're on an incompatible
		if ( ! self::compatible_version() ) {
			return;
		}
		
		// Admin settings
		include_once( STF_DIR . '/admin/admin-settings.php' );
		
		// Make it so
		add_action( 'init', array( $this, 'init' ) );
	}
	
	/**
	 * Activation hook. 
	 *
	 * The primary sanity check, automatically disable the plugin on activation
	 * if it doesn't meet minimum requirements. If it does, then it does some version
	 * checks and updates the site version option.
	 *
	 * @since 0.6
	 */
	static function activation_check() {
		if ( ! self::compatible_version() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( __( 'Scripts-to-Footer requires WordPress 3.1.0 or higher', 'stf' ) );
		} else {
			// Save the previous version we're upgrading from
			$current_version = get_option( 'stf_version', false );
			if ( $current_version )
				update_option( 'stf_previous_version', $current_version );
		
			// See if it's a previous version, which may not have set the version option
			if ( false === $current_version || $current_version != STF_VERSION ) {
				// do things on update
			}
		
			// Save current version
			update_option( 'stf_version', STF_VERSION );
		}
	}
	
	/**
	 * The backup sanity check.
	 *
	 * This is just in case the plugin is activated in a weird way,
	 * or the versions change after activation.
	 *
	 * @since 0.6
	 */
	function check_version() {
		if ( ! self::compatible_version() ) {
			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}
	}
	
	/**
	 * Display notice on deactivation.
	 *
	 * @since 0.6
	 *
	 * @return void
	 */
	function disabled_notice() {
		echo '<strong>' . esc_html__( 'Scripts-to-Footer requires WordPress 3.1.0 or higher.', STF_DOMAIN ) . '</strong>';
	}
	
	/**
	 * Check everything is compatible.
	 *
	 * @since 0.6
	 *
	 * @return boolean
	 */
	static function compatible_version() {
		if ( version_compare( $GLOBALS['wp_version'], '3.1.0', '<' ) ) {
			return false;
		}

		// Add sanity checks for other version requirements here

		return true;
	}

	/**
	 * Plugin Init.
	 *
	 * Makes some checks and runs the filter, sets up the admin settings page
	 * and plugin links.
	 *
	 * @since 0.2
	 */
	function init() {
		
		// Run the plugin
		add_action( 'wp_enqueue_scripts', array( $this, 'clean_head' ) );
		add_filter( 'stf_include', array( $this, 'stf_includes' ) );
		
		// Set the header scripts to be forced into the header
		$this->set_header_scripts();
		
		// Add select scripts into the header
		add_action( 'wp_head', array( $this, 'print_head_scripts' ) );
		
		// Add Links to Plugin Bar
		if( function_exists( 'stf_plugin_links' ) )
			add_filter( 'plugin_row_meta', 'stf_plugin_links', 10, 2 );
		
		// Add setting link to plugin bar
		if( function_exists( 'stf_plugin_settings_link' ) ) {
			$plugin = plugin_basename(__FILE__); 
			add_filter( "plugin_action_links_$plugin", 'stf_plugin_settings_link' );
		}
	}
	
	/**
	 * Set the scripts to be printed in the header, based on options and filter.
	 *
	 * @since 0.6
	 */
	public function set_header_scripts() {
		if( $exclude = stf_get_option( 'stf_jquery_header', false ) ) {
			$head_scripts = array( 'jquery' );
		} else {
			$head_scripts = array();
		}
		
		$this->header_scripts = apply_filters( 'stf_exclude_scripts', $head_scripts );
	}
	
	/**
	 * The holy grail: print select scripts in the header!
	 *
	 * @since 0.6
	 */
	function print_head_scripts() {
		if( !isset( $this->header_scripts ) || empty( $this->header_scripts ) || !is_array( $this->header_scripts ) )
			return;
		
		// The main filter, true inacts the plugin, false does not (excludes the page)
		$include = $this->is_included();
		if( $this->is_included() ) {
			foreach( $this->header_scripts as $script ) {
				if( !is_string( $script ) )
					continue;
			
				// If the script is enqueued for the page, print it
				if( wp_script_is( $script ) )
					wp_print_scripts( $script );
			}
		}
	}
	
	/**
	 * Remove scripts from header, forces them to the footer.
	 *
	 * Checks the singular post/page first, then other common pages
	 * and compares against any global settings and filters.
	 *
	 * @since 0.1
	 **/
	function clean_head() {
		
		// Bail if we're in the admin area
		if( is_admin() )
			return;
		
		// The main filter, true inacts the plugin, false does not (excludes the page)
		$include = $this->is_included();
		
		// If this isn't set, then we're missing something
		if( !isset( $include ) ) {
			$this->log_me( 'Something went wrong with the $include variable' );
			return;
		}
		
		// Either it's turned off site wide and included for this post/page, or turned on site wide and not excluded for this post/page - also not admin
		if( true === $include ) {
			remove_action( 'wp_head', 'wp_print_scripts' ); 
			remove_action( 'wp_head', 'wp_print_head_scripts', 9 ); 
			remove_action( 'wp_head', 'wp_enqueue_scripts', 1 ); 
		}
	}

	/**
	 * Determing if the current page is included, via a filter.
	 *
	 * @since 0.6
	 *
	 * @return boolean Default is true.
	 */
	public function is_included() {
		$include = apply_filters( 'stf_include', true );
		
		if( true === $include ) {
			return true;
		} elseif( false === $include ) {
			return false;
		} else {
			$this->log_me( 'Non-boolean value in the stf_include filter' );
			return true;
		}
	}
	
	/**
	 * Runs the various template checks and returns true/false.
	 *
	 * @since 0.6
	 *
	 * @return boolean
	 */
	function stf_includes() {
		// Collect the information
		if( is_singular() || is_page() ) {
			// Make sure we can grab the page/post id
			$queried_object_id = get_queried_object_id();
			
			// verify we got a good result
			if( absint( $queried_object_id ) && ( $post_type = get_post_type( $queried_object_id ) ) ) {
				// See if post type is supported, if not bail
				if( false === $this->post_type_supported( $post_type ) )
					return false;

				// Get the exclude post meta value
				$exclude_page = get_post_meta( $queried_object_id, 'stf_exclude', true );
				
				// Support for older versions that use 'on' as well as newer versions with boolean
				if( 'on' === $exclude_page || true == $exclude_page )
					return false;
				
				// Older override check, depreciated
				$excluded_override = apply_filters( 'scripts_to_footer_exclude_page', null, $queried_object_id );
				if( 'on' == $excluded_override || true == $excluded_override ) {
					$this->log_me( 'The scripts_to_footer_exclude_page is depreciated, please use stf_{$post_type} returning false to exclude');
					return false;
				}
				
				// Allow override
				return apply_filters( "stf_{$post_type}", true, $queried_object_id );
			
			} else {
				$this->log_me( 'Unable to get a correct object id from get_queried_object_id or unable to get post type' );
				return false;
			}
			
		// Home (blog) page
		} elseif( is_home() ) {
			// Grab global setting
			$type = 'home';
			
		// Search Result Page
		} elseif( is_search() ) {
			$type = 'search';
		
		// Author Archive
		} elseif( is_author() ) {
			$type = 'author_archive';
		
		// Category Archive
		} elseif( is_category() ) {
			
			if( $this->tax_supported( 'category' ) ) {
				$type = "category_archive";
			} else {
				$this->log_me( 'Error in category check' );
				return false;
			} 
	
		// Tag Archive
		} elseif( is_tag() ) {
			
			if( $this->tax_supported( 'post_tag' ) ) {
				$type = "post_tag_archive";
			} elseif( false === $tax ) {
				$this->log_me( 'Error in tag check' );
				return false;
			} 
		
		// Taxonomy Archive
		} elseif( is_tax() ) {
			
			$tax = get_taxonomy();
			if( isset( $tax->name ) && $this->tax_supported( $tax->name ) ) {
				$type = "{$tax->name}_archive";
			} elseif( false === $tax ) {
				$this->log_me( 'Error in taxonomy check' );
				return false;
			} 
			
		// Post Type Archive
		} elseif( is_post_type_archive() ) {
		
			$post_type = get_post_type();
			if( $this->post_type_supported( $post_type ) ) {
				$type = "{$post_type}_archive";
			} elseif( false === $post_type ) {
				$this->log_me( 'Error in post type check check' );
				return false;
			}

		// Generic archives (date, author, etc)
		} elseif( is_archive() ) {
			$type = 'archive';
		
		// if all else fails, log an error, return false
		} else {
			return false;
		}
		
		// Get the option and return the result with a filter to override
		if( isset( $type ) && is_string( $type ) ) {
			// Filter to *exclude* a type of page, return the opposite
			$exclude = stf_get_option( "stf_exclude_{$type}", false );
			if( $exclude ) {
				$include = false;
			} else {
				$include = true;
			}
			return apply_filters( "stf_{$type}", $include );
			
		} else {
			$this->log_me( 'invalid $type element' );
			return false;
		}
	}
	
	/**
	 * Check for post type support, via the filter. Default support for page and post.
	 *
	 * @since 0.6
	 *
	 * @return array Supported posts
	 */
	public function post_types_supported() {
		$post_types = apply_filters( 'scripts_to_footer_post_types', array( 'page', 'post' ) );
		if( is_array( $post_types ) ) {
			return $post_types;
		} else {
			$this->log_me( 'Invalid post types returned in scripts_to_footer_post_types filter' );
			return false;
		}
	}
	
	/**
	 * Check if a post type is supported.
	 *
	 * @since 0.6
	 *
	 * @return array Supported posts
	 */
	public function post_type_supported( $post_type ) {
		$post_types = $this->post_types_supported();
		if( is_array( $post_types ) && is_string( $post_type ) && in_array( $post_type, $post_types ) ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Check for custom taxonomy support, via the filter.
	 *
	 * @since 0.6
	 *
	 * @return array Supported posts
	 */
	public function taxonomies_supported() {
		$taxes = apply_filters( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ) );
		if( is_array( $taxes ) ) {
			return $taxes;
		} else {
			$this->log_me( 'Invalid taxonomies returned in scripts_to_footer_taxonomies filter' );
			return false;
		}
	}
	
	/**
	 * Check if a post type is supported.
	 *
	 * @since 0.6
	 *
	 * @return array Supported posts
	 */
	public function tax_supported( $taxonomy ) {
		$taxes = $this->taxonomies_supported();
		if( is_array( $taxes ) && is_string( $taxonomy ) && in_array( $taxonomy, $taxes ) ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Log any errors, if debug mode is on.
	 *
	 * @since 0.6
	 *
	 * @param string $message
	 */
	public function log_me( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( 'Scripts-to-Footer Plugin Error: ' . print_r( $message, true ) );
			} else {
				error_log( 'Scripts-to-Footer Plugin Error: ' . $message );
			}
		}
	}
}

/**
 * Add various links to plugin page
 *
 * @since  0.2
 *
 * @param  $links
 * @param  $file
 *
 * @return strings plugin links
 */
if( !function_exists( 'stf_plugin_links' ) ) {
	function stf_plugin_links( $links, $file ) {
	    static $this_plugin;
	
		/** Capability Check */
		if( ! current_user_can( 'install_plugins' ) ) 
			return $links;
	
		if( !$this_plugin ) {
			$this_plugin = plugin_basename(__FILE__);
		}
	
		if( $file == $this_plugin ) {
			$links[] = '<a href="http://wordpress.org/support/plugin/scripts-to-footerphp" title="' . __( 'Support', STF_DOMAIN ) . '">' . __( 'Support', STF_DOMAIN ) . '</a>';
			
			$links[] = '<a href="https://github.com/joshuadavidnelson/scripts-to-footer/wiki" title="' . __( 'Documentation', STF_DOMAIN ) . '" target="_blank">' . __( 'Documentation', STF_DOMAIN ) . '</a>';
	
			$links[] = '<a href="http://jdn.im/donate" title="' . __( 'Donate', STF_DOMAIN ) . '">' . __( 'Donate', STF_DOMAIN ) . '</a>';
		}
		
		return $links;
	}
}

/**
 * Add link to options page in plguin screen.
 *
 * @since 0.6
 *
 * @param string $links Links.
 * @return $links Amended links.
 */
if( !function_exists( 'stf_plugin_settings_link' ) ) {
	function stf_plugin_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . STF_SETTINGS_FIELD . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}