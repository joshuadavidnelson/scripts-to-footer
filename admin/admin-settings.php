<?php
/**
 * Main Plugin File
 *
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer_Settings
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2014, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       http://joshuadnelson.com/scripts-to-footer-plugin
 **/

/**
 * Prevent direct access to this file.
 *
 * @since 0.2
 **/
if( !defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

if( !class_exists( 'Scripts_To_Footer_Settings' ) ) {	
	class Scripts_To_Footer_Settings {
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;

		/**
		 * Start up
		 *
		 * @since 0.6
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'page_init' ) );
		}
		
		/**
		 * Add options page
		 *
		 * @since 0.6
		 */
		public function add_plugin_page() {
			// This page will be under "Settings"
			add_options_page(
				'Scripts to Footer Settings', 
				'Scripts to Footer', 
				'manage_options', 
				STF_SETTINGS_FIELD, 
				array( $this, 'create_admin_page' )
			);
		}
		
		/**
		 * Options page callback
		 *
		 * @since 0.6
		 */
		public function create_admin_page()  {
			// Set class property
			$this->options = get_option( STF_SETTINGS_FIELD );
			?>
			<div class="wrap">
				<h2>Scripts to Footer Settings</h2>           
				<form method="post" action="options.php">
					<?php
					// This prints out all hidden setting fields
					settings_fields( STF_SETTINGS_FIELD );   
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
		 * @since 0.6
		 */
		public function page_init() {
			
			// Metabox on Edit screen, for Page/Post Override
			add_action( 'add_meta_boxes', array( $this, 'metabox_register' ) );
			add_action( 'save_post', array( $this, 'metabox_save' ),  1, 2  );
			
			// Admin options page
			register_setting(
				STF_SETTINGS_FIELD, // Option group
				STF_SETTINGS_FIELD, // Option name
				array( $this, 'sanitize' ) // Sanitize
			);
			
			// Template Options Section
			add_settings_section(
				'exclude_options',
				'Exclude Options',
				array( $this, 'print_exclude_options_section_info' ),
				'stf-settings'
			);
			
			// Home page option
			add_settings_field(
				'stf_home',
				'Blog Page', 
				array( $this, 'stf_home' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Search Results option
			add_settings_field(
				'stf_search',
				'Search Results', 
				array( $this, 'stf_search' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Post Type Archives options
			add_settings_field(
				'stf_post_type_archives',
				'Post Type Archives', 
				array( $this, 'stf_post_type_archives' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Taxonomy Archives options
			add_settings_field(
				'stf_taxonomy_archives',
				'Taxonomy Archives', 
				array( $this, 'stf_taxonomy_archives' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Author Archives options
			add_settings_field(
				'stf_author_archive',
				'Author Archives', 
				array( $this, 'stf_author_archive' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Archive option
			add_settings_field(
				'stf_archive',
				'Other Archive Pages', 
				array( $this, 'stf_archive' ),
				'stf-settings',
				'exclude_options'          
			);
			
			// Header Scripts Section
			add_settings_section(
				'header_script_options',
				'Header Scripts',
				array( $this, 'print_header_script_section_info' ),
				'stf-settings'
			);
			
			// Archive option
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
		 * @since 0.6
		 *
		 * @param array $input Contains all settings fields as array keys
		 */
		public function sanitize( $input ) {
			$new_input = array();
			if( isset( $input['stf_exclude_home'] ) )
				$new_input['stf_exclude_home'] = absint( $input['stf_exclude_home'] );
			
			if( isset( $input['stf_exclude_search'] ) )
				$new_input['stf_exclude_search'] = absint( $input['stf_exclude_search'] );
				
			if( isset( $input['stf_exclude_archive'] ) )
				$new_input['stf_exclude_archive'] = absint( $input['stf_exclude_archive'] );
			if( isset( $input['stf_exclude_author_archive'] ) )
				$new_input['stf_exclude_author_archive'] = absint( $input['stf_exclude_author_archive'] );
			
			// Post Type options
			$post_types = $this->post_type_options();
			if( is_array( $post_types ) ) {
				foreach( $post_types as $option ) {
					if( isset( $input[ $option ] ) )
						$new_input[ $option ] = absint( $input[ $option ] );
				}
			}
			
			// Taxonomy options
			$taxes = $this->taxonomy_options();
			if( is_array( $taxes ) ) {
				foreach( $taxes as $option ) {
					if( isset( $input[ $option ] ) )
						$new_input[ $option ] = absint( $input[ $option ] );
				}
			}
			
			if( isset( $input['stf_jquery_header'] ) )
				$new_input['stf_jquery_header'] = absint( $input['stf_jquery_header'] );
				
			return $new_input;
		}
		
		/**
		 * Return the post type settings field names.
		 *
		 * @since 0.6
		 *
		 * @return array
		 */
		function post_type_options() {
			$options = array();
			
			global $stf_scripts_to_footer;
			$post_types = $stf_scripts_to_footer->post_types_supported();
			if( is_array( $post_types ) ) {
				foreach( $post_types as $post_type ) {
					 $options[] = "stf_exclude_{$post_type}_archive";
				}
			}
			
			return $options;
		}
		
		/**
		 * Return the post type settings field names.
		 *
		 * @since 0.6
		 *
		 * @return array
		 */
		function taxonomy_options() {
			$options = array();
			
			global $stf_scripts_to_footer;
			$taxes = $stf_scripts_to_footer->taxonomies_supported();
			if( is_array( $taxes ) ) {
				foreach( $taxes as $tax ) {
					 $options[] = "stf_exclude_{$tax}_archive";
				}
			}
			
			return $options;
		}
		
		/** 
		 * Print the Section text.
		 *
		 * @since 0.6
		 */
		public function print_exclude_options_section_info() {
			echo _x( 'Select which templates should <em><strong>not</strong></em> have scripts moved to the footer:', STF_DOMAIN );
		}

		/** 
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_home() {
			if( !isset( $this->options['stf_exclude_home'] ) )
				$this->options['stf_exclude_home'] = 0;
				
			echo '<input type="checkbox" name="' . STF_SETTINGS_FIELD . '[stf_exclude_home]" ' . checked( $this->options['stf_exclude_home'], 1, false ) . ' value="1">';
	
		}
		
		/** 
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_search() {
			if( !isset( $this->options['stf_exclude_search'] ) )
				$this->options['stf_exclude_search'] = 0;
			
			echo '<input type="checkbox" name="' . STF_SETTINGS_FIELD . '[stf_exclude_search]" ' . checked( $this->options['stf_exclude_search'], 1, false ) . ' value="1">';
		
		}
		
		/**
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_post_type_archives() {
			global $stf_scripts_to_footer;
			$post_types = $stf_scripts_to_footer->post_types_supported();
			if( is_array( $post_types ) ) {
				echo '<ul>';
				foreach( $post_types as $post_type ) {
					if( !isset( $this->options["stf_exclude_{$post_type}_archive"] ) )
						$this->options["stf_exclude_{$post_type}_archive"] = 0;
					
					$obj = get_post_type_object( $post_type );
					$title = $obj->labels->singular_name;
					echo "<li><input type=\"checkbox\" id=\"stf_exclude_{$post_type}_archive\" name=\"" . STF_SETTINGS_FIELD . "[stf_exclude_{$post_type}_archive]\" " . checked( $this->options["stf_exclude_{$post_type}_archive"], 1, false ) . " value=\"1\"><label for=\"stf_exclude_{$post_type}_archive\">" . $title . ' </label></li>';
				}
				echo '</ul>';
			}
		}
		
		/**
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_taxonomy_archives() {
			global $stf_scripts_to_footer;
			$taxes = $stf_scripts_to_footer->taxonomies_supported();
			if( is_array( $taxes ) ) {
				echo '<ul>';
				foreach( $taxes as $taxonomy ) {
					if( !isset( $this->options["stf_exclude_{$taxonomy}_archive"] ) )
						$this->options["stf_exclude_{$taxonomy}_archive"] = 0;
				
					$obj = get_taxonomy( $taxonomy );
					$title = $obj->labels->singular_name;
					echo "<li><input type=\"checkbox\" id=\"stf_exclude_{$taxonomy}_archive\" name=\"" . STF_SETTINGS_FIELD . "[stf_exclude_{$taxonomy}_archive]\" " . checked( $this->options["stf_exclude_{$taxonomy}_archive"], 1, false ) . " value=\"1\"><label for=\"stf_exclude_{$taxonomy}_archive\">" . $title . ' </label></li>';
				}
				echo '</ul>';
			}
		}
		
		/** 
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_author_archive() {
			if( !isset( $this->options['stf_exclude_author_archive'] ) )
				$this->options['stf_exclude_author_archive'] = 0;
			
			echo '<input type="checkbox" name="' . STF_SETTINGS_FIELD . '[stf_exclude_author_archive]" ' . checked( $this->options['stf_exclude_author_archive'], 1, false ) . ' value="1">';

		}
		
		/** 
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_archive() {
			if( !isset( $this->options['stf_exclude_archive'] ) )
				$this->options['stf_exclude_archive'] = 0;
			
			echo '<input type="checkbox" name="' . STF_SETTINGS_FIELD . '[stf_exclude_archive]" ' . checked( $this->options['stf_exclude_archive'], 1, false ) . ' value="1">';

		}
		
		/** 
		 * Print the Section text.
		 *
		 * @since 0.6
		 */
		public function print_header_script_section_info() {
			echo sprintf( __( 'Options for keeping specific scripts in the header, if they occur. Want to exclude a different script? Check out the <a href="%s" title="On Github">documentation</a> for more information.', STF_DOMAIN ), 'https://github.com/joshuadavidnelson/scripts-to-footer/wiki#header-scripts' );
		}
		
		/** 
		 * Get the settings option array and print one of its values.
		 *
		 * @since 0.6
		 */
		public function stf_jquery_header() {
			if( !isset( $this->options['stf_jquery_header'] ) )
				$this->options['stf_jquery_header'] = 0;
			
			echo '<input type="checkbox" name="' . STF_SETTINGS_FIELD . '[stf_jquery_header]" ' . checked( $this->options['stf_jquery_header'], 1, false ) . ' value="1">';

		}
		
		///////////// Post Metabox /////////////
		
		/**
		 * Initialize the single post metabox.
		 *
		 * @since 0.6
		 */
		function metabox_register() {
			global $stf_scripts_to_footer;
			
			// Check for post type support
			$post_type = get_post_type();
			if( !$post_type || !$stf_scripts_to_footer->post_type_supported( $post_type ) )
				return;
		
			add_meta_box( 'scripts-to-footer', 'Scripts to Footer Settings', array( $this, 'metabox_render' ), $post_type, 'normal', 'high' );
		}
	
		/**
		 * Output the single post metabox.
		 *
		 * @since 0.6
		 */
		function metabox_render() {

			// Grab current value
			$exclude = get_post_meta( get_the_ID(), 'stf_exclude', true );
			if( $exclude == 'on' ) {
				update_post_meta( get_the_ID(), 'stf_exclude', 1, 'on' );
				$exclude = 1;
			}
				
			// Security nonce
			wp_nonce_field( 'scripts_to_footer', 'stf_nonce' );

			echo '<p style="padding-top:10px;">';
			
			// Exclude
			printf( '<label for="stf_exclude">%s</label>', __( 'Turn Plugin Off', STF_DOMAIN ) );
	
			echo '<input type="checkbox" id="stf_exclude" name="stf_exclude" ' . checked( true , $exclude, false ) . ' style="margin:0 20px 0 10px;">';
	
			printf( '<span style="color:#999;">%s</span>', __( 'By default, this plugin will run on this post type. This checkbox lets you turn it off for this specific page/post.', STF_DOMAIN ) );
		
			echo '</p>';
		}

		/**
		 * Handle metabox saves
		 *
		 * @since 0.6
		 */
		function metabox_save( $post_id, $post ) {

			// Security check
			if ( ! isset( $_POST['stf_nonce'] ) || ! wp_verify_nonce( $_POST['stf_nonce'], 'scripts_to_footer' ) ) {
				return;
			}

			// Bail out if running an autosave, ajax, cron.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				return;
			}

			// Bail out if the user doesn't have the correct permissions to update the slider.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		
			// Either save or delete they post meta
			if ( isset( $_POST[ 'stf_exclude' ] ) ) {
				$value = (int) (bool) $_POST[ 'stf_exclude' ];
				update_post_meta( $post_id, 'stf_exclude', $value );
			} else {
				delete_post_meta( $post_id, 'stf_exclude' );
			}
		}
	}
	
	if( is_admin() )
	    $stf_settings = new Scripts_To_Footer_Settings();
}

/**
 * Get custom settings.
 *
 * @since 0.6
 *
 * @param string $key The key
 * @param string $setting The settings field
 */
if( !function_exists( 'stf_get_option' ) ) {
	function stf_get_option( $key, $setting = null ) {
		// Get settings field options
		$setting = $setting ? $setting : STF_SETTINGS_FIELD;
		$options = get_option( $setting );
		
		if ( ! is_array( $options ) || ! array_key_exists( $key, $options ) )
			return '';

		return is_array( $options[$key] ) ? stripslashes_deep( $options[$key] ) : stripslashes( wp_kses_decode_entities( $options[$key] ) );
	}
}
