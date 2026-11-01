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
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

/**
 * Covers the procedural uninstall.php file.
 */
class UninstallTest extends TestCase {

	/**
	 * Original $wpdb global.
	 *
	 * @var mixed
	 */
	private $original_wpdb;

	/**
	 * Ordered record of WordPress calls.
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Saves $wpdb.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->original_wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		$this->calls         = array();
	}

	/**
	 * Restores $wpdb.
	 *
	 * @return void
	 */
	protected function tear_down() {
		if ( null === $this->original_wpdb ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->original_wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		parent::tear_down();
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
		$this->expectExceptionMessage( 'You must be logged in to run this script.' );

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
			$this->assertSame( 'You do not have permission to run this script.', $e->getMessage() );
			$this->assertSame( 'Scripts To Footer', $e->title );
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
			$this->assertSame( 'Scripts To Footer', $e->title );
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

		$this->assertSame(
			array(
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_option', 'stf_previous_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
			),
			$this->calls
		);
	}

	/**
	 * Multisite runs the deletions for each blog, switching in order.
	 *
	 * @return void
	 */
	public function test_multisite_deletes_per_blog() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$GLOBALS['wpdb'] = $this->make_wpdb( array( array( 'blog_id' => 3 ), array( 'blog_id' => 7 ) ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->run_uninstall();

		$this->assertSame( array( array( 'SELECT blog_id FROM wp_blogs', ARRAY_A ) ), $GLOBALS['wpdb']->queries );
		$this->assertSame(
			array(
				array( 'switch_to_blog', 3 ),
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_option', 'stf_previous_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
				array( 'restore_current_blog' ),
				array( 'switch_to_blog', 7 ),
				array( 'delete_option', 'scripts-to-footer' ),
				array( 'delete_option', 'stf_version' ),
				array( 'delete_option', 'stf_previous_version' ),
				array( 'delete_post_meta_by_key', 'stf_exclude' ),
				array( 'restore_current_blog' ),
			),
			$this->calls
		);
	}

	/**
	 * Multisite with no blogs does nothing.
	 *
	 * @return void
	 */
	public function test_multisite_without_blogs_does_nothing() {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$GLOBALS['wpdb'] = $this->make_wpdb( array() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->run_uninstall();

		$this->assertSame( array( array( 'SELECT blog_id FROM wp_blogs', ARRAY_A ) ), $GLOBALS['wpdb']->queries );
		$this->assertSame( array(), $this->calls );
	}

	/**
	 * Provides the non-array results get_results() can return (wpdb returns null on error).
	 *
	 * @return array
	 */
	public function provide_empty_blog_results() {
		return array(
			'null'  => array( null ),
			'false' => array( false ),
		);
	}

	/**
	 * Multisite where the blog query yields no usable rows touches nothing, without warnings.
	 *
	 * @dataProvider provide_empty_blog_results
	 *
	 * @param mixed $result The get_results() return value.
	 * @return void
	 */
	public function test_multisite_with_empty_query_result_does_nothing( $result ) {
		$this->stub_recorders();
		WP_Mock::userFunction( 'is_user_logged_in' )->once()->andReturn( true );
		$this->expect_function_strict( 'current_user_can', array( 'install_plugins' ), true );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$GLOBALS['wpdb'] = $this->make_wpdb( $result ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->run_uninstall();

		$this->assertSame( array( array( 'SELECT blog_id FROM wp_blogs', ARRAY_A ) ), $GLOBALS['wpdb']->queries );
		$this->assertSame( array(), $this->calls );
	}

	/**
	 * Builds a $wpdb double that records queries and returns the given rows.
	 *
	 * @param array|null|false $rows Rows to return from get_results().
	 * @return object
	 */
	private function make_wpdb( $rows ) {
		return new class( $rows ) {
			/**
			 * Blogs table.
			 *
			 * @var string
			 */
			public $blogs = 'wp_blogs';

			/**
			 * Recorded queries.
			 *
			 * @var array
			 */
			public $queries = array();

			/**
			 * Rows to return.
			 *
			 * @var array|null|false
			 */
			private $rows;

			/**
			 * Constructor.
			 *
			 * @param mixed $rows Rows.
			 */
			public function __construct( $rows ) {
				$this->rows = $rows;
			}

			/**
			 * Records and answers a query.
			 *
			 * @param string $query  SQL.
			 * @param string $output Output type.
			 * @return mixed
			 */
			public function get_results( $query, $output ) {
				$this->queries[] = array( $query, $output );

				return $this->rows;
			}
		};
	}
}
