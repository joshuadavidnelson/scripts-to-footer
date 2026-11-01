<?php
/**
 * Tests for STF_I18n.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for STF_I18n.
 *
 * @covers STF_I18n
 */
class I18nTest extends TestCase {

	/**
	 * Load_plugin_textdomain() loads the 'scripts-to-footerphp' domain from the languages dir.
	 *
	 * @covers STF_I18n::load_plugin_textdomain
	 *
	 * @return void
	 */
	public function test_load_plugin_textdomain_passes_domain_and_languages_path() {
		WP_Mock::userFunction(
			'plugin_basename',
			array(
				'times'  => 1,
				'args'   => array( dirname( __DIR__, 3 ) . '/includes/class-stf-i18n.php' ),
				'return' => 'scripts-to-footer/includes/class-stf-i18n.php',
			)
		);

		$calls = array();
		WP_Mock::userFunction(
			'load_plugin_textdomain',
			array(
				'times'  => 1,
				'return' => function ( ...$args ) use ( &$calls ) {
					$calls[] = $args;
					return true;
				},
			)
		);

		( new STF_I18n() )->load_plugin_textdomain();

		$this->assertSame(
			array( array( 'scripts-to-footerphp', false, 'scripts-to-footer/languages/' ) ),
			$calls
		);
	}
}
