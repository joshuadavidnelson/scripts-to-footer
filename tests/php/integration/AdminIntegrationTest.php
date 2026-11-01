<?php
/**
 * Integration tests for the admin area.
 *
 * @package Scripts_To_Footer
 */

/**
 * Admin integration tests.
 *
 * @covers STF_Admin
 * @covers STF_Admin_Settings
 * @covers Scripts_To_Footer
 */
class AdminIntegrationTest extends WP_UnitTestCase {

	/**
	 * The screen before the test.
	 *
	 * @var mixed
	 */
	private $previous_screen;

	/**
	 * Save the global state this test changes.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
	}

	/**
	 * Restore the global state.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_POST = array();
		wp_set_current_user( 0 );
		// WordPress before 6.3 warns when unregistering a setting whose group was never
		// registered, so only unregister what a test actually registered.
		if ( isset( get_registered_settings()['scripts-to-footer'] ) ) {
			unregister_setting( 'scripts-to-footer', 'scripts-to-footer' );
		}

		if ( null === $this->previous_screen ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $this->previous_screen;
		}

		parent::tear_down();
	}

	/**
	 * Create a post authored by an administrator.
	 *
	 * @return int
	 */
	private function create_post() {
		return self::factory()->post->create(
			array( 'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ) )
		);
	}

	/**
	 * Create an admin helper object.
	 *
	 * @return STF_Admin
	 */
	private function admin() {
		return new STF_Admin( STF_PLUGIN_NAME, STF_VERSION );
	}

	/**
	 * Log in a user with a role.
	 *
	 * @param string $role The role.
	 * @return void
	 */
	private function login_as( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Save with a valid nonce stores an integer flag.
	 *
	 * @return void
	 */
	public function test_metabox_save_stores_exclude_flag() {
		$post_id = $this->create_post();
		$this->login_as( 'editor' );
		$_POST = array(
			'stf_nonce'   => wp_create_nonce( 'scripts_to_footer' ),
			'stf_exclude' => 'on',
		);

		$this->admin()->metabox_save( $post_id );

		$this->assertSame( '1', get_post_meta( $post_id, 'stf_exclude', true ) );
		$this->assertSame( array( '1' ), get_post_custom_values( 'stf_exclude', $post_id ) );
	}

	/**
	 * Save without the checkbox deletes the meta.
	 *
	 * @return void
	 */
	public function test_metabox_save_deletes_flag_when_unchecked() {
		$post_id = $this->create_post();
		update_post_meta( $post_id, 'stf_exclude', 1 );
		$this->login_as( 'editor' );
		$_POST = array( 'stf_nonce' => wp_create_nonce( 'scripts_to_footer' ) );

		$this->admin()->metabox_save( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'stf_exclude' ) );
	}

	/**
	 * A user who cannot edit the post cannot change the flag.
	 *
	 * @return void
	 */
	public function test_metabox_save_requires_edit_capability() {
		$post_id = $this->create_post();
		$this->login_as( 'subscriber' );
		$_POST = array(
			'stf_nonce'   => wp_create_nonce( 'scripts_to_footer' ),
			'stf_exclude' => '1',
		);

		$this->admin()->metabox_save( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'stf_exclude' ) );

		update_post_meta( $post_id, 'stf_exclude', 1 );
		unset( $_POST['stf_exclude'] );
		$this->admin()->metabox_save( $post_id );
		$this->assertSame( '1', get_post_meta( $post_id, 'stf_exclude', true ), 'A subscriber must not delete the flag.' );
	}

	/**
	 * An invalid or missing nonce cannot change the flag.
	 *
	 * @dataProvider bad_nonce_provider
	 *
	 * @param array $post The POST data.
	 * @return void
	 */
	public function test_metabox_save_requires_valid_nonce( $post ) {
		$post_id = $this->create_post();
		$this->login_as( 'administrator' );
		$_POST = $post;

		$this->admin()->metabox_save( $post_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'stf_exclude' ) );

		update_post_meta( $post_id, 'stf_exclude', 1 );
		unset( $_POST['stf_exclude'] );
		$this->admin()->metabox_save( $post_id );
		$this->assertSame( '1', get_post_meta( $post_id, 'stf_exclude', true ), 'A bad nonce must not delete the flag.' );
	}

	/**
	 * POST data without a valid nonce.
	 *
	 * @return array<string, array{0: array}>
	 */
	public function bad_nonce_provider() {
		return array(
			'invalid nonce' => array(
				array(
					'stf_nonce'   => 'invalid',
					'stf_exclude' => '1',
				),
			),
			'missing nonce' => array( array( 'stf_exclude' => '1' ) ),
			'wrong action'  => array(
				array(
					'stf_nonce'   => wp_create_nonce( 'other_action' ),
					'stf_exclude' => '1',
				),
			),
		);
	}

	/**
	 * Saving a post through WordPress runs the plugin's save_post handler.
	 *
	 * @return void
	 */
	public function test_save_post_hook_saves_exclude_flag() {
		$post_id = $this->create_post();
		$this->login_as( 'administrator' );
		$_POST = array(
			'stf_nonce'   => wp_create_nonce( 'scripts_to_footer' ),
			'stf_exclude' => '1',
		);

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated',
			)
		);

		$this->assertSame( '1', get_post_meta( $post_id, 'stf_exclude', true ) );
	}

	/**
	 * The settings sanitize callback is applied when the option is saved.
	 *
	 * @return void
	 */
	public function test_settings_are_sanitized_on_update() {
		$settings = new STF_Admin_Settings();
		$settings->page_init();

		update_option(
			'scripts-to-footer',
			array(
				'stf_exclude_home' => '7abc',
				'junk'             => 1,
			)
		);

		$this->assertSame( array( 'stf_exclude_home' => 7 ), get_option( 'scripts-to-footer' ) );
	}

	/**
	 * Admin init is wired to the settings registration.
	 *
	 * @return void
	 */
	public function test_admin_init_registers_setting() {
		// WordPress before 6.3 warns when unregistering a setting whose group was never
		// registered, so only unregister what a test actually registered.
		if ( isset( get_registered_settings()['scripts-to-footer'] ) ) {
			unregister_setting( 'scripts-to-footer', 'scripts-to-footer' );
		}
		$this->assertArrayNotHasKey( 'scripts-to-footer', get_registered_settings() );

		$found = false;
		foreach ( $GLOBALS['wp_filter']['admin_init']->callbacks[10] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof STF_Admin_Settings && 'page_init' === $callback['function'][1] ) {
				$callback['function'][0]->page_init();
				$found = true;
			}
		}

		$this->assertTrue( $found, 'page_init should be hooked to admin_init.' );
		$this->assertArrayHasKey( 'scripts-to-footer', get_registered_settings() );
	}

	/**
	 * Upgrading records the previous version and the current one in the admin.
	 *
	 * @return void
	 */
	public function test_upgrade_check_records_versions_in_admin() {
		set_current_screen( 'dashboard' );
		$this->assertTrue( is_admin() );
		update_option( 'stf_version', '0.1.0' );

		new Scripts_To_Footer();

		$this->assertSame( STF_VERSION, get_option( 'stf_version' ) );
		$this->assertSame( '0.1.0', get_option( 'stf_previous_version' ) );
	}

	/**
	 * A fresh install records the version without a previous version.
	 *
	 * @return void
	 */
	public function test_upgrade_check_records_version_on_fresh_install() {
		set_current_screen( 'dashboard' );
		delete_option( 'stf_version' );
		delete_option( 'stf_previous_version' );

		new Scripts_To_Footer();

		$this->assertSame( STF_VERSION, get_option( 'stf_version' ) );
		$this->assertFalse( get_option( 'stf_previous_version' ) );
	}

	/**
	 * The upgrade check does nothing on the front end.
	 *
	 * @return void
	 */
	public function test_upgrade_check_skips_front_end() {
		set_current_screen( 'front' );
		$this->assertFalse( is_admin() );
		update_option( 'stf_version', '0.1.0' );

		new Scripts_To_Footer();

		$this->assertSame( '0.1.0', get_option( 'stf_version' ) );
		$this->assertFalse( get_option( 'stf_previous_version' ) );
	}

	/**
	 * Upgrading converts legacy 'on' exclusion values to '1'.
	 *
	 * @return void
	 */
	public function test_upgrade_migrates_legacy_on_exclude_meta() {
		$post_id = $this->create_post();
		update_post_meta( $post_id, 'stf_exclude', 'on' );
		update_option( 'stf_version', '0.1.0' );
		set_current_screen( 'dashboard' );
		$this->assertTrue( is_admin() );

		new Scripts_To_Footer();

		$this->assertSame( '1', get_post_meta( $post_id, 'stf_exclude', true ) );
	}

	/**
	 * Rendering the metabox for a legacy 'on' value checks the box without writing.
	 *
	 * @return void
	 */
	public function test_metabox_render_legacy_on_checked_without_write() {
		$post_id = $this->create_post();
		update_post_meta( $post_id, 'stf_exclude', 'on' );
		$GLOBALS['post'] = get_post( $post_id );

		ob_start();
		$this->admin()->metabox_render();
		$out = ob_get_clean();

		unset( $GLOBALS['post'] );

		$this->assertStringContainsString( "checked='checked'", $out );
		$this->assertSame( 'on', get_post_meta( $post_id, 'stf_exclude', true ) );
	}
}
