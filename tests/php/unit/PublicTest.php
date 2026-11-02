<?php
/**
 * Tests for STF_Public.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for STF_Public.
 *
 * @covers STF_Public
 */
class PublicTest extends TestCase {

	/**
	 * The ordered conditional tags stf_includes() walks through.
	 *
	 * @var string[]
	 */
	private static $conditional_tags = array(
		'is_singular',
		'is_page',
		'is_home',
		'is_search',
		'is_404',
		'is_author',
		'is_category',
		'is_tag',
		'is_tax',
		'is_post_type_archive',
		'is_archive',
	);

	/**
	 * Builds the object under test.
	 *
	 * @return STF_Public
	 */
	private function make_public() {
		return new STF_Public( 'scripts-to-footer', '1.2.3' );
	}

	/**
	 * Stubs every conditional tag, returning true only for $true_tags (or none).
	 *
	 * @param string|string[]|null $true_tags The conditional tag(s) that return true.
	 * @return void
	 */
	private function stub_conditionals( $true_tags = null ) {
		$true_tags = (array) $true_tags;
		foreach ( self::$conditional_tags as $tag ) {
			WP_Mock::userFunction( $tag, array( 'return' => in_array( $tag, $true_tags, true ) ) );
		}
	}

	/**
	 * Stubs absint() as WordPress does, since the unit suite doesn't load WordPress.
	 *
	 * @return void
	 */
	private function stub_absint() {
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
	}

	/**
	 * Stubs the option read behind stf_get_option().
	 *
	 * @param mixed $options The stored 'scripts-to-footer' option value.
	 * @return void
	 */
	private function stub_options( $options ) {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( 'scripts-to-footer', false ),
				'return' => $options,
			)
		);
		WP_Mock::userFunction( 'wp_kses_decode_entities', array( 'return_arg' => 0 ) );
	}

	/**
	 * Arranges a singular request that reaches the post type support check.
	 *
	 * @param string $tag       Which of is_singular / is_page is true.
	 * @param int    $id        The queried object id.
	 * @param mixed  $post_type The post type get_post_type() returns.
	 * @return void
	 */
	private function arrange_singular( $tag, $id, $post_type ) {
		$this->stub_conditionals( $tag );
		$this->stub_absint();
		WP_Mock::userFunction( 'get_queried_object_id', array( 'return' => $id ) );
		WP_Mock::userFunction(
			'get_post_type',
			array(
				'args'   => array( $id ),
				'return' => $post_type,
			)
		);
	}

	/**
	 * Stubs the supported post types filter.
	 *
	 * @param mixed $reply Supported post types the filter returns.
	 * @return void
	 */
	private function stub_post_types( $reply ) {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), $reply );
	}

	/**
	 * Stubs the supported taxonomies filter.
	 *
	 * @param mixed $reply Supported taxonomies the filter returns.
	 * @return void
	 */
	private function stub_taxonomies( $reply ) {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), $reply );
	}

	// -------------------------------------------------------------------------
	// Constructor.
	// -------------------------------------------------------------------------

	/**
	 * The constructor stores the plugin name and version and builds STF_Functions.
	 *
	 * @covers STF_Public::__construct
	 *
	 * @return void
	 */
	public function test_constructor_sets_properties() {
		$public = $this->make_public();

		$this->assertSame( 'scripts-to-footer', $this->get_property( $public, 'plugin_name' ) );
		$this->assertSame( '1.2.3', $this->get_property( $public, 'version' ) );
		$this->assertInstanceOf( STF_Functions::class, $this->get_property( $public, 'functions' ) );
		$this->assertNull( $this->get_property( $public, 'header_scripts' ) );
	}

	// -------------------------------------------------------------------------
	// is_included().
	// -------------------------------------------------------------------------

	/**
	 * Provides stf_include replies and their bool casts.
	 *
	 * @return array
	 */
	public function provide_include_replies() {
		return array(
			'true'          => array( true, true ),
			'false'         => array( false, false ),
			'int one'       => array( 1, true ),
			'int zero'      => array( 0, false ),
			'empty string'  => array( '', false ),
			'string yes'    => array( 'yes', true ),
			'string zero'   => array( '0', false ),
			'null'          => array( null, false ),
			'empty array'   => array( array(), false ),
			'non-empty arr' => array( array( 1 ), true ),
		);
	}

	/**
	 * Is_included() passes true as the default and casts the reply to bool.
	 *
	 * @covers STF_Public::is_included
	 * @dataProvider provide_include_replies
	 *
	 * @param mixed $reply    The stf_include filter reply.
	 * @param bool  $expected The expected result.
	 * @return void
	 */
	public function test_is_included_casts_filter_result_to_bool( $reply, $expected ) {
		$this->stub_filter_strict( 'stf_include', true, $reply );

		$this->assertSame( $expected, $this->make_public()->is_included() );
	}

	// -------------------------------------------------------------------------
	// clean_head().
	// -------------------------------------------------------------------------

	/**
	 * Clean_head() does nothing in the admin, not even asking the include filter.
	 *
	 * @covers STF_Public::clean_head
	 *
	 * @return void
	 */
	public function test_clean_head_bails_in_admin() {
		WP_Mock::userFunction( 'is_admin', array( 'return' => true ) );
		WP_Mock::userFunction( 'remove_action', array( 'times' => 0 ) );

		$this->make_public()->clean_head();
	}

	/**
	 * Clean_head() removes exactly the three core script hooks when included.
	 *
	 * @covers STF_Public::clean_head
	 * @covers STF_Public::is_included
	 * @dataProvider provide_truthy_include_replies
	 *
	 * @param mixed $reply The stf_include filter reply (cast to true).
	 * @return void
	 */
	public function test_clean_head_removes_script_hooks_when_included( $reply ) {
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		$this->stub_filter_strict( 'stf_include', true, $reply );

		WP_Mock::userFunction(
			'remove_action',
			array(
				'args'  => array( 'wp_head', 'wp_print_scripts' ),
				'times' => 1,
			)
		);
		WP_Mock::userFunction(
			'remove_action',
			array(
				'args'  => array( 'wp_head', 'wp_print_head_scripts', 9 ),
				'times' => 1,
			)
		);
		WP_Mock::userFunction(
			'remove_action',
			array(
				'args'  => array( 'wp_head', 'wp_enqueue_scripts', 1 ),
				'times' => 1,
			)
		);

		$this->make_public()->clean_head();
	}

	/**
	 * Provides truthy stf_include replies.
	 *
	 * @return array
	 */
	public function provide_truthy_include_replies() {
		return array(
			'true'       => array( true ),
			'int one'    => array( 1 ),
			'string yes' => array( 'yes' ),
		);
	}

	/**
	 * Clean_head() removes nothing when the page is not included.
	 *
	 * @covers STF_Public::clean_head
	 * @covers STF_Public::is_included
	 * @dataProvider provide_falsy_include_replies
	 *
	 * @param mixed $reply The stf_include filter reply (cast to false).
	 * @return void
	 */
	public function test_clean_head_removes_nothing_when_not_included( $reply ) {
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		$this->stub_filter_strict( 'stf_include', true, $reply );
		WP_Mock::userFunction( 'remove_action', array( 'times' => 0 ) );

		$this->make_public()->clean_head();
	}

	/**
	 * Provides falsy stf_include replies.
	 *
	 * @return array
	 */
	public function provide_falsy_include_replies() {
		return array(
			'false'        => array( false ),
			'int zero'     => array( 0 ),
			'empty string' => array( '' ),
			'null'         => array( null ),
		);
	}

	// -------------------------------------------------------------------------
	// set_header_scripts().
	// -------------------------------------------------------------------------

	/**
	 * Provides stf_jquery_header option values and the resulting head scripts.
	 *
	 * @return array
	 */
	public function provide_jquery_header_options() {
		return array(
			'int one'          => array( array( 'stf_jquery_header' => 1 ), array( 'jquery' ) ),
			'string one'       => array( array( 'stf_jquery_header' => '1' ), array( 'jquery' ) ),
			'string on'        => array( array( 'stf_jquery_header' => 'on' ), array( 'jquery' ) ),
			'zero'             => array( array( 'stf_jquery_header' => 0 ), array() ),
			'empty string'     => array( array( 'stf_jquery_header' => '' ), array() ),
			'missing key'      => array( array( 'other' => 1 ), array() ),
			'option not array' => array( false, array() ),
		);
	}

	/**
	 * Set_header_scripts() seeds jquery from the option and stores the filtered list.
	 *
	 * @covers STF_Public::set_header_scripts
	 * @dataProvider provide_jquery_header_options
	 *
	 * @param mixed $options  The stored option.
	 * @param array $expected The list handed to stf_exclude_scripts.
	 * @return void
	 */
	public function test_set_header_scripts_seeds_jquery_from_option( $options, $expected ) {
		$this->stub_options( $options );
		$this->stub_filter_strict( 'stf_exclude_scripts', $expected, array( 'custom', 'other' ) );

		$public = $this->make_public();
		$public->set_header_scripts();

		$this->assertSame( array( 'custom', 'other' ), $this->get_property( $public, 'header_scripts' ) );
	}

	/**
	 * Set_header_scripts() stores whatever the filter returns, even a non-array.
	 *
	 * @covers STF_Public::set_header_scripts
	 *
	 * @return void
	 */
	public function test_set_header_scripts_stores_filter_reply_verbatim() {
		$this->stub_options( array() );
		$this->stub_filter_strict( 'stf_exclude_scripts', array(), 'jquery' );

		$public = $this->make_public();
		$public->set_header_scripts();

		$this->assertSame( 'jquery', $this->get_property( $public, 'header_scripts' ) );
	}

	// -------------------------------------------------------------------------
	// print_head_scripts().
	// -------------------------------------------------------------------------

	/**
	 * Provides header_scripts values that make print_head_scripts() return early.
	 *
	 * @return array
	 */
	public function provide_unprintable_header_scripts() {
		return array(
			'unset (null)'     => array( null ),
			'empty array'      => array( array() ),
			'non-empty string' => array( 'jquery' ),
			'false'            => array( false ),
			'int'              => array( 5 ),
		);
	}

	/**
	 * Print_head_scripts() returns before asking the include filter.
	 *
	 * @covers STF_Public::print_head_scripts
	 * @dataProvider provide_unprintable_header_scripts
	 *
	 * @param mixed $header_scripts The stored header scripts.
	 * @return void
	 */
	public function test_print_head_scripts_returns_early_without_usable_scripts( $header_scripts ) {
		WP_Mock::userFunction( 'wp_script_is', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'wp_print_scripts', array( 'times' => 0 ) );

		$public = $this->make_public();
		$this->set_property( $public, 'header_scripts', $header_scripts );

		// Strict mode fails if the stf_include filter is reached without a stub.
		$public->print_head_scripts();
	}

	/**
	 * Print_head_scripts() prints nothing when the page is not included.
	 *
	 * @covers STF_Public::print_head_scripts
	 *
	 * @return void
	 */
	public function test_print_head_scripts_prints_nothing_when_not_included() {
		$this->stub_filter_strict( 'stf_include', true, false );
		WP_Mock::userFunction( 'wp_script_is', array( 'times' => 0 ) );
		WP_Mock::userFunction( 'wp_print_scripts', array( 'times' => 0 ) );

		$public = $this->make_public();
		$this->set_property( $public, 'header_scripts', array( 'jquery' ) );

		$public->print_head_scripts();
	}

	/**
	 * Print_head_scripts() prints only enqueued string handles when included.
	 *
	 * @covers STF_Public::print_head_scripts
	 *
	 * @return void
	 */
	public function test_print_head_scripts_prints_only_enqueued_string_handles() {
		$this->stub_filter_strict( 'stf_include', true, true );

		WP_Mock::userFunction(
			'wp_script_is',
			array(
				'args'   => array( 'jquery' ),
				'times'  => 1,
				'return' => true,
			)
		);
		WP_Mock::userFunction(
			'wp_script_is',
			array(
				'args'   => array( 'not-enqueued' ),
				'times'  => 1,
				'return' => false,
			)
		);
		WP_Mock::userFunction(
			'wp_script_is',
			array(
				'args'   => array( 'theme' ),
				'times'  => 1,
				'return' => true,
			)
		);
		WP_Mock::userFunction(
			'wp_print_scripts',
			array(
				'args'  => array( 'jquery' ),
				'times' => 1,
			)
		);
		WP_Mock::userFunction(
			'wp_print_scripts',
			array(
				'args'  => array( 'theme' ),
				'times' => 1,
			)
		);
		WP_Mock::userFunction(
			'wp_print_scripts',
			array(
				'args'  => array( 'not-enqueued' ),
				'times' => 0,
			)
		);

		$public = $this->make_public();
		// The non-string entries (int, array, null) are skipped without touching wp_script_is().
		$this->set_property( $public, 'header_scripts', array( 'jquery', 123, 'not-enqueued', array( 'x' ), null, 'theme' ) );

		$public->print_head_scripts();
	}

	// -------------------------------------------------------------------------
	// stf_includes(): singular.
	// -------------------------------------------------------------------------

	/**
	 * Provides which singular conditional tag is true.
	 *
	 * @return array
	 */
	public function provide_singular_tags() {
		return array(
			'is_singular' => array( 'is_singular' ),
			'is_page'     => array( 'is_page' ),
		);
	}

	/**
	 * A supported post type with no exclusion runs the stf_{post_type} filter.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_singular_tags
	 *
	 * @param string $tag Which singular tag is true.
	 * @return void
	 */
	public function test_stf_includes_singular_supported_post_type_passes_through_filter( $tag ) {
		$this->arrange_singular( $tag, 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => '',
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, null, array( 42 ) );
		$this->stub_filter_strict( 'stf_post', true, 'filtered', array( 42 ) );

		// Characterizes current behavior: the stf_{post_type} reply is returned unmodified, not cast.
		$this->assertSame( 'filtered', $this->make_public()->stf_includes() );
	}

	/**
	 * The stf_{post_type} filter can exclude the page by returning false.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_singular_filter_can_exclude() {
		$this->arrange_singular( 'is_singular', 7, 'page' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 7, 'stf_exclude', true ),
				'return' => '',
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, null, array( 7 ) );
		$this->stub_filter_strict( 'stf_page', true, false, array( 7 ) );

		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * A custom post type enabled via the filter uses its own stf_{post_type} filter.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_singular_custom_post_type_enabled_via_filter() {
		$this->arrange_singular( 'is_singular', 9, 'product' );
		$this->stub_post_types( array( 'page', 'post', 'product' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 9, 'stf_exclude', true ),
				'return' => '',
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, null, array( 9 ) );
		$this->stub_filter_strict( 'stf_product', true, true, array( 9 ) );

		$this->assertTrue( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides unsupported-post-type filter replies.
	 *
	 * @return array
	 */
	public function provide_unsupported_post_type_replies() {
		return array(
			'type not listed'      => array( array( 'page', 'post' ), 'product' ),
			'filter returns false' => array( false, 'post' ),
			'filter returns empty' => array( array(), 'post' ),
			'filter returns str'   => array( 'post', 'post' ),
		);
	}

	/**
	 * An unsupported post type is excluded before any meta or per-type filter is read.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_unsupported_post_type_replies
	 *
	 * @param mixed  $supported The supported post types the filter returns.
	 * @param string $post_type The queried post type.
	 * @return void
	 */
	public function test_stf_includes_singular_unsupported_post_type_returns_false( $supported, $post_type ) {
		$this->arrange_singular( 'is_singular', 42, $post_type );
		$this->stub_post_types( $supported );
		WP_Mock::userFunction( 'get_post_meta', array( 'times' => 0 ) );

		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * A queried object id of 0 (or anything absint() makes 0) returns false.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_singular_zero_object_id_returns_false() {
		$this->stub_conditionals( 'is_singular' );
		$this->stub_absint();
		WP_Mock::userFunction( 'get_queried_object_id', array( 'return' => 0 ) );
		WP_Mock::userFunction( 'get_post_type', array( 'times' => 0 ) );

		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * A missing post type (get_post_type() false) returns false.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_singular_missing_post_type_returns_false() {
		$this->arrange_singular( 'is_singular', 42, false );
		WP_Mock::userFunction( 'get_post_meta', array( 'times' => 0 ) );

		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides exclusion meta values that exclude the page.
	 *
	 * @return array
	 */
	public function provide_excluding_meta_values() {
		return array(
			'string one' => array( '1' ),
			'int one'    => array( 1 ),
			'bool true'  => array( true ),
			'legacy on'  => array( 'on' ),
			// Characterizes current behavior: any non-falsy value excludes, even 'off'.
			'string off' => array( 'off' ),
			'string yes' => array( 'yes' ),
		);
	}

	/**
	 * Truthy and legacy 'on' post meta exclude the page before the filters run.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_excluding_meta_values
	 *
	 * @param mixed $meta The stf_exclude post meta.
	 * @return void
	 */
	public function test_stf_includes_singular_exclude_meta_returns_false( $meta ) {
		$this->arrange_singular( 'is_singular', 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => $meta,
			)
		);

		// Strict mode fails if the deprecated or stf_post filters are reached.
		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides falsy exclusion meta values that let processing continue.
	 *
	 * @return array
	 */
	public function provide_non_excluding_meta_values() {
		return array(
			'empty string' => array( '' ),
			'string zero'  => array( '0' ),
			'int zero'     => array( 0 ),
			'bool false'   => array( false ),
		);
	}

	/**
	 * Falsy post meta does not exclude the page.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_non_excluding_meta_values
	 *
	 * @param mixed $meta The stf_exclude post meta.
	 * @return void
	 */
	public function test_stf_includes_singular_falsy_meta_continues( $meta ) {
		$this->arrange_singular( 'is_singular', 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => $meta,
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, null, array( 42 ) );
		$this->stub_filter_strict( 'stf_post', true, true, array( 42 ) );

		$this->assertTrue( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides replies of the deprecated exclude filter and the expected outcome.
	 *
	 * @return array
	 */
	public function provide_deprecated_exclude_replies() {
		return array(
			'true excludes'        => array( true, false ),
			'on excludes'          => array( 'on', false ),
			'null continues'       => array( null, true ),
			'false continues'      => array( false, true ),
			// Characterizes current behavior: only strict true / 'on' exclude; 1 and 'yes' do not.
			'int one continues'    => array( 1, true ),
			'string yes continues' => array( 'yes', true ),
		);
	}

	/**
	 * The deprecated scripts_to_footer_exclude_page filter excludes only on true or 'on'.
	 *
	 * Log_me() writes only when WP_DEBUG and STF_DEBUG are defined, which they are not in
	 * the unit suite, so no log output is produced (the suite fails on unexpected output).
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_deprecated_exclude_replies
	 *
	 * @param mixed $reply    The deprecated filter reply.
	 * @param bool  $included Whether processing continues to stf_{post_type}.
	 * @return void
	 */
	public function test_stf_includes_singular_deprecated_exclude_filter( $reply, $included ) {
		$this->arrange_singular( 'is_singular', 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => '',
			)
		);
		// Receives exactly null and the object id.
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, $reply, array( 42 ) );
		if ( $included ) {
			$this->stub_filter_strict( 'stf_post', true, true, array( 42 ) );
		}

		$this->assertSame( $included, $this->make_public()->stf_includes() );
	}

	/**
	 * The deprecated exclude filter logs its deprecation notice through log_me().
	 *
	 * Separate process because WP_DEBUG and STF_DEBUG are constants; error_log is captured
	 * so the exact logged message can be asserted.
	 *
	 * @covers STF_Public::stf_includes
	 * @covers STF_Functions::log_me
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_stf_includes_deprecated_exclude_filter_logs_deprecation() {
		define( 'WP_DEBUG', true );
		define( 'STF_DEBUG', true );
		$this->capture_error_log();

		$this->arrange_singular( 'is_singular', 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => '',
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, true, array( 42 ) );

		$result = $this->make_public()->stf_includes();

		$this->assertFalse( $result );
		$this->assertSame(
			array( 'Scripts-to-Footer Plugin Error: The \'scripts_to_footer_exclude_page\' is depreciated, please use \'stf_{$post_type}\' returning false to exclude the page.' ),
			$this->logged_errors
		);
	}

	/**
	 * Singular requests win over every other conditional, as the first branch.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_conditional_order_singular_wins_over_everything() {
		$this->arrange_singular( self::$conditional_tags, 42, 'post' );
		$this->stub_post_types( array( 'page', 'post' ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, 'stf_exclude', true ),
				'return' => '',
			)
		);
		$this->stub_filter_strict( 'scripts_to_footer_exclude_page', null, null, array( 42 ) );
		$this->stub_filter_strict( 'stf_post', true, 'singular-wins', array( 42 ) );

		$this->assertSame( 'singular-wins', $this->make_public()->stf_includes() );
	}

	// -------------------------------------------------------------------------
	// stf_includes(): non-singular contexts.
	// -------------------------------------------------------------------------

	/**
	 * Stubs one non-singular request context.
	 *
	 * @param array $context The context definition.
	 * @return void
	 */
	private function arrange_context( array $context ) {
		$this->stub_conditionals( array_merge( array( $context['tag'] ), isset( $context['also'] ) ? $context['also'] : array() ) );

		if ( isset( $context['taxonomies'] ) ) {
			$this->stub_taxonomies( $context['taxonomies'] );
		}

		if ( 'is_tax' === $context['tag'] ) {
			WP_Mock::userFunction(
				'get_query_var',
				array(
					'args'   => array( 'taxonomy' ),
					'return' => $context['query_var'],
				)
			);
			if ( $context['query_var'] ) {
				WP_Mock::userFunction(
					'get_taxonomy',
					array(
						'args'   => array( $context['query_var'] ),
						'return' => $context['taxonomy_object'],
					)
				);
			}
		}

		if ( 'is_post_type_archive' === $context['tag'] ) {
			WP_Mock::userFunction( 'get_post_type', array( 'return' => $context['post_type'] ) );
			$this->stub_post_types( $context['post_types'] );
		}
	}

	/**
	 * Provides every supported non-singular context: [ case, expected type ].
	 *
	 * @return array
	 */
	public function provide_supported_contexts() {
		$taxes = array( 'category', 'post_tag' );

		return array(
			'home'              => array( array( 'tag' => 'is_home' ), 'home' ),
			'search'            => array( array( 'tag' => 'is_search' ), 'search' ),
			'404'               => array( array( 'tag' => 'is_404' ), '404' ),
			'author'            => array( array( 'tag' => 'is_author' ), 'author_archive' ),
			'category'          => array(
				array(
					'tag'        => 'is_category',
					'taxonomies' => $taxes,
				),
				'category_archive',
			),
			'post tag'          => array(
				array(
					'tag'        => 'is_tag',
					'taxonomies' => $taxes,
				),
				'post_tag_archive',
			),
			'category (custom)' => array(
				array(
					'tag'        => 'is_category',
					'taxonomies' => array( 'category' ),
				),
				'category_archive',
			),
			'custom taxonomy'   => array(
				array(
					'tag'             => 'is_tax',
					'taxonomies'      => array( 'category', 'post_tag', 'genre' ),
					'query_var'       => 'genre',
					'taxonomy_object' => (object) array( 'name' => 'genre' ),
				),
				'genre_archive',
			),
			'post type archive' => array(
				array(
					'tag'        => 'is_post_type_archive',
					'post_type'  => 'product',
					'post_types' => array( 'page', 'post', 'product' ),
				),
				'product_archive',
			),
			'generic archive'   => array( array( 'tag' => 'is_archive' ), 'archive' ),
		);
	}

	/**
	 * A set exclude option makes the context excluded; the filter sees exactly false.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_supported_contexts
	 *
	 * @param array  $context The context definition.
	 * @param string $type The expected type slug.
	 * @return void
	 */
	public function test_stf_includes_context_exclude_option_makes_include_false( array $context, $type ) {
		$this->arrange_context( $context );
		$this->stub_options( array( "stf_exclude_{$type}" => 1 ) );
		$this->stub_filter_strict( "stf_{$type}", false, 'reply-from-filter' );

		$this->assertSame( 'reply-from-filter', $this->make_public()->stf_includes() );
	}

	/**
	 * An unset exclude option includes the context; the filter sees exactly true.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_supported_contexts
	 *
	 * @param array  $context The context definition.
	 * @param string $type The expected type slug.
	 * @return void
	 */
	public function test_stf_includes_context_unset_option_makes_include_true( array $context, $type ) {
		$this->arrange_context( $context );
		$this->stub_options( array( 'stf_exclude_something_else' => 1 ) );
		$this->stub_filter_strict( "stf_{$type}", true, 'reply-from-filter' );

		$this->assertSame( 'reply-from-filter', $this->make_public()->stf_includes() );
	}

	/**
	 * A falsy stored exclude value ('0') still includes; a false filter reply excludes.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_supported_contexts
	 *
	 * @param array  $context The context definition.
	 * @param string $type The expected type slug.
	 * @return void
	 */
	public function test_stf_includes_context_falsy_option_includes_and_filter_can_exclude( array $context, $type ) {
		$this->arrange_context( $context );
		$this->stub_options( array( "stf_exclude_{$type}" => '0' ) );
		$this->stub_filter_strict( "stf_{$type}", true, false );

		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * The option is read even when the whole setting is missing (not an array).
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_context_missing_option_includes() {
		$this->arrange_context( array( 'tag' => 'is_home' ) );
		$this->stub_options( false );
		$this->stub_filter_strict( 'stf_home', true, true );

		$this->assertTrue( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides unsupported contexts that return false before any option or filter is read.
	 *
	 * @return array
	 */
	public function provide_unsupported_contexts() {
		return array(
			'category unsupported'          => array(
				array(
					'tag'        => 'is_category',
					'taxonomies' => array( 'post_tag' ),
				),
			),
			'category, filter non-array'    => array(
				array(
					'tag'        => 'is_category',
					'taxonomies' => false,
				),
			),
			'tag unsupported'               => array(
				array(
					'tag'        => 'is_tag',
					'taxonomies' => array( 'category' ),
				),
			),
			'tax: empty query var'          => array(
				array(
					'tag'             => 'is_tax',
					'query_var'       => '',
					'taxonomy_object' => null,
				),
			),
			'tax: get_taxonomy false'       => array(
				array(
					'tag'             => 'is_tax',
					'query_var'       => 'genre',
					'taxonomy_object' => false,
				),
			),
			'tax: object without name'      => array(
				array(
					'tag'             => 'is_tax',
					'query_var'       => 'genre',
					'taxonomy_object' => (object) array( 'label' => 'Genre' ),
				),
			),
			'tax: unsupported'              => array(
				array(
					'tag'             => 'is_tax',
					'taxonomies'      => array( 'category', 'post_tag' ),
					'query_var'       => 'genre',
					'taxonomy_object' => (object) array( 'name' => 'genre' ),
				),
			),
			'post type archive unsupported' => array(
				array(
					'tag'        => 'is_post_type_archive',
					'post_type'  => 'product',
					'post_types' => array( 'page', 'post' ),
				),
			),
			'post type archive: no type'    => array(
				array(
					'tag'        => 'is_post_type_archive',
					'post_type'  => false,
					'post_types' => array( 'page', 'post' ),
				),
			),
			'unmatched context'             => array( array( 'tag' => null ) ),
		);
	}

	/**
	 * Unsupported or unmatched contexts return false without reading options or filters.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_unsupported_contexts
	 *
	 * @param array $context The context definition.
	 * @return void
	 */
	public function test_stf_includes_unsupported_context_returns_false( array $context ) {
		$this->arrange_context( $context );

		// Strict mode fails if get_option() or an stf_{type} filter is reached without a stub.
		$this->assertFalse( $this->make_public()->stf_includes() );
	}

	/**
	 * Earlier conditional tags win: a home request that is also an archive is 'home'.
	 *
	 * @covers STF_Public::stf_includes
	 *
	 * @return void
	 */
	public function test_stf_includes_conditional_order_home_wins_over_archive() {
		WP_Mock::userFunction( 'is_singular', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_page', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_home', array( 'return' => true ) );
		WP_Mock::userFunction( 'is_archive', array( 'times' => 0 ) );
		$this->stub_options( array() );
		$this->stub_filter_strict( 'stf_home', true, true );

		$this->assertTrue( $this->make_public()->stf_includes() );
	}

	/**
	 * Provides every supported context with all LATER conditional tags also true.
	 *
	 * Real WordPress sets is_archive() together with is_author(), is_category(), is_tag(),
	 * is_tax() and is_post_type_archive(), so the earlier branch must win.
	 *
	 * @return array
	 */
	public function provide_contexts_with_later_tags_true() {
		$cases = array();
		foreach ( $this->provide_supported_contexts() as $name => $case ) {
			$index           = array_search( $case[0]['tag'], self::$conditional_tags, true );
			$case[0]['also'] = array_slice( self::$conditional_tags, $index + 1 );
			$cases[ $name ]  = $case;
		}

		return $cases;
	}

	/**
	 * The earliest matching conditional decides the type, even when later ones are true too.
	 *
	 * @covers STF_Public::stf_includes
	 * @dataProvider provide_contexts_with_later_tags_true
	 *
	 * @param array  $context The context definition.
	 * @param string $type    The expected type slug.
	 * @return void
	 */
	public function test_stf_includes_earlier_conditional_wins_over_later_true_ones( array $context, $type ) {
		$this->arrange_context( $context );
		$this->stub_options( array( "stf_exclude_{$type}" => 1 ) );
		$this->stub_filter_strict( "stf_{$type}", false, 'reply-from-filter' );

		$this->assertSame( 'reply-from-filter', $this->make_public()->stf_includes() );
	}
}
