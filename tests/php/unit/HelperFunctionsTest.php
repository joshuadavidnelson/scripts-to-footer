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
	 * A port of core's wp_kses_decode_entities(): only numeric entities are decoded, via chr().
	 *
	 * Named entities such as &amp; are left alone, exactly as in WordPress.
	 *
	 * @param string $content The content.
	 * @return string
	 */
	private static function core_kses_decode_entities( $content ) {
		$content = preg_replace_callback(
			'/&#([0-9]+);/',
			function ( $found ) {
				return chr( $found[1] );
			},
			$content
		);

		return preg_replace_callback(
			'/&#[Xx]([0-9A-Fa-f]+);/',
			function ( $found ) {
				return chr( hexdec( $found[1] ) );
			},
			$content
		);
	}

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
					return self::core_kses_decode_entities( $value );
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
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', false ), array( 'k' => 'v' ) );

		$this->assertSame( 'v', stf_get_option( 'k' ) );
	}

	/**
	 * A custom settings field is used instead of the default, and the default passes through.
	 *
	 * @return void
	 */
	public function test_custom_setting_and_default_value_passed_through() {
		$this->stub_string_helpers();
		$this->expect_function_strict( 'get_option', array( 'other-setting', 'fallback' ), array( 'k' => 'custom' ) );

		$this->assertSame( 'custom', stf_get_option( 'k', 'fallback', 'other-setting' ) );
	}

	/**
	 * An empty-string setting falls back to the default settings field.
	 *
	 * @return void
	 */
	public function test_empty_setting_falls_back_to_default() {
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', 'd' ), false );

		$this->assertSame( '', stf_get_option( 'k', 'd', '' ) );
	}

	/**
	 * A non-array option yields an empty string.
	 *
	 * @return void
	 */
	public function test_non_array_option_returns_empty_string() {
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', false ), 'not-an-array' );

		$this->assertSame( '', stf_get_option( 'k' ) );
	}

	/**
	 * A missing key yields an empty string, not the default value.
	 *
	 * @return void
	 */
	public function test_missing_key_returns_empty_string() {
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', 'default' ), array( 'other' => 'x' ) );

		$this->assertSame( '', stf_get_option( 'k', 'default' ) );
	}

	/**
	 * A string value has entities decoded and then slashes stripped.
	 *
	 * @return void
	 */
	public function test_string_value_is_decoded_then_unslashed() {
		$this->stub_string_helpers();
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', false ), array( 'k' => "O\\'Neil &#38; &#x26; &amp;" ) );

		// Core decodes only numeric entities, so the named &amp; survives.
		$this->assertSame( "O'Neil & & &amp;", stf_get_option( 'k' ) );
	}

	/**
	 * Order matters: an entity that decodes to a backslash is then stripped.
	 *
	 * @return void
	 */
	public function test_decoding_happens_before_stripping_slashes() {
		$this->stub_string_helpers();
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', false ), array( 'k' => '&#92;n' ) );

		// Characterizes current behavior: decoded backslash is removed by stripslashes().
		$this->assertSame( 'n', stf_get_option( 'k' ) );
	}

	/**
	 * An array value goes through stripslashes_deep() only.
	 *
	 * @return void
	 */
	public function test_array_value_uses_stripslashes_deep() {
		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', false ), array( 'k' => array( "a\\'b", 'c' ) ) );
		WP_Mock::userFunction( 'stripslashes_deep' )
			->once()
			->with( array( "a\\'b", 'c' ) )
			->andReturn( array( "a'b", 'c' ) );

		$this->assertSame( array( "a'b", 'c' ), stf_get_option( 'k' ) );
	}
}
