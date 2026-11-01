<?php
/**
 * Tests for the stf_get_option() helper.
 *
 * @package Scripts_To_Footer
 */

/**
 * Covers stf_get_option().
 *
 * @covers ::stf_get_option
 */
class HelperFunctionsTest extends TestCase {

	/**
	 * Stubs the WordPress string helpers with real-ish behavior.
	 *
	 * @return void
	 */
	private function stub_string_helpers() {
		WP_Mock::userFunction(
			'wp_kses_decode_entities',
			array(
				'return' => function ( $value ) {
					return html_entity_decode( $value );
				},
			)
		);
		WP_Mock::userFunction(
			'stripslashes_deep',
			array(
				'return' => function ( $value ) {
					return is_array( $value ) ? array_map( 'stripslashes', $value ) : stripslashes( $value );
				},
			)
		);
	}

	/**
	 * The default settings field and default value are passed to get_option().
	 *
	 * @return void
	 */
	public function test_uses_default_setting_and_default_value() {
		$this->stub_string_helpers();
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'scripts-to-footer', false )
			->andReturn( array( 'k' => 'v' ) );

		$this->assertSame( 'v', stf_get_option( 'k' ) );
	}

	/**
	 * A custom settings field is used instead of the default, and the default passes through.
	 *
	 * @return void
	 */
	public function test_custom_setting_and_default_value_passed_through() {
		$this->stub_string_helpers();
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'other-setting', 'fallback' )
			->andReturn( array( 'k' => 'custom' ) );

		$this->assertSame( 'custom', stf_get_option( 'k', 'fallback', 'other-setting' ) );
	}

	/**
	 * An empty-string setting falls back to the default settings field.
	 *
	 * @return void
	 */
	public function test_empty_setting_falls_back_to_default() {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'scripts-to-footer', 'd' )
			->andReturn( false );

		$this->assertSame( '', stf_get_option( 'k', 'd', '' ) );
	}

	/**
	 * A non-array option yields an empty string.
	 *
	 * @return void
	 */
	public function test_non_array_option_returns_empty_string() {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'scripts-to-footer', false )
			->andReturn( 'not-an-array' );

		$this->assertSame( '', stf_get_option( 'k' ) );
	}

	/**
	 * A missing key yields an empty string, not the default value.
	 *
	 * @return void
	 */
	public function test_missing_key_returns_empty_string() {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'scripts-to-footer', 'default' )
			->andReturn( array( 'other' => 'x' ) );

		$this->assertSame( '', stf_get_option( 'k', 'default' ) );
	}

	/**
	 * A string value has entities decoded and then slashes stripped.
	 *
	 * @return void
	 */
	public function test_string_value_is_decoded_then_unslashed() {
		$this->stub_string_helpers();
		WP_Mock::userFunction( 'get_option' )
			->once()
			->andReturn( array( 'k' => "O\\'Neil &amp;" ) );

		$this->assertSame( "O'Neil &", stf_get_option( 'k' ) );
	}

	/**
	 * Order matters: an entity that decodes to a backslash is then stripped.
	 *
	 * @return void
	 */
	public function test_decoding_happens_before_stripping_slashes() {
		$this->stub_string_helpers();
		WP_Mock::userFunction( 'get_option' )
			->once()
			->andReturn( array( 'k' => '&#92;n' ) );

		// Characterizes current behavior: decoded backslash is removed by stripslashes().
		$this->assertSame( 'n', stf_get_option( 'k' ) );
	}

	/**
	 * An array value goes through stripslashes_deep() only.
	 *
	 * @return void
	 */
	public function test_array_value_uses_stripslashes_deep() {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->andReturn( array( 'k' => array( "a\\'b", 'c' ) ) );
		WP_Mock::userFunction( 'stripslashes_deep' )
			->once()
			->with( array( "a\\'b", 'c' ) )
			->andReturn( array( "a'b", 'c' ) );

		$this->assertSame( array( "a'b", 'c' ), stf_get_option( 'k' ) );
	}
}
