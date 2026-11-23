<?php
/**
 * Tests for STF_Functions.
 *
 * @package Scripts_To_Footer
 */

/**
 * Covers STF_Functions.
 *
 * @covers STF_Functions
 * @covers STF_Functions::post_types_supported
 * @covers STF_Functions::post_type_supported
 * @covers STF_Functions::taxonomies_supported
 * @covers STF_Functions::tax_supported
 * @covers STF_Functions::log_me
 */
class FunctionsTest extends TestCase {

	/**
	 * Captures error_log() calls instead of writing them anywhere.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->capture_error_log();
	}

	/**
	 * The default post types are page and post, and the filtered value is returned.
	 *
	 * @return void
	 */
	public function test_post_types_supported_returns_filtered_array() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), array( 'page', 'book' ) );

		$this->assertSame( array( 'page', 'book' ), ( new STF_Functions() )->post_types_supported() );
	}

	/**
	 * Without a filter callback the defaults are returned.
	 *
	 * @return void
	 */
	public function test_post_types_supported_default() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), array( 'page', 'post' ) );

		$this->assertSame( array( 'page', 'post' ), ( new STF_Functions() )->post_types_supported() );
	}

	/**
	 * A non-array filter reply yields false.
	 *
	 * @return void
	 */
	public function test_post_types_supported_non_array_is_false() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), 'page' );

		$this->assertFalse( ( new STF_Functions() )->post_types_supported() );
	}

	/**
	 * Data provider for post_type_supported().
	 *
	 * @return array
	 */
	public function provide_post_type_cases() {
		return array(
			'listed'                 => array( array( 'page', 'book' ), 'book', true ),
			'unlisted'               => array( array( 'page', 'book' ), 'post', false ),
			'integer input'          => array( array( 'page', '1' ), 1, false ),
			'integer zero vs string' => array( array( '0' ), 0, false ),
			'null input'             => array( array( 'page', '' ), null, false ),
			'true input vs string'   => array( array( 'page', '1' ), true, false ),
			'array input'            => array( array( 'page' ), array( 'page' ), false ),
			'empty list'             => array( array(), 'page', false ),
			'non-array filter'       => array( 'page', 'page', false ),
			'case sensitive'         => array( array( 'page' ), 'Page', false ),
			'bool in list'           => array( array( 'page', true ), 'book', false ),
			'int in list'            => array( array( 123 ), 123, false ),
		);
	}

	/**
	 * Post_type_supported() uses strict, string-only matching.
	 *
	 * @dataProvider provide_post_type_cases
	 *
	 * @param mixed $filtered The filter reply.
	 * @param mixed $input    The post type passed in.
	 * @param bool  $expected Expected result.
	 * @return void
	 */
	public function test_post_type_supported( $filtered, $input, $expected ) {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), $filtered );

		$this->assertSame( $expected, ( new STF_Functions() )->post_type_supported( $input ) );
	}

	/**
	 * The default taxonomies are category and post_tag, and the filtered value is returned.
	 *
	 * @return void
	 */
	public function test_taxonomies_supported_returns_filtered_array() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), array( 'genre' ) );

		$this->assertSame( array( 'genre' ), ( new STF_Functions() )->taxonomies_supported() );
	}

	/**
	 * Without a filter callback the defaults are returned.
	 *
	 * @return void
	 */
	public function test_taxonomies_supported_default() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), array( 'category', 'post_tag' ) );

		$this->assertSame( array( 'category', 'post_tag' ), ( new STF_Functions() )->taxonomies_supported() );
	}

	/**
	 * A non-array filter reply yields false.
	 *
	 * @return void
	 */
	public function test_taxonomies_supported_non_array_is_false() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), null );

		$this->assertFalse( ( new STF_Functions() )->taxonomies_supported() );
	}

	/**
	 * Data provider for tax_supported().
	 *
	 * @return array
	 */
	public function provide_tax_cases() {
		return array(
			'listed'           => array( array( 'category', 'genre' ), 'genre', true ),
			'unlisted'         => array( array( 'category', 'genre' ), 'post_tag', false ),
			'integer input'    => array( array( '1' ), 1, false ),
			'zero vs string'   => array( array( '0' ), 0, false ),
			'null input'       => array( array( '' ), null, false ),
			'true input'       => array( array( '1' ), true, false ),
			'empty list'       => array( array(), 'category', false ),
			'bool in list'     => array( array( 'category', true ), 'genre', false ),
			'int in list'      => array( array( 123 ), 123, false ),
			'non-array filter' => array( 'category', 'category', false ),
		);
	}

	/**
	 * Tax_supported() uses strict, string-only matching.
	 *
	 * @dataProvider provide_tax_cases
	 *
	 * @param mixed $filtered The filter reply.
	 * @param mixed $input    The taxonomy passed in.
	 * @param bool  $expected Expected result.
	 * @return void
	 */
	public function test_tax_supported( $filtered, $input, $expected ) {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), $filtered );

		$this->assertSame( $expected, ( new STF_Functions() )->tax_supported( $input ) );
	}

	/**
	 * Nothing is logged when WP_DEBUG and STF_DEBUG are undefined.
	 *
	 * @return void
	 */
	public function test_log_me_silent_when_debug_constants_undefined() {
		$this->assertFalse( defined( 'WP_DEBUG' ) );
		$this->assertFalse( defined( 'STF_DEBUG' ) );

		( new STF_Functions() )->log_me( 'hello' );

		$this->assertSame( array(), $this->logged_errors );
	}

	/**
	 * Strings are logged with the plugin prefix when both debug constants are true.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_log_me_logs_string_when_both_debug_on() {
		define( 'WP_DEBUG', true );
		define( 'STF_DEBUG', true );

		( new STF_Functions() )->log_me( 'msg' );

		$this->assertSame( array( 'Scripts-to-Footer Plugin Error: msg' ), $this->logged_errors );
	}

	/**
	 * Arrays are logged in print_r form.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_log_me_logs_array_with_print_r() {
		define( 'WP_DEBUG', true );
		define( 'STF_DEBUG', true );

		( new STF_Functions() )->log_me( array( 'a' => 1 ) );

		$expected = "Scripts-to-Footer Plugin Error: Array\n(\n    [a] => 1\n)\n";
		$this->assertSame( array( $expected ), $this->logged_errors );
	}

	/**
	 * Objects are logged in print_r form too.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_log_me_logs_object_with_print_r() {
		define( 'WP_DEBUG', true );
		define( 'STF_DEBUG', true );

		$obj    = new stdClass();
		$obj->x = 'y';
		( new STF_Functions() )->log_me( $obj );

		$expected = "Scripts-to-Footer Plugin Error: stdClass Object\n(\n    [x] => y\n)\n";
		$this->assertSame( array( $expected ), $this->logged_errors );
	}

	/**
	 * Nothing is logged when only WP_DEBUG is on.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_log_me_silent_when_stf_debug_false() {
		define( 'WP_DEBUG', true );
		define( 'STF_DEBUG', false );

		( new STF_Functions() )->log_me( 'msg' );

		$this->assertSame( array(), $this->logged_errors );
	}

	/**
	 * Nothing is logged when only STF_DEBUG is on.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_log_me_silent_when_wp_debug_false() {
		define( 'WP_DEBUG', false );
		define( 'STF_DEBUG', true );

		( new STF_Functions() )->log_me( 'msg' );

		$this->assertSame( array(), $this->logged_errors );
	}
}
