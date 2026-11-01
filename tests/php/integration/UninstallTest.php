<?php
/**
 * Integration tests for uninstall.php.
 *
 * @package Scripts_To_Footer
 */

/**
 * Uninstall integration tests.
 *
 * @coversNothing
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Uninstalling removes the plugin options and all exclude post meta.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_options_and_post_meta() {
		$post_ids = self::factory()->post->create_many( 2 );
		update_option( 'scripts-to-footer', array( 'stf_exclude_home' => '1' ) );
		update_option( 'stf_version', '0.7.4' );
		update_post_meta( $post_ids[0], 'stf_exclude', '1' );
		update_post_meta( $post_ids[1], 'stf_exclude', 'on' );
		update_post_meta( $post_ids[1], 'unrelated_key', 'keep' );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		$this->assertTrue( current_user_can( 'install_plugins' ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'scripts-to-footer/scripts-to-footer.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant.
		}

		include dirname( __DIR__, 3 ) . '/uninstall.php';

		$this->assertFalse( get_option( 'scripts-to-footer' ) );
		$this->assertFalse( get_option( 'stf_version' ) );
		$this->assertSame( '', get_post_meta( $post_ids[0], 'stf_exclude', true ) );
		$this->assertSame( '', get_post_meta( $post_ids[1], 'stf_exclude', true ) );
		$this->assertSame( 'keep', get_post_meta( $post_ids[1], 'unrelated_key', true ) );
	}
}
