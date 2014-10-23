<?php
/**
 * Main Plugin File
 *
 * @package    Scripts_To_Footer
 * @subpackage Admin
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
	 * Construct.
	 *
	 * Registers our activation hook and init hook.
	 *
	 * @since 0.2
	 * @author Joshua David Nelson
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Plugin Init.
	 *
	 * Makes some checks and runs the filter, sets up the admin settings page
	 * and plugin links.
	 *
	 * @since 0.2
	 * @author Joshua David Nelson
	 */
	function init() {
		// Run the plugin
		add_action( 'wp_enqueue_scripts', array( $this, 'clean_head' ) );
		
		// Metabox on Edit screen, for Page Override
		add_filter( 'cmb_meta_boxes', array( $this, 'create_metaboxes' ) );
		add_action( 'init', array( $this, 'initialize_cmb_meta_boxes' ), 50 );
		
		// Add Links to Plugin Bar
		if( function_exists( 'stf_plugin_links' ) )
			add_filter( 'plugin_row_meta', 'stf_plugin_links', 10, 2 );
	}
	
	/**
	 * Remove scripts from header, forces them to the footer.
	 *
	 * First checks to see if the current post type is excluded via the custom meta box, if so then it doesn't run.
	 *
	 * @since 0.1
	 **/
	function clean_head() {
		if( get_queried_object_id() ) {
			$queried_object_id = get_queried_object_id();
			$exclude_page = get_post_meta( $queried_object_id, 'stf_exclude', true );
			$exclude_page = apply_filters( 'scripts_to_footer_exclude_page', $exclude_page, $queried_object_id );
			
			if( 'on' !== $exclude_page && !is_admin() ) {
				remove_action( 'wp_head', 'wp_print_scripts' ); 
				remove_action( 'wp_head', 'wp_print_head_scripts', 9 ); 
				remove_action( 'wp_head', 'wp_enqueue_scripts', 1 ); 
			}
		}
	}
	
	/**
	 * Create Page-Specific Metaboxes.
	 * @link http://www.billerickson.net/wordpress-metaboxes/
	 *
	 * @param array $meta_boxes, current metaboxes
	 * @return array $meta_boxes, current + new metaboxes
	 *
	 * @since 0.2
	 */
	function create_metaboxes( $meta_boxes ) {
		$post_types = apply_filters( 'scripts_to_footer_post_types', array( 'page', 'post' ) );
		if( !empty( $post_types ) ) {
			$meta_boxes[] = array(
				'id' => 'scripts-to-footer',
				'title' => __( 'Scripts to Footer Plugin Settings', STF_DOMAIN ),
				'pages' => $post_types,
				'context' => 'normal',
				'priority' => 'high',
				'show_names' => true,
				'fields' => array(
					array(
						'name' => __( 'Disable Plugin', STF_DOMAIN ),
						'desc' => __( 'By default, the scripts to footer plugin is set to run. This checkbox disables the plugin on this page', STF_DOMAIN ),
						'id' => 'stf_exclude',
						'type' => 'checkbox'
					)
				)
			);
		} 
		
		return $meta_boxes;
	}
	
	/**
	 * Initializing Custom Metaboxes
	 *
	 * @author Bill Erickson, billerickson.net
	 * @since 0.2
	 **/
	function initialize_cmb_meta_boxes() {
		$post_types = apply_filters( 'scripts_to_footer_post_types', array( 'page', 'post' ) );
		if( !class_exists( 'cmb_Meta_Box' ) && !empty( $post_types ) ) {
	        	require_once( dirname( __FILE__) . '/lib/metabox/init.php' );
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
	
			$links[] = '<a href="http://jdn.im/donate" title="' . __( 'Donate', STF_DOMAIN ) . '">' . __( 'Donate', STF_DOMAIN ) . '</a>';
		}
		
		return $links;
	}
}
