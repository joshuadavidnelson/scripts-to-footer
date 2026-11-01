<?php
/**
 * Integration tests for the main plugin file wiring.
 *
 * @package Scripts_To_Footer
 */

/**
 * Main plugin file tests.
 *
 * @coversNothing
 */
class PluginBootstrapTest extends WP_UnitTestCase {

	/**
	 * The real path of the main plugin file that the bootstrap loaded.
	 *
	 * @return string
	 */
	private function main_file() {
		return realpath( dirname( __DIR__, 3 ) . '/scripts-to-footer.php' );
	}

	/**
	 * The plugin starts on plugins_loaded at the default priority.
	 *
	 * @return void
	 */
	public function test_plugin_runs_on_plugins_loaded() {
		$this->assertSame( 10, has_action( 'plugins_loaded', 'run_scripts_to_footer' ) );
	}

	/**
	 * The activation hook is registered for the main file.
	 *
	 * @return void
	 */
	public function test_activation_hook_is_registered() {
		$hook = 'activate_' . plugin_basename( $this->main_file() );

		$this->assertSame( 10, has_action( $hook, 'activate_scripts_to_footer' ) );
	}

	/**
	 * The deactivation hook is registered for the main file.
	 *
	 * @return void
	 */
	public function test_deactivation_hook_is_registered() {
		$hook = 'deactivate_' . plugin_basename( $this->main_file() );

		$this->assertSame( 10, has_action( $hook, 'deactivate_scripts_to_footer' ) );
	}

	/**
	 * The version constant matches the plugin header.
	 *
	 * @return void
	 */
	public function test_version_constant_matches_plugin_header() {
		$data = get_file_data( $this->main_file(), array( 'Version' => 'Version' ) );

		$this->assertNotSame( '', $data['Version'] );
		$this->assertSame( $data['Version'], STF_VERSION );
	}
}
