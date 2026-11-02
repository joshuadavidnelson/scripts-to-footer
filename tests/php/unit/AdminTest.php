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
	 * The metabox render tests stub esc_html__() with a marker, so it has no passthrough.
	 *
	 * @var string[]
	 */
	protected $own_i18n_stubs = array( 'esc_html__' );

	/**
	 * Clears $_POST after each test, so submitted data never leaks into the next one.
	 *
	 * $_POST is empty at the start of every run, so resetting is the same as restoring.
	 *
	 * @return void
	 */
	protected function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Builds the admin object.
	 *
	 * @return STF_Admin
	 */
	private function admin() {
		return new STF_Admin( 'scripts-to-footer', STF_VERSION );
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
	 * Arranges a posted nonce that is read the way WordPress requires.
	 *
	 * The nonce must be unslashed, then sanitized, then verified. Each step returns a
	 * distinct value, so a skipped or reordered step fails the exact-args assertions.
	 *
	 * @param bool|int $verified What wp_verify_nonce() returns.
	 * @return void
	 */
	private function posted_nonce( $verified ) {
		$_POST['stf_nonce'] = 'posted\\-nonce';
		$this->expect_function_strict( 'wp_unslash', array( 'posted\\-nonce' ), 'unslashed-nonce' );
		$this->expect_function_strict( 'sanitize_text_field', array( 'unslashed-nonce' ), 'clean-nonce' );
		$this->expect_function_strict( 'wp_verify_nonce', array( 'clean-nonce', 'scripts_to_footer' ), $verified );
	}

	/**
	 * Arranges a valid save request up to the point of the given guard.
	 *
	 * @return void
	 */
	private function valid_nonce() {
		$this->posted_nonce( 1 );
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
	 * Other plugins' rows are untouched, including the wordpress.org slug directory.
	 *
	 * Characterizes current behavior: on wordpress.org the plugin directory is
	 * 'scripts-to-footerphp', which never equals 'scripts-to-footer', so the links
	 * never show there.
	 *
	 * @covers STF_Admin::plugin_links
	 *
	 * @return void
	 */
	public function test_plugin_links_other_plugin_unchanged() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );

		$admin = $this->admin();

		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), 'akismet/akismet.php' ) );
		$this->assertSame( array( 'x' ), $admin->plugin_links( array( 'x' ), 'scripts-to-footerphp/scripts-to-footer.php' ) );
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
			'esc_html__',
			array(
				'return' => function ( ...$args ) {
					return 'HTML(' . implode( '|', $args ) . ')';
				},
			)
		);
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
		$this->assertSame( array( array( true, '1', false ) ), $checked );
		$this->assertSame( array(), $updates );
		$this->assertSame(
			'[NONCE]<p style="padding-top:10px;"><label for="stf_exclude">HTML(Turn Plugin Off|scripts-to-footerphp)</label>'
			. '<input type="checkbox" id="stf_exclude" name="stf_exclude"  checked=\'checked\' style="margin:0 20px 0 10px;">'
			. '<span style="color:#999;">HTML(By default, this plugin will run on this post type. This checkbox lets you turn it off for this specific page/post.|scripts-to-footerphp)</span></p>',
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

		$this->assertSame( array( array( true, '', false ) ), $checked );
		$this->assertSame( array(), $updates );
		$this->assertStringContainsString( 'name="stf_exclude"  style=', $out );
		$this->assertStringNotContainsString( 'checked=', $out );
	}

	/**
	 * Legacy 'on' values are migrated while rendering.
	 *
	 * Characterizes current behavior: rendering the metabox writes post meta.
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_migrates_legacy_on_value() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( 'on', $updates, $checked, $nonces );

		$this->assertSame( array( array( 42, 'stf_exclude', 1, 'on' ) ), $updates );
		$this->assertSame( array( array( true, 1, false ) ), $checked );
		$this->assertStringContainsString( "checked='checked'", $out );
	}

	/**
	 * A non-'on' truthy value is not migrated.
	 *
	 * Characterizes current behavior: a truthy non-'1' meta value excludes the post in
	 * stf_includes but renders the box unchecked (core's checked( true, 'yes' ) is '').
	 *
	 * @covers STF_Admin::metabox_render
	 *
	 * @return void
	 */
	public function test_metabox_render_other_values_not_migrated() {
		$updates = array();
		$checked = array();
		$nonces  = array();
		$out     = $this->render( 'yes', $updates, $checked, $nonces );

		$this->assertSame( array(), $updates );
		$this->assertSame( array( array( true, 'yes', false ) ), $checked );
		$this->assertStringNotContainsString( 'checked=', $out );
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
		WP_Mock::userFunction( 'wp_unslash', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'times' => 0 ) );
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7 );
	}

	/**
	 * An invalid nonce bails.
	 *
	 * @covers STF_Admin::metabox_save
	 *
	 * @return void
	 */
	public function test_metabox_save_invalid_nonce() {
		$this->posted_nonce( false );
		$_POST['stf_exclude'] = 'on';
		$this->expect_no_writes();

		$this->admin()->metabox_save( 7 );
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

		$this->admin()->metabox_save( 7 );
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

		$this->admin()->metabox_save( 7 );

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

		$this->admin()->metabox_save( 7 );
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

		$this->admin()->metabox_save( 7 );
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

		$this->admin()->metabox_save( 7 );
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

		$this->admin()->metabox_save( 7 );

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

		$this->admin()->metabox_save( 7 );

		$this->assertSame( array( array( 7, 'stf_exclude' ) ), $calls );
	}
}
