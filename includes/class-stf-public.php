<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage STF_Public
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
		$exclude      = stf_get_option( 'stf_jquery_header', false );
		$head_scripts = $exclude ? array( 'jquery' ) : array();

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

		if ( empty( $this->header_scripts ) || ! is_array( $this->header_scripts ) ) {
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
	 * Determining if the current page is included, via a filter.
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
			return $this->singular_includes();
		}

		$type = $this->template_type();
		if ( false === $type ) {
			return false;
		}

		/**
		 * Filter to *exclude* a type of page, return the opposite.
		 *
		 * @since 0.2.0
		 * @param bool $exclude return true disable the plugin.
		 */
		$exclude = stf_get_option( "stf_exclude_{$type}", false );
		$include = ! $exclude;

		/**
		 * Filter to include the template, true to move the scripts, false to not.
		 *
		 * @since 0.6.0
		 * @param bool $include true to include, false to not.
		 */
		return apply_filters( "stf_{$type}", $include );
	}

	/**
	 * Check a singular post or page against the plugin's exclusions.
	 *
	 * @since 0.7.5
	 * @return bool
	 */
	private function singular_includes() {

		// Make sure we can grab the page/post id.
		$queried_object_id = get_queried_object_id();
		if ( ! absint( $queried_object_id ) ) {
			return false;
		}

		// See if post type is supported, if not bail.
		$post_type = get_post_type( $queried_object_id );
		if ( ! $post_type || false === $this->functions->post_type_supported( $post_type ) ) {
			return false;
		}

		if ( $this->is_excluded_by_meta( $queried_object_id ) || $this->is_excluded_by_legacy_filter( $queried_object_id ) ) {
			return false;
		}

		/**
		 * Allow override of plugin functionality based on post type.
		 *
		 * @since 0.6.0
		 * @param bool $exclude           defaults to true, which runs the plugin.
		 * @param int  $queried_object_id the current object id.
		 */
		return apply_filters( "stf_{$post_type}", true, $queried_object_id );
	}

	/**
	 * Check the exclude post meta value.
	 *
	 * Supports older versions that use 'on' as well as newer versions with bool.
	 *
	 * @since 0.7.5
	 * @param int $post_id The post id.
	 * @return bool
	 */
	private function is_excluded_by_meta( $post_id ) {

		$exclude_page = get_post_meta( $post_id, 'stf_exclude', true );

		return 'on' === $exclude_page || true === (bool) $exclude_page;
	}

	/**
	 * Check the older, deprecated exclusion filter.
	 *
	 * @since 0.7.5
	 * @param int $post_id The post id.
	 * @return bool
	 */
	private function is_excluded_by_legacy_filter( $post_id ) {

		$excluded_override = apply_filters( 'scripts_to_footer_exclude_page', null, $post_id );
		if ( 'on' !== $excluded_override && true !== $excluded_override ) {
			return false;
		}

		$this->functions->log_me( 'The \'scripts_to_footer_exclude_page\' is depreciated, please use \'stf_{$post_type}\' returning false to exclude the page.' );

		return true;
	}

	/**
	 * Get the template type slug for the current non-singular request.
	 *
	 * @since 0.7.5
	 * @return string|false The type slug, or false if the page is not supported.
	 */
	private function template_type() {

		$simple_types = array(
			'home'           => 'is_home',
			'search'         => 'is_search',
			'404'            => 'is_404',
			'author_archive' => 'is_author',
		);
		foreach ( $simple_types as $type => $condition ) {
			if ( $condition() ) {
				return $type;
			}
		}

		if ( is_category() ) {
			return $this->taxonomy_type( 'category' );
		}

		if ( is_tag() ) {
			return $this->taxonomy_type( 'post_tag' );
		}

		if ( is_tax() ) {
			return $this->queried_taxonomy_type();
		}

		if ( is_post_type_archive() ) {
			return $this->post_type_archive_type();
		}

		// Generic archives (date, etc), otherwise unsupported.
		return is_archive() ? 'archive' : false;
	}

	/**
	 * Get the archive type slug for a taxonomy, if the taxonomy is supported.
	 *
	 * @since 0.7.5
	 * @param string $taxonomy The taxonomy name.
	 * @return string|false
	 */
	private function taxonomy_type( $taxonomy ) {

		return $this->functions->tax_supported( $taxonomy ) ? "{$taxonomy}_archive" : false;
	}

	/**
	 * Get the archive type slug for the currently queried taxonomy.
	 *
	 * @since 0.7.5
	 * @return string|false
	 */
	private function queried_taxonomy_type() {

		$taxonomy = get_query_var( 'taxonomy' );
		if ( ! $taxonomy ) {
			return false;
		}

		$tax = get_taxonomy( $taxonomy );
		if ( ! isset( $tax->name ) ) {
			return false;
		}

		return $this->taxonomy_type( $tax->name );
	}

	/**
	 * Get the archive type slug for the current post type archive.
	 *
	 * @since 0.7.5
	 * @return string|false
	 */
	private function post_type_archive_type() {

		$post_type = get_post_type();

		return $this->functions->post_type_supported( $post_type ) ? "{$post_type}_archive" : false;
	}
}
