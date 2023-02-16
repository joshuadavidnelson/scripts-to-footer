<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    Scripts_To_Footer
 * @subpackage STF_Public
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2021, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
 **/

/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * The public-facing functionality of the plugin.
 *
 * @since 0.7.0
 */
class STF_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since  0.7.0
	 * @access private
	 * @var    string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  0.7.0
	 * @access private
	 * @var    string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * The functions class contains helper functions.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var STF_Functions $functions Contains functions used by multiple classes.
	 */
	protected $functions;

	/**
	 * Scripts to remain in the head.
	 *
	 * @since  0.7.0
	 * @access private
	 * @var    array $header_scripts A group strings[] of script slugs.
	 */
	private $header_scripts;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since  0.7.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->functions   = new STF_Functions();

	}

	/**
	 * Set the scripts to be printed in the header, based on options and filter.
	 *
	 * @since 0.6.0
	 */
	public function set_header_scripts() {

		// automatically include jquery in the head if the option is selected.
		$exclude = stf_get_option( 'stf_jquery_header', false );
		if ( $exclude ) {
			$head_scripts = array( 'jquery' );
		} else {
			$head_scripts = array();
		}

		/**
		 * Filter the scripts being displayed in the head.
		 *
		 * @since 0.2.0
		 * @param array $head_scripts an array of script slugs to be placed in the head.
		 * @return array
		 */
		$this->header_scripts = apply_filters( 'stf_exclude_scripts', $head_scripts );

	}

	/**
	 * The holy grail: print select scripts in the header!
	 *
	 * @since 0.6.0
	 */
	public function print_head_scripts() {

		if ( ! isset( $this->header_scripts ) || empty( $this->header_scripts ) || ! is_array( $this->header_scripts ) ) { // phpcs:ignore
			return;
		}

		// The main filter, true inacts the plugin, false does not (excludes the page).
		if ( $this->is_included() ) {
			foreach ( $this->header_scripts as $script ) {
				if ( ! is_string( $script ) ) {
					continue;
				}

				// If the script is enqueued for the page, print it.
				if ( wp_script_is( $script ) ) {
					wp_print_scripts( $script );
				}
			}
		}

	}

	/**
	 * Remove scripts from header, forces them to the footer.
	 *
	 * Checks the singular post/page first, then other common pages
	 * and compares against any global settings and filters.
	 *
	 * @since 0.1.0
	 * @return void
	 **/
	public function clean_head() {

		// Bail if we're in the admin area.
		if ( is_admin() ) {
			return;
		}

		// The main filter, true inacts the plugin, false does not (excludes the page).
		$include = $this->is_included();

		// Either it's turned off site wide and included for this post/page, or turned on site wide and not excluded for this post/page - also not admin.
		if ( true === $include ) {
			remove_action( 'wp_head', 'wp_print_scripts' );
			remove_action( 'wp_head', 'wp_print_head_scripts', 9 );
			remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		}

	}

	/**
	 * Determing if the current page is included, via a filter.
	 *
	 * @since 0.6.0
	 * @return bool Default is true.
	 */
	public function is_included() {

		/**
		 * The main filter for including a page in the plugin's functionality.
		 *
		 * @since 0.6.0
		 * @param bool $include true to move the scripts, false to skip the page.
		 * @return bool
		 */
		return (bool) apply_filters( 'stf_include', true );

	}

	/**
	 * Runs the various template checks and returns true/false.
	 *
	 * @since 0.6.0
	 * @return bool
	 */
	public function stf_includes() {

		// Singular page or post type.
		if ( is_singular() || is_page() ) {

			// Make sure we can grab the page/post id.
			$queried_object_id = get_queried_object_id();

			// verify we got a good result.
			if ( absint( $queried_object_id ) ) {
				$post_type = get_post_type( $queried_object_id );

				// See if post type is supported, if not bail.
				if ( ! $post_type || false === $this->functions->post_type_supported( $post_type ) ) {
					return false;
				}

				// Get the exclude post meta value.
				$exclude_page = get_post_meta( $queried_object_id, 'stf_exclude', true );

				// Support for older versions that use 'on' as well as newer versions with bool.
				if ( 'on' === $exclude_page || true === $exclude_page ) {
					return false;
				}

				// Older override check, depreciated.
				$excluded_override = apply_filters( 'scripts_to_footer_exclude_page', null, $queried_object_id );
				if ( 'on' === $excluded_override || true === $excluded_override ) {
					$this->functions->log_me( 'The \'scripts_to_footer_exclude_page\' is depreciated, please use \'stf_{$post_type}\' returning false to exclude the page.' );
					return false;
				}

				/**
				 * Allow override of plugin functionality based on post type.
				 *
				 * @since 0.6.0
				 * @param bool $exclude           defaults to true, whch runs the plugin.
				 * @param int  $queried_object_id the current object id.
				 */
				return apply_filters( "stf_{$post_type}", true, $queried_object_id );

			} else {
				return false;
			}
		} elseif ( is_home() ) { // Home (blog) page.

			// Grab global setting.
			$type = 'home';

		} elseif ( is_search() ) { // Search Result Page.

			$type = 'search';

		} elseif ( is_404() ) { // 404 Pages.

			$type = '404';

		} elseif ( is_author() ) { // Author Archive.

			$type = 'author_archive';

		} elseif ( is_category() ) { // Category Archive.

			if ( $this->functions->tax_supported( 'category' ) ) {
				$type = 'category_archive';
			} else {
				return false;
			}
		} elseif ( is_tag() ) { // Tag Archive.

			if ( $this->functions->tax_supported( 'post_tag' ) ) {
				$type = 'post_tag_archive';
			} else {
				return false;
			}
		} elseif ( is_tax() ) { // Taxonomy Archive.

			$taxonomy = get_query_var( 'taxonomy' );
			if ( ! $taxonomy ) {
				return false;
			}
			$tax = get_taxonomy( $taxonomy );
			if ( isset( $tax->name ) && $this->functions->tax_supported( $tax->name ) ) {
				$type = "{$tax->name}_archive";
			} else {
				return false;
			}
		} elseif ( is_post_type_archive() ) { // Post Type Archive.

			$post_type = get_post_type();
			if ( $this->functions->post_type_supported( $post_type ) ) {
				$type = "{$post_type}_archive";
			} else {
				return false;
			}
		} elseif ( is_archive() ) { // Generic archives (date, author, etc).

			$type = 'archive';

		} else { // if all else fails return false.

			return false;

		}

		/**
		 * Filter to *exclude* a type of page, return the opposite.
		 *
		 * @since 0.2.0
		 * @param bool $exclude return true disable the plugin.
		 */
		$exclude = stf_get_option( "stf_exclude_{$type}", false );
		if ( $exclude ) {
			$include = false;
		} else {
			$include = true;
		}

		/**
		 * Filter to include the template, true to move the scripts, false to not.
		 *
		 * @since 0.6.0
		 * @param bool $include true to include, false to not.
		 */
		return apply_filters( "stf_{$type}", $include );

	}
}
