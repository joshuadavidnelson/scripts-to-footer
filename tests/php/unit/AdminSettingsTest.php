<?php
/**
 * Tests for STF_Admin_Settings.
 *
 * @package Scripts_To_Footer
 */

/**
 * Covers STF_Admin_Settings.
 *
 * @covers STF_Admin_Settings
 * @covers STF_Admin_Settings::__construct
 * @covers STF_Admin_Settings::add_plugin_page
 * @covers STF_Admin_Settings::create_admin_page
 * @covers STF_Admin_Settings::page_init
 * @covers STF_Admin_Settings::sanitize
 * @covers STF_Admin_Settings::post_type_options
 * @covers STF_Admin_Settings::taxonomy_options
 * @covers STF_Admin_Settings::print_exclude_options_section_info
 * @covers STF_Admin_Settings::print_header_script_section_info
 * @covers STF_Admin_Settings::stf_home
 * @covers STF_Admin_Settings::stf_search
 * @covers STF_Admin_Settings::stf_404
 * @covers STF_Admin_Settings::stf_post_type_archives
 * @covers STF_Admin_Settings::stf_taxonomy_archives
 * @covers STF_Admin_Settings::stf_author_archive
 * @covers STF_Admin_Settings::stf_archive
 * @covers STF_Admin_Settings::stf_jquery_header
 */
class AdminSettingsTest extends TestCase {

	/**
	 * Recorded arguments of every checked() call.
	 *
	 * @var array
	 */
	private $checked_calls = array();

	/**
	 * Resets recorded calls.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();
		$this->checked_calls = array();
	}

	/**
	 * Stubs the post type and taxonomy support filters.
	 *
	 * @param mixed $post_types Post types reply.
	 * @param mixed $taxes      Taxonomies reply.
	 * @return void
	 */
	private function stub_supported( $post_types, $taxes ) {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), $post_types );
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), $taxes );
	}

	/**
	 * Stubs absint() with real behavior.
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
	 * Stubs checked(), recording its exact arguments and mimicking WordPress.
	 *
	 * @return void
	 */
	private function stub_checked() {
		WP_Mock::userFunction(
			'checked',
			array(
				'return' => function ( ...$args ) {
					$this->checked_calls[] = $args;

					return self::core_checked( $args[0], $args[1], false );
				},
			)
		);
	}

	/**
	 * Stubs esc_attr() so escaped values are visibly marked as ESC(value).
	 *
	 * @return void
	 */
	private function stub_esc_attr() {
		WP_Mock::userFunction(
			'esc_attr',
			array(
				'return' => function ( $value ) {
					return 'ESC(' . $value . ')';
				},
			)
		);
	}

	/**
	 * The constructor sets the settings field and a functions helper.
	 *
	 * @return void
	 */
	public function test_constructor() {
		$settings = new STF_Admin_Settings();

		$this->assertSame( 'scripts-to-footer', $this->get_property( $settings, 'settings_field' ) );
		$this->assertInstanceOf( STF_Functions::class, $this->get_property( $settings, 'functions' ) );
	}

	/**
	 * The options page is registered with exact arguments.
	 *
	 * @return void
	 */
	public function test_add_plugin_page() {
		$settings = new STF_Admin_Settings();

		WP_Mock::userFunction( 'add_options_page' )
			->once()
			->with( 'Scripts to Footer Settings', 'Scripts to Footer', 'manage_options', 'scripts-to-footer', array( $settings, 'create_admin_page' ) );

		$settings->add_plugin_page();
	}

	/**
	 * The admin page loads options and prints the form pieces in order.
	 *
	 * @return void
	 */
	public function test_create_admin_page() {
		$settings = new STF_Admin_Settings();
		$stored   = array( 'stf_exclude_home' => 1 );

		$this->expect_function_strict( 'get_option', array( 'scripts-to-footer', array() ), $stored );
		WP_Mock::userFunction(
			'settings_fields',
			array(
				'times'  => 1,
				'args'   => array( 'scripts-to-footer' ),
				'return' => function () {
					echo '[fields]';
				},
			)
		);
		WP_Mock::userFunction(
			'do_settings_sections',
			array(
				'times'  => 1,
				'args'   => array( 'stf-settings' ),
				'return' => function () {
					echo '[sections]';
				},
			)
		);
		WP_Mock::userFunction(
			'submit_button',
			array(
				'times'  => 1,
				'return' => function () {
					echo '[submit]';
				},
			)
		);

		$this->expectOutputRegex( '#<div class="wrap">\s*<h2>Scripts to Footer Settings</h2>\s*<form method="post" action="options.php">\s*\[fields\]\[sections\]\[submit\]\s*</form>\s*</div>#' );

		$settings->create_admin_page();

		$this->assertSame( $stored, $this->get_property( $settings, 'options' ) );
	}

	/**
	 * Page_init registers the setting, sections and fields with exact arguments.
	 *
	 * @return void
	 */
	public function test_page_init() {
		$settings = new STF_Admin_Settings();

		WP_Mock::userFunction( 'register_setting' )
			->once()
			->with( 'scripts-to-footer', 'scripts-to-footer', array( $settings, 'sanitize' ) );

		WP_Mock::userFunction( 'add_settings_section' )
			->once()
			->with( 'exclude_options', 'Exclude Options', array( $settings, 'print_exclude_options_section_info' ), 'stf-settings' );
		WP_Mock::userFunction( 'add_settings_section' )
			->once()
			->with( 'header_script_options', 'Header Scripts', array( $settings, 'print_header_script_section_info' ), 'stf-settings' );

		$fields = array(
			'stf_home'               => array( 'Blog Page', 'exclude_options' ),
			'stf_search'             => array( 'Search Results', 'exclude_options' ),
			'stf_404'                => array( '404 pages', 'exclude_options' ),
			'stf_post_type_archives' => array( 'Post Type Archives', 'exclude_options' ),
			'stf_taxonomy_archives'  => array( 'Taxonomy Archives', 'exclude_options' ),
			'stf_author_archive'     => array( 'Author Archives', 'exclude_options' ),
			'stf_archive'            => array( 'Other Archive Pages', 'exclude_options' ),
			'stf_jquery_header'      => array( 'Keep jQuery in the Header', 'header_script_options' ),
		);
		foreach ( $fields as $id => $info ) {
			WP_Mock::userFunction( 'add_settings_field' )
				->once()
				->with( $id, $info[0], array( $settings, $id ), 'stf-settings', $info[1] );
		}

		$settings->page_init();
	}

	/**
	 * Sanitize casts every known key to an absolute integer and drops unknown keys.
	 *
	 * @return void
	 */
	public function test_sanitize_known_keys_and_drops_unknown() {
		$this->stub_absint();
		$this->stub_supported( array( 'page', 'book' ), array( 'category', 'genre' ) );
		$settings = new STF_Admin_Settings();

		$input = array(
			'stf_exclude_home'             => '1',
			'stf_exclude_search'           => '-5',
			'stf_exclude_404'              => 'abc',
			'stf_exclude_archive'          => 1,
			'stf_exclude_author_archive'   => '7',
			'stf_exclude_page_archive'     => '1',
			'stf_exclude_book_archive'     => '3',
			'stf_exclude_category_archive' => '0',
			'stf_exclude_genre_archive'    => '1',
			'stf_jquery_header'            => '1',
			'stf_exclude_post_archive'     => '1',
			'stf_exclude_post_tag_archive' => '1',
			'unknown'                      => '1',
		);

		$this->assertSame(
			array(
				'stf_exclude_home'             => 1,
				'stf_exclude_search'           => 5,
				'stf_exclude_404'              => 0,
				'stf_exclude_archive'          => 1,
				'stf_exclude_author_archive'   => 7,
				'stf_exclude_page_archive'     => 1,
				'stf_exclude_book_archive'     => 3,
				'stf_exclude_category_archive' => 0,
				'stf_exclude_genre_archive'    => 1,
				'stf_jquery_header'            => 1,
			),
			$settings->sanitize( $input )
		);
	}

	/**
	 * Missing keys stay absent and an empty input returns an empty array.
	 *
	 * @return void
	 */
	public function test_sanitize_missing_keys_absent() {
		$this->stub_supported( array( 'page', 'post' ), array( 'category', 'post_tag' ) );
		$settings = new STF_Admin_Settings();

		$this->assertSame( array(), $settings->sanitize( array() ) );
	}

	/**
	 * Null values are treated as unset.
	 *
	 * @return void
	 */
	public function test_sanitize_null_values_are_dropped() {
		$this->stub_supported( array( 'page', 'post' ), array( 'category', 'post_tag' ) );
		$settings = new STF_Admin_Settings();

		$this->assertSame( array(), $settings->sanitize( array( 'stf_exclude_home' => null ) ) );
	}

	/**
	 * Non-array filter replies skip the post type and taxonomy loops.
	 *
	 * @return void
	 */
	public function test_sanitize_skips_loops_for_non_array_filters() {
		$this->stub_absint();
		$this->stub_supported( 'nope', false );
		$settings = new STF_Admin_Settings();

		$this->assertSame(
			array( 'stf_exclude_home' => 1 ),
			$settings->sanitize(
				array(
					'stf_exclude_home'             => '1',
					'stf_exclude_page_archive'     => '1',
					'stf_exclude_category_archive' => '1',
				)
			)
		);
	}

	/**
	 * Post_type_options() builds field names from the supported post types.
	 *
	 * @return void
	 */
	public function test_post_type_options() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), array( 'page', 'book' ) );

		$this->assertSame(
			array( 'stf_exclude_page_archive', 'stf_exclude_book_archive' ),
			( new STF_Admin_Settings() )->post_type_options()
		);
	}

	/**
	 * Post_type_options() is empty when the filter returns a non-array.
	 *
	 * @return void
	 */
	public function test_post_type_options_non_array() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), 'x' );

		$this->assertSame( array(), ( new STF_Admin_Settings() )->post_type_options() );
	}

	/**
	 * Taxonomy_options() builds field names from the supported taxonomies.
	 *
	 * @return void
	 */
	public function test_taxonomy_options() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), array( 'category', 'genre' ) );

		$this->assertSame(
			array( 'stf_exclude_category_archive', 'stf_exclude_genre_archive' ),
			( new STF_Admin_Settings() )->taxonomy_options()
		);
	}

	/**
	 * Taxonomy_options() is empty when the filter returns a non-array.
	 *
	 * @return void
	 */
	public function test_taxonomy_options_non_array() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), null );

		$this->assertSame( array(), ( new STF_Admin_Settings() )->taxonomy_options() );
	}

	/**
	 * The exclude section intro is passed through wp_kses_post().
	 *
	 * @return void
	 */
	public function test_print_exclude_options_section_info() {
		WP_Mock::userFunction( 'wp_kses_post' )
			->once()
			->with( 'Select which templates should <em><strong>not</strong></em> have scripts moved to the footer:' )
			->andReturn( 'KSES' );

		$this->expectOutputString( 'KSES' );

		( new STF_Admin_Settings() )->print_exclude_options_section_info();
	}

	/**
	 * The header scripts intro links to the documentation.
	 *
	 * @return void
	 */
	public function test_print_header_script_section_info() {
		$this->expectOutputString( 'Options for keeping specific scripts in the header, if they occur. Want to exclude a different script? Check out the <a href="https://github.com/joshuadavidnelson/scripts-to-footer/wiki" title="On Github">documentation</a> for more information.' );

		( new STF_Admin_Settings() )->print_header_script_section_info();
	}

	/**
	 * Data provider for the simple checkbox fields.
	 *
	 * @return array
	 */
	public function provide_checkbox_fields() {
		return array(
			'home'           => array( 'stf_home', 'stf_exclude_home', true ),
			'search'         => array( 'stf_search', 'stf_exclude_search', true ),
			'404'            => array( 'stf_404', 'stf_exclude_404', true ),
			'author archive' => array( 'stf_author_archive', 'stf_exclude_author_archive', false ),
			'archive'        => array( 'stf_archive', 'stf_exclude_archive', false ),
			'jquery header'  => array( 'stf_jquery_header', 'stf_jquery_header', false ),
		);
	}

	/**
	 * A checkbox field with nothing stored prints unchecked and checks against 0.
	 *
	 * @dataProvider provide_checkbox_fields
	 *
	 * @param string $method  The field method.
	 * @param string $key     The option key.
	 * @param bool   $escaped Whether the field name goes through esc_attr().
	 * @return void
	 */
	public function test_checkbox_field_unset( $method, $key, $escaped ) {
		if ( $escaped ) {
			$this->stub_esc_attr();
		}
		$this->stub_checked();
		$settings = new STF_Admin_Settings();

		$name = $escaped ? 'ESC(scripts-to-footer)' : 'scripts-to-footer';
		$this->expectOutputString( '<input type="checkbox" name="' . $name . '[' . $key . ']"  value="1">' );

		$settings->$method();

		$this->assertSame( array( array( 0, 1, false ) ), $this->checked_calls );
		$this->assertSame( 0, $this->get_property( $settings, 'options' )[ $key ] );
	}

	/**
	 * A checkbox field with 1 stored prints checked and checks against 1.
	 *
	 * @dataProvider provide_checkbox_fields
	 *
	 * @param string $method  The field method.
	 * @param string $key     The option key.
	 * @param bool   $escaped Whether the field name goes through esc_attr().
	 * @return void
	 */
	public function test_checkbox_field_set( $method, $key, $escaped ) {
		if ( $escaped ) {
			$this->stub_esc_attr();
		}
		$this->stub_checked();
		$settings = new STF_Admin_Settings();
		$this->set_property( $settings, 'options', array( $key => 1 ) );

		$name = $escaped ? 'ESC(scripts-to-footer)' : 'scripts-to-footer';
		$this->expectOutputString( '<input type="checkbox" name="' . $name . '[' . $key . ']"  checked=\'checked\' value="1">' );

		$settings->$method();

		$this->assertSame( array( array( 1, 1, false ) ), $this->checked_calls );
	}

	/**
	 * Escaped fields run the settings field name through esc_attr().
	 *
	 * @return void
	 */
	public function test_home_field_escapes_name() {
		WP_Mock::userFunction( 'esc_attr' )->once()->with( 'scripts-to-footer' )->andReturn( 'ESC' );
		$this->stub_checked();

		$this->expectOutputString( '<input type="checkbox" name="ESC[stf_exclude_home]"  value="1">' );

		( new STF_Admin_Settings() )->stf_home();
	}

	/**
	 * The post type archives field prints one list item per supported post type.
	 *
	 * @return void
	 */
	public function test_post_type_archives_field() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), array( 'page', 'book' ) );
		$this->stub_checked();
		WP_Mock::userFunction( 'get_post_type_object' )
			->once()
			->with( 'page' )
			->andReturn( (object) array( 'labels' => (object) array( 'singular_name' => 'Page' ) ) );
		WP_Mock::userFunction( 'get_post_type_object' )
			->once()
			->with( 'book' )
			->andReturn( (object) array( 'labels' => (object) array( 'singular_name' => 'Book' ) ) );

		$settings = new STF_Admin_Settings();
		$this->set_property( $settings, 'options', array( 'stf_exclude_book_archive' => 1 ) );

		$this->expectOutputString(
			'<ul>'
			. '<li><input type="checkbox" id="stf_exclude_page_archive" name="scripts-to-footer[stf_exclude_page_archive]"  value="1"><label for="stf_exclude_page_archive">Page </label></li>'
			. '<li><input type="checkbox" id="stf_exclude_book_archive" name="scripts-to-footer[stf_exclude_book_archive]"  checked=\'checked\' value="1"><label for="stf_exclude_book_archive">Book </label></li>'
			. '</ul>'
		);

		$settings->stf_post_type_archives();

		$this->assertSame( array( array( 0, 1, false ), array( 1, 1, false ) ), $this->checked_calls );
	}

	/**
	 * The post type archives field prints nothing for a non-array filter reply.
	 *
	 * @return void
	 */
	public function test_post_type_archives_field_non_array() {
		$this->stub_filter_strict( 'scripts_to_footer_post_types', array( 'page', 'post' ), false );

		$this->expectOutputString( '' );

		( new STF_Admin_Settings() )->stf_post_type_archives();
	}

	/**
	 * The taxonomy archives field prints one list item per supported taxonomy.
	 *
	 * @return void
	 */
	public function test_taxonomy_archives_field() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), array( 'category', 'genre' ) );
		$this->stub_checked();
		WP_Mock::userFunction( 'get_taxonomy' )
			->once()
			->with( 'category' )
			->andReturn( (object) array( 'labels' => (object) array( 'singular_name' => 'Category' ) ) );
		WP_Mock::userFunction( 'get_taxonomy' )
			->once()
			->with( 'genre' )
			->andReturn( (object) array( 'labels' => (object) array( 'singular_name' => 'Genre' ) ) );

		$settings = new STF_Admin_Settings();
		$this->set_property( $settings, 'options', array( 'stf_exclude_category_archive' => 1 ) );

		$this->expectOutputString(
			'<ul>'
			. '<li><input type="checkbox" id="stf_exclude_category_archive" name="scripts-to-footer[stf_exclude_category_archive]"  checked=\'checked\' value="1"><label for="stf_exclude_category_archive">Category </label></li>'
			. '<li><input type="checkbox" id="stf_exclude_genre_archive" name="scripts-to-footer[stf_exclude_genre_archive]"  value="1"><label for="stf_exclude_genre_archive">Genre </label></li>'
			. '</ul>'
		);

		$settings->stf_taxonomy_archives();

		$this->assertSame( array( array( 1, 1, false ), array( 0, 1, false ) ), $this->checked_calls );
	}

	/**
	 * The taxonomy archives field prints nothing for a non-array filter reply.
	 *
	 * @return void
	 */
	public function test_taxonomy_archives_field_non_array() {
		$this->stub_filter_strict( 'scripts_to_footer_taxonomies', array( 'category', 'post_tag' ), 'x' );

		$this->expectOutputString( '' );

		( new STF_Admin_Settings() )->stf_taxonomy_archives();
	}

	/**
	 * An injected STF_Functions decides which option fields exist.
	 *
	 * @return void
	 */
	public function test_injected_functions_instance_is_used() {
		$functions = Mockery::mock( STF_Functions::class );
		$functions->shouldReceive( 'post_types_supported' )->once()->andReturn( array( 'secret' ) );
		$functions->shouldReceive( 'taxonomies_supported' )->once()->andReturn( array( 'topic' ) );

		$settings = new STF_Admin_Settings( $functions );

		$this->assertSame( $functions, $this->get_property( $settings, 'functions' ) );
		$this->assertSame( array( 'stf_exclude_secret_archive' ), $settings->post_type_options() );
		$this->assertSame( array( 'stf_exclude_topic_archive' ), $settings->taxonomy_options() );
	}
}
