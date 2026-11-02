<?php
/**
 * Tests for uninstall.php.
 *
 * The branch where WP_UNINSTALL_PLUGIN is undefined calls header() and exit() and is
 * intentionally not covered.
 *
 * @package Scripts_To_Footer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'scripts-to-footer/scripts-to-footer.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

/**
 * Covers the procedural uninstall.php file.
 */
class UninstallTest extends TestCase {

	/**
	 * The wp_die() messages must go through esc_html__(), so it is stubbed with a marker.
	 *
	 * @var string[]
	 */
	protected $own_i18n_stubs = array( 'esc_html__' );

	/**
	 * Ordered record of WordPress calls.
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Resets the call record and marks esc_html__() output as HTML(text).
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->calls = array();

		WP_Mock::userFunction(
			'esc_html__',
			array(
				'return' => function ( $text, $domain ) {
					return 'HTML(' . $text . '|' . $domain . ')';
				},
			)
		);
	}

	/**
	 * Stubs the cleanup functions so each call is recorded in order.
	 *
	 * @return void
	 */
	private function stub_recorders() {
		$record = function ( $name ) {
			return function ( ...$args ) use ( $name ) {
				$this->calls[] = array_merge( array( $name ), $args );
			};
		};

		WP_Mock::userFunction( 'delete_option', array( 'return' => $record( 'delete_option' ) ) );
		WP_Mock::userFunction( 'delete_post_meta_by_key', array( 'return' => $record( 'delete_post_meta_by_key' ) ) );
		WP_Mock::userFunction( 'switch_to_blog', array( 'return' => $record( 'switch_to_blog' ) ) );
		WP_Mock::userFunction( 'restore_current_blog', array( 'return' => $record( 'restore_current_blog' ) ) );
	}

	/**
	 * Includes uninstall.php.
	 *
	 * @return void
	 */
	private function run_uninstall() {
		include dirname( __DIR__, 3 ) . '/uninstall.php';
	}

	/**
	 * Anonymous users are stopped before anything is deleted.
	 *
	 * @return void
	 */
	public function test_not_logged_in_dies() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( false );

		$this->expectException( STF_Test_Wp_Die_Exception::class );
		$this->expectExceptionMessage( 'HTML(You must be logged in to run this script.|scripts-to-footerphp)' );

		try {
			$this->run_uninstall();
		} finally {
			$this->assertSame( array(), $this->calls );
		}
	}

	/**
	 * Users without install_plugins are stopped before anything is deleted.
	 *
	 * @return void
	 */
	public function test_missing_capability_dies() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), false );

		try {
			$this->run_uninstall();
			$this->fail( 'wp_die() should have been called.' );
		} catch ( STF_Test_Wp_Die_Exception $e ) {
			$this->assertSame( 'HTML(You do not have permission to run this script.|scripts-to-footerphp)', $e->getMessage() );
			$this->assertSame( 'HTML(Scripts To Footer|scripts-to-footerphp)', $e->title );
			$this->assertSame( array( 'back_link' => true ), $e->args );
		} finally {
			$this->assertSame( array(), $this->calls );
		}
	}

	/**
	 * The wp_die arguments carry the title and back link.
	 *
	 * @return void
	 */
	public function test_die_passes_title_and_back_link() {
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( false );

		try {
			$this->run_uninstall();
			$this->fail( 'wp_die() should have been called.' );
		} catch ( STF_Test_Wp_Die_Exception $e ) {
			$this->assertSame( 'HTML(Scripts To Footer|scripts-to-footerphp)', $e->title );
			$this->assertSame( array( 'back_link' => true ), $e->args );
		}
	}

	/**
	 * A single site deletes the options and post meta once.
	 *
	 * @return void
	 */
	public function test_single_site_deletes_options_and_meta() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( false );

		$this->run_uninstall();

		// Characterizes current behavior: 'stf_previous_version' is NOT deleted (likely a bug).
		$this->assertSame(
			array(
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
			),
			$this->calls
		);
	}

	/**
	 * Multisite runs the deletions for each site, switching in order.
	 *
	 * @return void
	 */
	public function test_multisite_deletes_per_site() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$this->expect_get_sites( array( 3, 7 ) );

		$this->run_uninstall();

		$this->assertSame(
			array(
				array( 'switch_to_blog', 3 ),
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
				array( 'restore_current_blog' ),
				array( 'switch_to_blog', 7 ),
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
				array( 'restore_current_blog' ),
			),
			$this->calls
		);
	}

	/**
	 * Multisite with no sites does nothing.
	 *
	 * @return void
	 */
	public function test_multisite_without_sites_does_nothing() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$this->expect_get_sites( array() );

		$this->run_uninstall();

		$this->assertSame( array(), $this->calls );
	}

	/**
	 * Expects get_sites() to be asked for every site ID, exactly once.
	 *
	 * @param int[] $ids The site IDs it returns.
	 * @return void
	 */
	private function expect_get_sites( array $ids ) {
		$this->expect_function_strict(
			'get_sites',
			array(
				array(
					'fields' => 'ids',
					'number' => 0,
				),
			),
			$ids
		);
	}
}
