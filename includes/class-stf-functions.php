<?php
/**
 * Functions
 *
 * @package    Scripts_To_Footer
 * @subpackage STF_Functions
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2023, Joshua David Nelson
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
 * Common functions used by other classes.
 *
 * @since 0.7.0
 */
class STF_Functions {

	/**
	 * Check for post type support, via the filter. Default support for page and post.
	 *
	 * @since 0.6.0
	 * @return bool|array Supported posts
	 */
	public function post_types_supported() {

		/**
		 * Post types to be supported by the plugin.
		 *
		 * @since 0.6.0
		 * @param array $post_types an array of post type slugs, defaults to pages and posts.
		 */
		$post_types = apply_filters( 'scripts_to_footer_post_types', array( 'page', 'post' ) );
		if ( is_array( $post_types ) ) {
			return $post_types;
		} else {
			return false;
		}

	}

	/**
	 * Check if a post type is supported.
	 *
	 * @since 0.6.0
	 * @param string $post_type the post type slug.
	 * @return bool
	 */
	public function post_type_supported( $post_type ) {

		$post_types = $this->post_types_supported();
		if ( is_array( $post_types ) && is_string( $post_type ) && in_array( $post_type, $post_types, true ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Check for custom taxonomy support, via the filter.
	 *
	 * @since 0.6.0
	 * @return bool|array Supported posts
	 */
	public function taxonomies_supported() {

		/**
		 * Taxonomies to be supported by the plugin.
		 *
		 * @since 0.6.0
		 * @param array $post_types an array of post type slugs, defaults to categories and post tags.
		 */
		$taxes = apply_filters( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ) );
		if ( is_array( $taxes ) ) {
			return $taxes;
		} else {
			return false;
		}

	}

	/**
	 * Check if a post type is supported.
	 *
	 * @since 0.6.0
	 * @param string $taxonomy the current taxonomy slug.
	 * @return bool
	 */
	public function tax_supported( $taxonomy ) {

		$taxes = $this->taxonomies_supported();
		if ( is_array( $taxes ) && is_string( $taxonomy ) && in_array( $taxonomy, $taxes, true ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Log any errors, if debug mode is on.
	 *
	 * @since 0.6.0
	 * @param mixed $message the message to be logged.
	 * @return void
	 */
	public function log_me( $message ) {

		if ( $this->debug() ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( 'Scripts-to-Footer Plugin Error: ' . print_r( $message, true ) ); // phpcs:ignore
			} else {
				error_log( 'Scripts-to-Footer Plugin Error: ' . $message ); // phpcs:ignore
			}
		}

	}

	/**
	 * Check to see if we're in a debug mode.
	 *
	 * @since 0.6.0
	 * @return bool
	 */
	private function debug() {

		return defined( 'WP_DEBUG' ) && true === WP_DEBUG
				&& defined( 'STF_DEBUG' ) && true === STF_DEBUG;

	}
}
