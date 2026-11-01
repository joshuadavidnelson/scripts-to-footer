<?php
/**
 * Tests for STF_Admin.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for STF_Admin.
 *
 * @covers STF_Admin
 */
class AdminTest extends TestCase {

	/**
	 * Saved $_POST.
	 *
	 * @var array
	 */
	private $post_backup;

	/**
	 * Saves $_POST.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();
		$this->post_backup = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Restores $_POST.
	 *
	 * @return void
	 */
	protected function tear_down() {
		$_POST = $this->post_backup;
		parent::tear_down();
	}

	/**
	 * Builds the admin object.
	 *
	 * @param string $plugin_file The plugin basename.
	 * @return STF_Admin
	 */
	private function admin( $plugin_file = 'scripts-to-footer/scripts-to-footer.php' ) {
		return new STF_Admin( 'scripts-to-footer', STF_VERSION, $plugin_file );
	}

	/**
	 * Records calls to a function.
	 *
	 * @param string $func Function name.
	 * @param array  $calls    Reference receiving argument lists.
	 * @param mixed  $value   Return value.
	 * @return void
	 */
	private function record( $func, array &$calls, $value = true ) {
		WP_Mock::userFunction(
			$func,
			array(
				'return' => function ( ...$args ) use ( &$calls, $value ) {
					$calls[] = $args;
					return $value;
				},
			)
		);
	}

	/**
	 * Stubs the supported post types filter strictly.
	 *
	 * @param array $reply Filtered post types.
	 * @return void
	 */
	private function stub_post_types( array $reply ) {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), $reply );
	}

	/**
	 * Arranges a valid save request up to the point of the given guard.
	 *
	 * @return void
	 */
	private function valid_nonce() {
		$_POST['stf_nonce'] = 'abc123';
		WP_Mock::userFunction(
			'wp_verify_nonce',
			array(
				'times'  => 1,
				'args'   => array( 'abc123', 'scripts_to_footer' ),
				'return' => 1,
			)
		);
	}

	/**
	 * Stubs write functions and asserts none are called.
	 *
	 * @return void
	 */
	private function expect_no_writes() {
		WP_Mock::userFunction( 'update_post_meta', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'delete_post_meta', array( 'times' => 0 ) );
	}

	/**
	 * Without install_plugins the links are unchanged.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_without_capability() {
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => array( 'install_plugins' ),
				'return' => false,
			)
		);

		$this->assertSame( array( 'x' ), $this->admin()->plugin_links( array( 'x' ), 'scripts-to-footer/scripts-to-footer.php' ) );
	}

	/**
	 * With the capability the plugin's own row gets exactly three extra links.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_appends_three_links() {
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'args'   => array( 'install_plugins' ),
				'return' => true,
			)
		);

		$this->assertSame(
			array(
				'x',
				'<a href="http://wordpress.org/support/plugin/scripts-to-footerphp" title="Support">Support</a>',
				'<a href="https://github.com/joshuadavidnelson/scripts-to-footer/wiki" title="Documentation" target="_blank">Documentation</a>',
				'<a href="http://joshuadnelson.com/donate" title="Donate">Donate</a>',
			),
			$this->admin()->plugin_links( array( 'x' ), 'scripts-to-footer/scripts-to-footer.php' )
		);
	}

	/**
	 * The links show on the wordpress.org install, whose directory is 'scripts-to-footerphp'.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_show_for_wordpress_org_directory() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );

		$file  = 'scripts-to-footerphp/scripts-to-footer.php';
		$links = $this->admin( $file )->plugin_links( array( 'x' ), $file );

		$this->assertCount( 4, $links );
		$this->assertStringContainsString( 'Support', $links[1] );
	}

	/**
	 * Other plugins' rows are untouched, whichever directory this plugin lives in.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_other_plugin_unchanged() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );

		$admin = $this->admin( 'scripts-to-footerphp/scripts-to-footer.php' );

		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), 'akismet/akismet.php' ) );
		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), 'scripts-to-footer/scripts-to-footer.php' ) );
	}

	/**
	 * Built with two arguments, the plugin file is resolved lazily so the links still show.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_two_arg_construction_shows_links() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		$this->expect_function_strict( 'plugin_basename', array( STF_DIR . '/scripts-to-footer.php' ), 'scripts-to-footerphp/scripts-to-footer.php' );

		$admin = new STF_Admin( 'scripts-to-footer', STF_VERSION );
		$links = $admin->plugin_links( array( 'x' ), 'scripts-to-footerphp/scripts-to-footer.php' );

		$this->assertCount( 4, $links );
		$this->assertStringContainsString( 'Support', $links[1] );
	}

	/**
	 * Built with two arguments, other plugins' rows are still untouched.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_two_arg_construction_other_file_unchanged() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'plugin_basename', array( 'return' => 'scripts-to-footer/scripts-to-footer.php' ) );

		$admin = new STF_Admin( 'scripts-to-footer', STF_VERSION );

		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), 'akismet/akismet.php' ) );
		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), '' ) );
	}

	/**
	 * An explicit plugin file is used as given, without asking WordPress to resolve one.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_explicit_file_skips_resolution() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'plugin_basename', array( 'times' => 0 ) );

		$this->assertSame( array( 'x' ), $this->admin()->plugin_links( array( 'x' ), 'akismet/akismet.php' ) );
	}

	/**
	 * The settings link is prepended.
	 *
	 * @covers STF_Admin::plugin_settings_link
	 *
	 * @return void
	 */
	public function test_plugin_settings_link_prepends() {
		$this->assertSame(
			array( '<a href="options-general.php?page=scripts-to-footer">Settings</a>', 'Deactivate' ),
			$this->admin()->plugin_settings_link( array( 'Deactivate' ) )
		);
	}

	/**
	 * Data provider of supported post types.
	 *
	 * @return array
	 */
	public function supported_types() {
		return array(
			'page'   => array( 'page' ),
			'post'   => array( 'post' ),
			'custom' => array( 'book' ),
		);
	}

	/**
	 * The metabox registers for each supported type.
	 *
	 * @covers STF_Admin::metabox_register
	 * @dataProvider supported_types
	 *
	 * @param string $type Post type.
	 * @return void
	 */
	public function test_metabox_register_supported( $type ) {
		$admin = $this->admin();
		WP_Mock::userFunction( 'get_post_type', array( 'return' => $type ) );
		$this->stub_post_types( array( 'page', 'post', 'book' ) );
		$calls = array();
		$this->record( 'add_meta_box', $calls );

		$admin->metabox_register();

		$this->assertSame(
			array( array( 'scripts-to-footer', 'Scripts to Footer Settings', array( $admin, 'metabox_render' ), $type, 'normal', 'high' ) ),
			$calls
		);
	}

	/**
	 * Unsupported types do not get the metabox.
	 *
	 * @covers STF_Admin::metabox_register
	 *
	 * @return void
	 */
	public function test_metabox_register_unsupported_type() {
		WP_Mock::userFunction( 'get_post_type', array( 'return' => 'attachment' ) );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction( 'add_meta_box', array( 'times' => 0 ) );

		$this->admin()->metabox_register();
	}

	/**
	 * A false post type short-circuits before the support lookup.
	 *
	 * @covers STF_Admin::metabox_register
	 *
	 * @return void
	 */
	public function test_metabox_register_false_post_type() {
		WP_Mock::userFunction( 'get_post_type', array( 'return' => false ) );
		WP_Mock::userFunction( 'add_meta_box', array( 'times' => 0 ) );

		$this->admin()->metabox_register();
	}

	/**
	 * Arranges and renders the metabox for a stored meta value.
	 *
	 * @param mixed $stored    Stored meta value.
	 * @param array $updates   Reference receiving update_post_meta calls.
	 * @param array $checked   Reference receiving checked() calls.
	 * @param array $nonces    Reference receiving wp_nonce_field calls.
	 * @return string Output.
	 */
	private function render( $stored, array &$updates, array &$checked, array &$nonces ) {
		WP_Mock::userFunction( 'get_the_ID', array( 'return' => 42 ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'times'  => 1,
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => $stored,
			)
		);
		$this->record( 'update_post_meta', $updates );
		WP_Mock::userFunction(
			'wp_nonce_field',
			array(
				'return' => function ( ...$args ) use ( &$nonces ) {
					$nonces[] = $args;
					echo '[NONCE]';
				},
			)
		);
		WP_Mock::userFunction(
			'checked',
			array(
				'return' => function ( ...$args ) use ( &$checked ) {
					$checked[] = $args;
					return self::core_checked( $args[0], $args[1], false );
				},
			)
		);

		ob_start();
		$this->admin()->metabox_render();

		return ob_get_clean();
	}

	/**
	 * Rendering with the box checked.
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_checked() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( '1', $updates, $checked, $nonces );

		$this->assertSame( array( array( 'scripts_to_footer', 'stf_nonce' ) ), $nonces );
		$this->assertSame( array( array( true, true, false ) ), $checked );
		$this->assertSame( array(), $updates );
		$this->assertSame(
			'[NONCE]<p style="padding-top:10px;"><label for="stf_exclude">Turn Plugin Off</label>'
			. '<input type="checkbox" id="stf_exclude" name="stf_exclude"  checked=\'checked\' style="margin:0 20px 0 10px;">'
			. '<span style="color:#999;">By default, this plugin will run on this post type. This checkbox lets you turn it off for this specific page/post.</span></p>',
			$out
		);
	}

	/**
	 * Rendering with an empty stored value leaves the box unchecked.
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_unchecked() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( '', $updates, $checked, $nonces );

		$this->assertSame( array( array( true, false, false ) ), $checked );
		$this->assertSame( array(), $updates );
		$this->assertStringContainsString( 'name="stf_exclude"  style=', $out );
		$this->assertStringNotContainsString( 'checked=', $out );
	}

	/**
	 * Legacy 'on' values render checked and rendering writes nothing.
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_legacy_on_value_is_checked_without_writing() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( 'on', $updates, $checked, $nonces );

		$this->assertSame( array(), $updates );
		$this->assertSame( array( array( true, true, false ) ), $checked );
		$this->assertStringContainsString( "checked='checked'", $out );
	}

	/**
	 * Any truthy value renders checked, matching STF_Public::stf_includes().
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_other_truthy_values_are_checked() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( 'yes', $updates, $checked, $nonces );

		$this->assertSame( array(), $updates );
		$this->assertSame( array( array( true, true, false ) ), $checked );
		$this->assertStringContainsString( "checked='checked'", $out );
	}

	/**
	 * A missing nonce bails before verification.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_missing_nonce() {
		unset( $_POST['stf_nonce'] );
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'times' => 0 ) );
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * An invalid nonce bails.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_invalid_nonce() {
		$_POST['stf_nonce']   = 'bad';
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction(
			'wp_verify_nonce',
			array(
				'times'  => 1,
				'args'   => array( 'bad', 'scripts_to_footer' ),
				'return' => false,
			)
		);
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * Autosaves bail without writing.
	 *
	 * DOING_AUTOSAVE is a constant, so this one test needs its own process.
	 *
	 * @covers STF_Admin::metabox_save
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_metabox_save_autosave() {
		define( 'DOING_AUTOSAVE', true );
		$this->valid_nonce();
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'times' => 0 ) );
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * DOING_AUTOSAVE defined as false does not bail: the save goes ahead.
	 *
	 * @covers STF_Admin::metabox_save
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_metabox_save_autosave_defined_false_still_saves() {
		define( 'DOING_AUTOSAVE', false );
		$this->valid_nonce();
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'delete_post_meta', array( 'times' => 0 ) );
		$calls = array();
		$this->record( 'update_post_meta', $calls );

		$this->admin()->metabox_save( 7, new stdClass() );

		$this->assertSame( array( array( 7, 'stf_exclude', 1 ) ), $calls );
	}

	/**
	 * AJAX requests bail without writing.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_ajax() {
		$this->valid_nonce();
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'times' => 0 ) );
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * Cron requests bail without writing.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_cron() {
		$this->valid_nonce();
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'times' => 0 ) );
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * Users who cannot edit the post bail without writing.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_without_edit_capability() {
		$this->valid_nonce();
		$_POST['stf_exclude'] = 'on';
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => array( 'edit_post', 7 ),
				'return' => false,
			)
		);
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7, new stdClass() );
	}

	/**
	 * Data provider of submitted exclude values and the int saved.
	 *
	 * @return array
	 */
	public function exclude_values() {
		return array(
			'on'    => array( 'on', 1 ),
			'empty' => array( '', 0 ),
		);
	}

	/**
	 * A submitted value is cast to an int and saved.
	 *
	 * @covers STF_Admin::metabox_save
	 * @dataProvider exclude_values
	 *
	 * @param string $submitted Submitted value.
	 * @param int    $expected  Saved value.
	 * @return void
	 */
	public function test_metabox_save_updates_meta( $submitted, $expected ) {
		$this->valid_nonce();
		$_POST['stf_exclude'] = $submitted;
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'delete_post_meta', array( 'times' => 0 ) );
		$calls = array();
		$this->record( 'update_post_meta', $calls );

		$this->admin()->metabox_save( 7, new stdClass() );

		$this->assertSame( array( array( 7, 'stf_exclude', $expected ) ), $calls );
	}

	/**
	 * An unsubmitted checkbox deletes the meta.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_deletes_meta_when_unchecked() {
		$this->valid_nonce();
		unset( $_POST['stf_exclude'] );
		WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'update_post_meta', array( 'times' => 0 ) );
		$calls = array();
		$this->record( 'delete_post_meta', $calls );

		$this->admin()->metabox_save( 7, new stdClass() );

		$this->assertSame( array( array( 7, 'stf_exclude' ) ), $calls );
	}

	/**
	 * An injected STF_Functions decides which post types get the metabox.
	 *
	 * @covers STF_Admin::__construct
	 * @covers STF_Admin::metabox_register
	 *
	 * @return void
	 */
	public function test_injected_functions_instance_is_used() {
		$functions = Mockery::mock( STF_Functions::class );
		$functions->shouldReceive( 'post_type_supported' )->once()->with( 'secret' )->andReturn( true );

		$admin = new STF_Admin( 'scripts-to-footer', STF_VERSION, '', $functions );
		WP_Mock::userFunction( 'get_post_type', array( 'return' => 'secret' ) );
		$calls = array();
		$this->record( 'add_meta_box', $calls );

		$admin->metabox_register();

		$this->assertSame( $functions, $this->get_property( $admin, 'functions' ) );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'secret', $calls[0][3] );
	}

	/**
	 * Without an injected instance the constructor builds its own.
	 *
	 * @covers STF_Admin::__construct
	 *
	 * @return void
	 */
	public function test_functions_default_when_not_injected() {
		$this->assertInstanceOf( STF_Functions::class, $this->get_property( $this->admin(), 'functions' ) );
	}
}
