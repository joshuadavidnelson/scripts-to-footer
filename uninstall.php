<?php
/**
 * Scripts to Footer Uninstall File
 *
 * @package    Scripts_To_Footer
 * @subpackage Uninstall
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2018, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       http://joshuadnelson.com/scripts-to-footer-plugin
 *
 * @since      0.2
 **/
 
/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 */
if ( !defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * If uninstall not called from WordPress, exit.
 *
 * @since 0.2
 *
 * @uses  WP_UNINSTALL_PLUGIN
 */
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Various user checks.
 *
 * @since 0.2
 *
 * @uses  is_user_logged_in()
 * @uses  current_user_can()
 * @uses  wp_die()
 */
if( !is_user_logged_in() ) {
	wp_die(
		__( 'You must be logged in to run this script.', STF_DOMAIN ),
		__( 'Scripts To Footer', STF_DOMAIN ),
		array( 'back_link' => true )
	);
} 

if( !current_user_can( 'install_plugins' ) ) {
	wp_die(
		__( 'You do not have permission to run this script.', STF_DOMAIN ),
		__( 'Scripts To Footer', STF_DOMAIN ),
		array( 'back_link' => true )
	);	
}

/**
 * Delete options array (settings field) from the database.
 *    Note: Respects Multisite setups and single installs.
 *
 * @since 0.2
 *
 * @uses  switch_to_blog()
 * @uses  restore_current_blog()
 *
 * @param array $blogs
 * @param int 	$blog
 *
 * @global $wpdb
 */
// First, check for Multisite, if yes, delete options on a per site basis
if ( is_multisite() ) {
	global $wpdb;
	
	// Get array of Site/Blog IDs from the database 
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	
	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			// Repeat for every Site ID 
			switch_to_blog( $blog[ 'blog_id' ] );
			// Delete plugin options
			delete_option( STF_SETTINGS_FIELD );
			delete_option( 'stf_version' );
	
			// Delete all post meta
			delete_post_meta_by_key( 'stf_exclude' );
		} 
		restore_current_blog();
	}
	
} else { // Otherwise, delete options from main options table
	// Delete plugin options
	delete_option( STF_SETTINGS_FIELD );
	delete_option( 'stf_version' );

	// Delete all post meta
	delete_post_meta_by_key( 'stf_exclude' );
}