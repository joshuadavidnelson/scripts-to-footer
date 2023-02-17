<?php
/**
 * Admin Settings
 *
 * @package    Scripts_To_Footer
 * @subpackage STF_Admin_Settings
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2023, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
 */

/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * Main Settings Class
 *
 * @since 0.6.0
 */
class STF_Admin_Settings {

	/**
	 * The functions class contains helper functions.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var STF_Functions $functions Contains functions used by multiple classes.
	 */
	protected $functions;

	/**
	 * Holds the values to be used in the fields callbacks
	 *
	 * @since 0.6.0
	 * @access private
	 * @var array
	 */
	private $options;

	/**
	 * The settings field.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var string
	 */
	protected $settings_field;

	/**
	 * Start up
	 *
	 * @since 0.6.0
	 */
	public function __construct() {

		$this->functions      = new STF_Functions();
		$this->settings_field = 'scripts-to-footer';

	}

	/**
	 * Add options page
	 *
	 * @since 0.6.0
	 */
	public function add_plugin_page() {

		// This page will be under "Settings".
		add_options_page(
			'Scripts to Footer Settings',
			'Scripts to Footer',
			'manage_options',
			$this->settings_field,
			array( $this, 'create_admin_page' )
		);

	}

	/**
	 * Options page callback
	 *
	 * @since 0.6.0
	 */
	public function create_admin_page() {

		// Set class property.
		$this->options = get_option( $this->settings_field );
		?>
		<div class="wrap">
			<h2>Scripts to Footer Settings</h2>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields.
				settings_fields( $this->settings_field );
				do_settings_sections( 'stf-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php

	}

	/**
	 * Register and add settings
	 *
	 * @since 0.6.0
	 */
	public function page_init() {

		// Admin options page.
		register_setting(
			$this->settings_field, // Option group.
			$this->settings_field, // Option name.
			array( $this, 'sanitize' ) // Sanitize.
		);

		// Template Options Section.
		add_settings_section(
			'exclude_options',
			'Exclude Options',
			array( $this, 'print_exclude_options_section_info' ),
			'stf-settings'
		);

		// Home page option.
		add_settings_field(
			'stf_home',
			'Blog Page',
			array( $this, 'stf_home' ),
			'stf-settings',
			'exclude_options'
		);

		// Search Results option.
		add_settings_field(
			'stf_search',
			'Search Results',
			array( $this, 'stf_search' ),
			'stf-settings',
			'exclude_options'
		);

		// Search Results option.
		add_settings_field(
			'stf_404',
			'404 pages',
			array( $this, 'stf_404' ),
			'stf-settings',
			'exclude_options'
		);

		// Post Type Archives options.
		add_settings_field(
			'stf_post_type_archives',
			'Post Type Archives',
			array( $this, 'stf_post_type_archives' ),
			'stf-settings',
			'exclude_options'
		);

		// Taxonomy Archives options.
		add_settings_field(
			'stf_taxonomy_archives',
			'Taxonomy Archives',
			array( $this, 'stf_taxonomy_archives' ),
			'stf-settings',
			'exclude_options'
		);

		// Author Archives options.
		add_settings_field(
			'stf_author_archive',
			'Author Archives',
			array( $this, 'stf_author_archive' ),
			'stf-settings',
			'exclude_options'
		);

		// Archive option.
		add_settings_field(
			'stf_archive',
			'Other Archive Pages',
			array( $this, 'stf_archive' ),
			'stf-settings',
			'exclude_options'
		);

		// Header Scripts Section.
		add_settings_section(
			'header_script_options',
			'Header Scripts',
			array( $this, 'print_header_script_section_info' ),
			'stf-settings'
		);

		// Archive option.
		add_settings_field(
			'stf_jquery_header',
			'Keep jQuery in the Header',
			array( $this, 'stf_jquery_header' ),
			'stf-settings',
			'header_script_options'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @since 0.6.0
	 * @since 0.6.2 added 404 support
	 * @param array $input Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {

		// Setup the new input array.
		$new_input = array();

		if ( isset( $input['stf_exclude_home'] ) ) {
			$new_input['stf_exclude_home'] = absint( $input['stf_exclude_home'] );
		}

		if ( isset( $input['stf_exclude_search'] ) ) {
			$new_input['stf_exclude_search'] = absint( $input['stf_exclude_search'] );
		}

		if ( isset( $input['stf_exclude_404'] ) ) {
			$new_input['stf_exclude_404'] = absint( $input['stf_exclude_404'] );
		}

		if ( isset( $input['stf_exclude_archive'] ) ) {
			$new_input['stf_exclude_archive'] = absint( $input['stf_exclude_archive'] );
		}

		if ( isset( $input['stf_exclude_author_archive'] ) ) {
			$new_input['stf_exclude_author_archive'] = absint( $input['stf_exclude_author_archive'] );
		}

		// Post Type options.
		$post_types = $this->post_type_options();
		if ( is_array( $post_types ) ) {
			foreach ( $post_types as $option ) {
				if ( isset( $input[ $option ] ) ) {
					$new_input[ $option ] = absint( $input[ $option ] );
				}
			}
		}

		// Taxonomy options.
		$taxes = $this->taxonomy_options();
		if ( is_array( $taxes ) ) {
			foreach ( $taxes as $option ) {
				if ( isset( $input[ $option ] ) ) {
					$new_input[ $option ] = absint( $input[ $option ] );
				}
			}
		}

		// jQuery in the header option.
		if ( isset( $input['stf_jquery_header'] ) ) {
			$new_input['stf_jquery_header'] = absint( $input['stf_jquery_header'] );
		}

		return $new_input;

	}

	/**
	 * Return the post type settings field names.
	 *
	 * @since 0.6.0
	 * @return array
	 */
	public function post_type_options() {

		$options = array();

		// Post types.
		$post_types = $this->functions->post_types_supported();

		if ( is_array( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[] = "stf_exclude_{$post_type}_archive";
			}
		}

		return $options;

	}

	/**
	 * Return the post type settings field names.
	 *
	 * @since 0.6.0
	 *
	 * @return array
	 */
	public function taxonomy_options() {

		$options = array();

		$taxes = $this->functions->taxonomies_supported();
		if ( is_array( $taxes ) ) {
			foreach ( $taxes as $tax ) {
					$options[] = "stf_exclude_{$tax}_archive";
			}
		}

		return $options;

	}

	/**
	 * Print the Section text.
	 *
	 * @since 0.6.0
	 */
	public function print_exclude_options_section_info() {

		// translators: tell the user that the options on this settings page are for selecting pages that will NOT have the scripts moved.
		echo wp_kses_post( _x( 'Select which templates should <em><strong>not</strong></em> have scripts moved to the footer:', 'stf' ) );

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6
	 */
	public function stf_home() {

		if ( ! isset( $this->options['stf_exclude_home'] ) ) {
			$this->options['stf_exclude_home'] = 0;
		}

		echo '<input type="checkbox" name="' . esc_attr( $this->settings_field ) . '[stf_exclude_home]" ' . checked( $this->options['stf_exclude_home'], 1, false ) . ' value="1">';

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_search() {

		if ( ! isset( $this->options['stf_exclude_search'] ) ) {
			$this->options['stf_exclude_search'] = 0;
		}

		echo '<input type="checkbox" name="' . esc_attr( $this->settings_field ) . '[stf_exclude_search]" ' . checked( $this->options['stf_exclude_search'], 1, false ) . ' value="1">';

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.2
	 */
	public function stf_404() {

		if ( ! isset( $this->options['stf_exclude_404'] ) ) {
			$this->options['stf_exclude_404'] = 0;
		}

		echo '<input type="checkbox" name="' . esc_attr( $this->settings_field ) . '[stf_exclude_404]" ' . checked( $this->options['stf_exclude_404'], 1, false ) . ' value="1">';

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_post_type_archives() {

		// Post types.
		$post_types = $this->functions->post_types_supported();

		if ( is_array( $post_types ) ) {
			echo '<ul>';
			foreach ( $post_types as $post_type ) {
				if ( ! isset( $this->options[ "stf_exclude_{$post_type}_archive" ] ) ) {
					$this->options[ "stf_exclude_{$post_type}_archive" ] = 0;
				}

				$obj = get_post_type_object( $post_type );

				// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
				echo "<li><input type=\"checkbox\" id=\"stf_exclude_{$post_type}_archive\" name=\"" . $this->settings_field . "[stf_exclude_{$post_type}_archive]\" " . checked( $this->options[ "stf_exclude_{$post_type}_archive" ], 1, false ) . " value=\"1\"><label for=\"stf_exclude_{$post_type}_archive\">" . $obj->labels->singular_name . ' </label></li>';
				// @codingStandardsIgnoreEnd
			}
			echo '</ul>';
		}

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_taxonomy_archives() {

		$taxes = $this->functions->taxonomies_supported();

		if ( is_array( $taxes ) ) {
			echo '<ul>';
			foreach ( $taxes as $taxonomy ) {
				if ( ! isset( $this->options[ "stf_exclude_{$taxonomy}_archive" ] ) ) {
					$this->options[ "stf_exclude_{$taxonomy}_archive" ] = 0;
				}

				$obj = get_taxonomy( $taxonomy );

				// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
				echo "<li><input type=\"checkbox\" id=\"stf_exclude_{$taxonomy}_archive\" name=\"" . $this->settings_field . "[stf_exclude_{$taxonomy}_archive]\" " . checked( $this->options["stf_exclude_{$taxonomy}_archive"], 1, false ) . " value=\"1\"><label for=\"stf_exclude_{$taxonomy}_archive\">" . $obj->labels->singular_name . ' </label></li>';
				// @codingStandardsIgnoreEnd
			}
			echo '</ul>';
		}
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_author_archive() {

		if ( ! isset( $this->options['stf_exclude_author_archive'] ) ) {
			$this->options['stf_exclude_author_archive'] = 0;
		}

		// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
		echo '<input type="checkbox" name="' . $this->settings_field . '[stf_exclude_author_archive]" ' . checked( $this->options['stf_exclude_author_archive'], 1, false ) . ' value="1">';
		// @codingStandardsIgnoreEnd

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_archive() {

		if ( ! isset( $this->options['stf_exclude_archive'] ) ) {
			$this->options['stf_exclude_archive'] = 0;
		}

		// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
		echo '<input type="checkbox" name="' . $this->settings_field . '[stf_exclude_archive]" ' . checked( $this->options['stf_exclude_archive'], 1, false ) . ' value="1">';
		// @codingStandardsIgnoreEnd

	}

	/**
	 * Print the Section text.
	 *
	 * @since 0.6.0
	 */
	public function print_header_script_section_info() {

		// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
		// translators: intro for the head scripts settings section and link to the documentation on Github.
		echo sprintf( __( 'Options for keeping specific scripts in the header, if they occur. Want to exclude a different script? Check out the <a href="%s" title="On Github">documentation</a> for more information.', 'stf' ), 'https://github.com/joshuadavidnelson/scripts-to-footer/wiki' );
		// @codingStandardsIgnoreEnd

	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @since 0.6.0
	 */
	public function stf_jquery_header() {

		if ( ! isset( $this->options['stf_jquery_header'] ) ) {
			$this->options['stf_jquery_header'] = 0;
		}

		// @codingStandardsIgnoreStart - wants to escape variables here that are entirely safe.
		echo '<input type="checkbox" name="' . $this->settings_field . '[stf_jquery_header]" ' . checked( $this->options['stf_jquery_header'], 1, false ) . ' value="1">';
		// @codingStandardsIgnoreEnd

	}

}
