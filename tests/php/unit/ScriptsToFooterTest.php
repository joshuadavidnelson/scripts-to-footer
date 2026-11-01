<?php
/**
 * Tests for Scripts_To_Footer.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for Scripts_To_Footer.
 *
 * @covers Scripts_To_Footer
 */
class ScriptsToFooterTest extends TestCase {

	/**
	 * The $wpdb global before the test.
	 *
	 * @var mixed
	 */
	private $wpdb_backup;

	/**
	 * Whether $wpdb was set before the test.
	 *
	 * @var bool
	 */
	private $had_wpdb;

	/**
	 * The $wpdb double installed for the test.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Installs a recording $wpdb double.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->had_wpdb    = array_key_exists( 'wpdb', $GLOBALS );
		$this->wpdb_backup = $this->had_wpdb ? $GLOBALS['wpdb'] : null;

		$this->wpdb = new class() {
			/**
			 * Postmeta table name.
			 *
			 * @var string
			 */
			public $postmeta = 'wp_postmeta';

			/**
			 * Recorded update() argument lists.
			 *
			 * @var array
			 */
			public $updates = array();

			/**
			 * Rows update() reports as changed.
			 *
			 * @var int
			 */
			public $affected = 0;

			/**
			 * Records the call.
			 *
			 * @param mixed ...$args Table, data and where.
			 * @return int
			 */
			public function update( ...$args ) {
				$this->updates[] = $args;

				return $this->affected;
			}
		};

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * Restores $wpdb.
	 *
	 * @return void
	 */
	protected function tear_down() {
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->wpdb_backup;
		} else {
			unset( $GLOBALS['wpdb'] );
		}

		parent::tear_down();
	}

	/**
	 * The exact migration call.
	 *
	 * @return array
	 */
	private function migration_call() {
		// phpcs:disable WordPress.DB.SlowDBQuery -- Asserting the arguments of the migration query.
		return array(
			'wp_postmeta',
			array( 'meta_value' => '1' ),
			array(
				'meta_key'   => 'stf_exclude',
				'meta_value' => 'on',
			),
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
	}

	/**
	 * Stubs what the constructor reaches.
	 *
	 * @param bool   $is_admin Whether is_admin() is true.
	 * @param string $basename What plugin_basename() reports for the main file.
	 * @return void
	 */
	private function stub_constructor( $is_admin = false, $basename = 'scripts-to-footer/scripts-to-footer.php' ) {
		WP_Mock::expectAction( 'stf_init' );
		$this->expect_function_strict( 'plugin_basename', array( dirname( __DIR__, 3 ) . '/scripts-to-footer.php' ), $basename );
		// Production passes its own directory (__DIR__ of includes/), not a file path.
		$this->expect_function_strict( 'plugin_dir_path', array( dirname( __DIR__, 3 ) . '/includes' ), dirname( __DIR__, 3 ) . '/' );
		WP_Mock::userFunction( 'is_admin', array( 'return' => $is_admin ) );
	}

	/**
	 * Records calls to a write function.
	 *
	 * @param string $func Function name.
	 * @param array  $calls    Reference receiving the argument lists.
	 * @return void
	 */
	private function record( $func, array &$calls ) {
		WP_Mock::userFunction(
			$func,
			array(
				'return' => function ( ...$args ) use ( &$calls ) {
					$calls[] = $args;
					return true;
				},
			)
		);
	}

	/**
	 * Builds the plugin with the upgrade check driven by the given stored version.
	 *
	 * @param mixed $stored Stored stf_version, or false when unset.
	 * @return array{0: Scripts_To_Footer, 1: array, 2: array} Plugin, update_option calls, get_option calls.
	 */
	private function build_admin( $stored ) {
		$this->stub_constructor( true );
		$updates = array();
		$gets    = array();
		$this->record( 'update_option', $updates );
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => function ( ...$args ) use ( &$gets, $stored ) {
					$gets[] = $args;
					return $stored;
				},
			)
		);

		$plugin = new Scripts_To_Footer();

		return array( $plugin, $updates, $gets );
	}

	/**
	 * Construction queues exactly the documented hooks.
	 *
	 * @covers Scripts_To_Footer::__construct
	 * @covers Scripts_To_Footer::define_admin_hooks
	 * @covers Scripts_To_Footer::define_public_hooks
	 * @covers Scripts_To_Footer::set_locale
	 * @covers Scripts_To_Footer::load_dependencies
	 *
	 * @return void
	 */
	public function test_constructor_queues_exact_hooks() {
		$this->stub_constructor();

		$plugin = new Scripts_To_Footer();
		$loader = $plugin->get_loader();

		$summarize = function ( array $hooks ) {
			return array_map(
				function ( $h ) {
					return array( $h['hook'], get_class( $h['component'] ), $h['callback'], $h['priority'], $h['accepted_args'] );
				},
				$hooks
			);
		};

		$this->assertSame(
			array(
				array( 'init', 'STF_I18n', 'load_plugin_textdomain', 10, 1 ),
				array( 'add_meta_boxes', 'STF_Admin', 'metabox_register', 10, 1 ),
				array( 'save_post', 'STF_Admin', 'metabox_save', 1, 2 ),
				array( 'admin_menu', 'STF_Admin_Settings', 'add_plugin_page', 10, 1 ),
				array( 'admin_init', 'STF_Admin_Settings', 'page_init', 10, 1 ),
				array( 'wp_enqueue_scripts', 'STF_Public', 'clean_head', 10, 1 ),
				array( 'wp_head', 'STF_Public', 'set_header_scripts', 1, 1 ),
				array( 'wp_head', 'STF_Public', 'print_head_scripts', 10, 1 ),
			),
			$summarize( $this->get_property( $loader, 'actions' ) )
		);
		$this->assertSame(
			array(
				array( 'plugin_row_meta', 'STF_Admin', 'plugin_links', 10, 2 ),
				array( 'plugin_action_links_scripts-to-footer/scripts-to-footer.php', 'STF_Admin', 'plugin_settings_link', 10, 1 ),
				array( 'stf_include', 'STF_Public', 'stf_includes', 10, 1 ),
			),
			$summarize( $this->get_property( $loader, 'filters' ) )
		);

		// The same Admin instance serves all of its hooks.
		$actions = $this->get_property( $loader, 'actions' );
		$filters = $this->get_property( $loader, 'filters' );
		$this->assertSame( $actions[1]['component'], $actions[2]['component'] );
		$this->assertSame( $actions[1]['component'], $filters[0]['component'] );
		$this->assertSame( $actions[1]['component'], $filters[1]['component'] );
	}

	/**
	 * The action-links hook and the Admin's plugin file follow the real install directory.
	 *
	 * @covers Scripts_To_Footer::__construct
	 * @covers Scripts_To_Footer::define_admin_hooks
	 *
	 * @dataProvider plugin_basenames
	 *
	 * @param string $basename What plugin_basename() reports.
	 * @return void
	 */
	public function test_action_links_hook_uses_plugin_basename( $basename ) {
		$this->stub_constructor( false, $basename );

		$plugin  = new Scripts_To_Footer();
		$filters = $this->get_property( $plugin->get_loader(), 'filters' );

		$this->assertSame( 'plugin_action_links_' . $basename, $filters[1]['hook'] );
		$this->assertSame( 'plugin_settings_link', $filters[1]['callback'] );
		$this->assertSame( $basename, $this->get_property( $filters[1]['component'], 'plugin_file' ) );
	}

	/**
	 * Install directories to cover.
	 *
	 * @return array
	 */
	public static function plugin_basenames() {
		return array(
			'manual install' => array( 'scripts-to-footer/scripts-to-footer.php' ),
			'wordpress.org'  => array( 'scripts-to-footerphp/scripts-to-footer.php' ),
		);
	}

	/**
	 * One STF_Functions instance is shared by every component.
	 *
	 * @covers Scripts_To_Footer::load_dependencies
	 * @covers Scripts_To_Footer::define_admin_hooks
	 * @covers Scripts_To_Footer::define_public_hooks
	 *
	 * @return void
	 */
	public function test_components_share_one_functions_instance() {
		$this->stub_constructor();

		$plugin  = new Scripts_To_Footer();
		$loader  = $plugin->get_loader();
		$actions = $this->get_property( $loader, 'actions' );
		$shared  = $this->get_property( $plugin, 'functions' );

		$this->assertInstanceOf( STF_Functions::class, $shared );
		foreach ( $actions as $hook ) {
			if ( $hook['component'] instanceof STF_I18n ) {
				continue;
			}
			$this->assertSame( $shared, $this->get_property( $hook['component'], 'functions' ), get_class( $hook['component'] ) );
		}

		$classes = array_unique( array_map( 'get_class', array_column( $actions, 'component' ) ) );
		$this->assertContains( 'STF_Admin', $classes );
		$this->assertContains( 'STF_Admin_Settings', $classes );
		$this->assertContains( 'STF_Public', $classes );
	}

	/**
	 * Run() delegates to the loader, adding every hook to WordPress.
	 *
	 * @covers Scripts_To_Footer::run
	 *
	 * @return void
	 */
	public function test_run_registers_hooks_with_wordpress() {
		$this->stub_constructor();

		$plugin = new Scripts_To_Footer();
		$loader = $plugin->get_loader();

		$by_hook = array();
		foreach ( array_merge( $this->get_property( $loader, 'actions' ), $this->get_property( $loader, 'filters' ) ) as $h ) {
			$by_hook[] = $h;
		}

		foreach ( $by_hook as $h ) {
			$is_filter = in_array( $h['hook'], array( 'plugin_row_meta', 'stf_include' ), true ) || 0 === strpos( $h['hook'], 'plugin_action_links_' );
			if ( $is_filter ) {
				WP_Mock::expectFilterAdded( $h['hook'], array( $h['component'], $h['callback'] ), $h['priority'], $h['accepted_args'] );
			} else {
				WP_Mock::expectActionAdded( $h['hook'], array( $h['component'], $h['callback'] ), $h['priority'], $h['accepted_args'] );
			}
		}

		$plugin->run();

		$this->assertCount( 11, $by_hook );
	}

	/**
	 * The getters expose the plugin name, version and loader.
	 *
	 * @covers Scripts_To_Footer::get_plugin_name
	 * @covers Scripts_To_Footer::get_version
	 * @covers Scripts_To_Footer::get_loader
	 *
	 * @return void
	 */
	public function test_getters() {
		$this->stub_constructor();

		$plugin = new Scripts_To_Footer();

		$this->assertSame( 'scripts-to-footer', $plugin->get_plugin_name() );
		$this->assertSame( STF_VERSION, $plugin->get_version() );
		$this->assertInstanceOf( STF_Loader::class, $plugin->get_loader() );
	}

	/**
	 * Outside the admin the upgrade check touches no options.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 *
	 * @return void
	 */
	public function test_upgrade_check_skipped_outside_admin() {
		$this->stub_constructor( false );
		WP_Mock::userFunction( 'get_option', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'update_option', array( 'times' => 0 ) );

		new Scripts_To_Footer();

		$this->assertSame( array(), $this->wpdb->updates );
	}

	/**
	 * With no stored version only the current version is saved.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 *
	 * @return void
	 */
	public function test_upgrade_check_first_install_saves_version_only() {
		list( , $updates, $gets ) = $this->build_admin( false );

		$this->assertSame( array( array( 'stf_version', false ) ), $gets );
		$this->assertSame( array( array( 'stf_version', STF_VERSION, false ) ), $updates );
		$this->assertSame( array( $this->migration_call() ), $this->wpdb->updates );
	}

	/**
	 * An older stored version is recorded as previous, then replaced.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 *
	 * @return void
	 */
	public function test_upgrade_check_older_version_records_previous() {
		list( , $updates ) = $this->build_admin( '0.6.0' );

		$this->assertSame(
			array(
				array( 'stf_previous_version', '0.6.0', false ),
				array( 'stf_version', STF_VERSION, false ),
			),
			$updates
		);
		$this->assertSame( array( $this->migration_call() ), $this->wpdb->updates );
	}

	/**
	 * The same stored version writes nothing.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 *
	 * @return void
	 */
	public function test_upgrade_check_same_version_writes_nothing() {
		list( , $updates, $gets ) = $this->build_admin( STF_VERSION );

		$this->assertSame( array(), $updates );
		$this->assertSame( array( array( 'stf_version', false ) ), $gets );
		$this->assertSame( array(), $this->wpdb->updates );
	}

	/**
	 * The post meta cache group is flushed only when rows changed and the cache supports it.
	 *
	 * @covers Scripts_To_Footer::migrate_legacy_exclude_meta
	 *
	 * @return void
	 */
	public function test_migration_flushes_post_meta_cache_when_rows_changed() {
		$this->wpdb->affected = 3;
		WP_Mock::userFunction(
			'wp_cache_supports',
			array(
				'times'  => 1,
				'args'   => array( 'flush_group' ),
				'return' => true,
			)
		);
		$this->expect_function_strict( 'wp_cache_flush_group', array( 'post_meta' ), true );

		$this->build_admin( false );

		$this->assertSame( array( $this->migration_call() ), $this->wpdb->updates );
	}

	/**
	 * No cache flush when nothing was migrated.
	 *
	 * @covers Scripts_To_Footer::migrate_legacy_exclude_meta
	 *
	 * @return void
	 */
	public function test_migration_does_not_flush_cache_when_no_rows_changed() {
		WP_Mock::userFunction( 'wp_cache_supports', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'wp_cache_flush_group', array( 'times' => 0 ) );

		$this->build_admin( '0.6.0' );

		$this->assertSame( array( $this->migration_call() ), $this->wpdb->updates );
	}

	/**
	 * A database error leaves both version options unwritten so the migration is retried.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 * @covers Scripts_To_Footer::migrate_legacy_exclude_meta
	 *
	 * @return void
	 */
	public function test_failed_migration_does_not_stamp_version() {
		$this->wpdb->affected = false;
		WP_Mock::userFunction( 'wp_cache_supports', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'wp_cache_flush_group', array( 'times' => 0 ) );

		list( , $updates ) = $this->build_admin( '0.6.0' );

		$this->assertSame( array( $this->migration_call() ), $this->wpdb->updates );
		$this->assertSame( array(), $updates );
	}

	/**
	 * Zero rows changed is a success: the version is still stamped.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 * @covers Scripts_To_Footer::migrate_legacy_exclude_meta
	 *
	 * @return void
	 */
	public function test_migration_with_no_rows_still_stamps_version() {
		$this->wpdb->affected = 0;

		list( , $updates ) = $this->build_admin( false );

		$this->assertSame( array( array( 'stf_version', STF_VERSION, false ) ), $updates );
	}

	/**
	 * Rows changed stamps the version and flushes the post meta cache where supported.
	 *
	 * @covers Scripts_To_Footer::upgrade_check
	 * @covers Scripts_To_Footer::migrate_legacy_exclude_meta
	 *
	 * @return void
	 */
	public function test_migration_with_rows_stamps_version_and_flushes_cache() {
		$this->wpdb->affected = 2;
		WP_Mock::userFunction(
			'wp_cache_supports',
			array(
				'times'  => 1,
				'args'   => array( 'flush_group' ),
				'return' => true,
			)
		);
		$this->expect_function_strict( 'wp_cache_flush_group', array( 'post_meta' ), true );

		list( , $updates ) = $this->build_admin( '0.6.0' );

		$this->assertSame(
			array(
				array( 'stf_previous_version', '0.6.0', false ),
				array( 'stf_version', STF_VERSION, false ),
			),
			$updates
		);
	}
}
