<?php
/**
 * Tests for the main plugin file, scripts-to-footer.php.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for scripts-to-footer.php.
 *
 * Each test requires the main file, which declares global functions, so each runs in its
 * own process. The main file loads its classes with require_once, so the real includes the
 * bootstrap already loaded are not loaded twice.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MainFileTest extends TestCase {

	/**
	 * Absolute path of the main plugin file.
	 *
	 * @var string
	 */
	private $main_file;

	/**
	 * Arranges the WordPress calls the main file makes at load time.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->main_file = dirname( __DIR__, 3 ) . '/scripts-to-footer.php';

		WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'return' => function ( $file ) {
					$this->assertSame( $this->main_file, $file );

					return dirname( __DIR__, 3 ) . '/';
				},
			)
		);
	}

	/**
	 * Loads the main file with its load-time hook registrations expected.
	 *
	 * @return void
	 */
	private function load_main_file() {
		$this->expect_function_strict( 'register_activation_hook', array( $this->main_file, 'activate_scripts_to_footer' ), null );
		$this->expect_function_strict( 'register_deactivation_hook', array( $this->main_file, 'deactivate_scripts_to_footer' ), null );
		WP_Mock::expectActionAdded( 'plugins_loaded', 'run_scripts_to_footer', 10, 0 );

		require $this->main_file;
	}

	/**
	 * Loading the main file when its constants already exist keeps the existing values
	 * instead of raising "Constant already defined" warnings.
	 *
	 * @coversNothing
	 * @return void
	 */
	public function test_load_keeps_predefined_constants() {
		$this->load_main_file();

		$this->assertSame( 'scripts-to-footer', STF_PLUGIN_NAME );
		$this->assertSame( '0.7.4', STF_VERSION );
		$this->assertFalse( STF_DEBUG );
	}

	/**
	 * The activation callback runs STF_Activator::activate().
	 *
	 * @covers ::activate_scripts_to_footer
	 * @return void
	 */
	public function test_activation_callback_activates() {
		$this->load_main_file();

		WP_Mock::userFunction( 'flush_rewrite_rules', array( 'times' => 1 ) );

		activate_scripts_to_footer();
	}

	/**
	 * The deactivation callback runs STF_Deactivator::deactivate().
	 *
	 * @covers ::deactivate_scripts_to_footer
	 * @return void
	 */
	public function test_deactivation_callback_deactivates() {
		$this->load_main_file();

		WP_Mock::userFunction( 'flush_rewrite_rules', array( 'times' => 1 ) );

		deactivate_scripts_to_footer();
	}
}
