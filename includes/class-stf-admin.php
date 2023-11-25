<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      0.7.0
 * @package    Scripts_To_Footer
 * @subpackage STF_Admin
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
class STF_Admin {

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
	 * The settings field.
	 *
	 * @since 0.7.0
	 * @access protected
	 * @var string
	 */
	protected $settings_field;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since  0.7.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name    = $plugin_name;
		$this->version        = $version;
		$this->functions      = new STF_Functions();
		$this->settings_field = 'scripts-to-footer';
	}

	/**
	 * Add various links to plugin page
	 *
	 * @since 0.2.0
	 * @param array  $links the array of plugin links.
	 * @param string $file  the current plugin file.
	 * @return array
	 */
	public function plugin_links( $links, $file ) {

		/** Capability Check */
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $links;
		}

		if ( basename( dirname( $file ) ) === $this->plugin_name ) {

			$links[] = '<a href="http://wordpress.org/support/plugin/scripts-to-footerphp" title="' . __( 'Support', 'stf' ) . '">' . __( 'Support', 'stf' ) . '</a>';

			$links[] = '<a href="https://github.com/joshuadavidnelson/scripts-to-footer/wiki" title="' . __( 'Documentation', 'stf' ) . '" target="_blank">' . __( 'Documentation', 'stf' ) . '</a>';

			$links[] = '<a href="http://joshuadnelson.com/donate" title="' . __( 'Donate', 'stf' ) . '">' . __( 'Donate', 'stf' ) . '</a>';

		}

		return $links;
	}

	/**
	 * Add link to options page in plguin screen.
	 *
	 * @since 0.6.0
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_settings_link( $links ) {

		$settings_link = '<a href="options-general.php?page=' . $this->settings_field . '">Settings</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Initialize the single post metabox.
	 *
	 * @since 0.6.0
	 */
	public function metabox_register() {

		// Check for post type support.
		$post_type = get_post_type();
		if ( ! $post_type || ! $this->functions->post_type_supported( $post_type ) ) {
			return;
		}

		add_meta_box( 'scripts-to-footer', 'Scripts to Footer Settings', array( $this, 'metabox_render' ), $post_type, 'normal', 'high' );
	}

	/**
	 * Output the single post metabox.
	 *
	 * @since 0.6.0
	 */
	public function metabox_render() {

		// Grab current value.
		$exclude = get_post_meta( get_the_ID(), 'stf_exclude', true );

		// Update old 'on' values to bool values.
		if ( 'on' === $exclude ) {
			update_post_meta( get_the_ID(), 'stf_exclude', 1, 'on' );
			$exclude = 1;
		}

		// Security nonce.
		wp_nonce_field( 'scripts_to_footer', 'stf_nonce' );

		echo '<p style="padding-top:10px;">';

		// Exclude.
		// @codingStandardsIgnoreStart
		printf( '<label for="stf_exclude">%s</label>', __( 'Turn Plugin Off', 'stf' ) );
		// @codingStandardsIgnoreEnd

		echo '<input type="checkbox" id="stf_exclude" name="stf_exclude" ' . checked( true, $exclude, false ) . ' style="margin:0 20px 0 10px;">';

		// @codingStandardsIgnoreStart
		// translators: indicates the checkbox will turn off the plugin for a specific post/page.
		printf( '<span style="color:#999;">%s</span>', __( 'By default, this plugin will run on this post type. This checkbox lets you turn it off for this specific page/post.', 'stf' ) );
		// @codingStandardsIgnoreEnd

		echo '</p>';
	}

	/**
	 * Handle metabox saves
	 *
	 * @since 0.6.0
	 * @param int    $post_id the post id.
	 * @param object $post    the post object.
	 * @return void
	 */
	public function metabox_save( $post_id, $post ) {

		// Security check.
		// @codingStandardsIgnoreStart
		if ( ! isset( $_POST['stf_nonce'] ) || ! wp_verify_nonce( $_POST['stf_nonce'], 'scripts_to_footer' ) ) {
			return;
		}
		// @codingStandardsIgnoreEnd

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

		// Either save or delete they post meta.
		if ( isset( $_POST['stf_exclude'] ) ) {
			$value = (int) (bool) $_POST['stf_exclude'];
			update_post_meta( $post_id, 'stf_exclude', $value );
		} else {
			delete_post_meta( $post_id, 'stf_exclude' );
		}
	}
}
