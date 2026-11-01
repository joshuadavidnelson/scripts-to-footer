<?php
/**
 * Integration tests for moving head scripts to the footer.
 *
 * Runs the plugin against real WordPress with the real global WP_Scripts
 * instance and captures what wp_head and wp_footer print.
 *
 * @package Scripts_To_Footer
 */

/**
 * Scripts to Footer front-end integration tests.
 *
 * @covers STF_Public
 * @covers STF_Functions
 * @covers Scripts_To_Footer
 */
class ScriptsToFooterIntegrationTest extends WP_UnitTestCase {

	/**
	 * Source of the primary test script.
	 *
	 * @var string
	 */
	const SCRIPT_SRC = 'https://example.org/a.js';

	/**
	 * Source of the dependency script.
	 *
	 * @var string
	 */
	const DEPENDENCY_SRC = 'https://example.org/dep.js';

	/**
	 * Set up the fixtures and a clean script queue.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->reset_scripts();

		register_post_type(
			'stf_book',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);
		register_post_type( 'stf_note', array( 'public' => true ) );
		register_taxonomy( 'stf_genre', 'post', array( 'public' => true ) );

		add_filter( 'scripts_to_footer_post_types', array( $this, 'support_book_post_type' ) );
		add_filter( 'scripts_to_footer_taxonomies', array( $this, 'support_genre_taxonomy' ) );
	}

	/**
	 * Remove fixtures and restore the global script and hook state.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'scripts_to_footer_post_types', array( $this, 'support_book_post_type' ) );
		remove_filter( 'scripts_to_footer_taxonomies', array( $this, 'support_genre_taxonomy' ) );
		remove_all_filters( 'stf_exclude_scripts' );

		unregister_post_type( 'stf_book' );
		unregister_post_type( 'stf_note' );
		unregister_taxonomy( 'stf_genre' );

		$this->reset_scripts();

		// The core wp_head callbacks are removed by the plugin, so put them back for later tests.
		foreach ( array(
			array( 'wp_head', 'wp_enqueue_scripts', 1 ),
			array( 'wp_head', 'wp_print_head_scripts', 9 ),
		) as $hook ) {
			if ( false === has_action( $hook[0], $hook[1] ) ) {
				add_action( $hook[0], $hook[1], $hook[2] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Add the book post type to the supported post types.
	 *
	 * @param string[] $post_types The supported post types.
	 * @return string[]
	 */
	public function support_book_post_type( $post_types ) {
		$post_types[] = 'stf_book';
		return $post_types;
	}

	/**
	 * Add the genre taxonomy to the supported taxonomies.
	 *
	 * @param string[] $taxonomies The supported taxonomies.
	 * @return string[]
	 */
	public function support_genre_taxonomy( $taxonomies ) {
		$taxonomies[] = 'stf_genre';
		return $taxonomies;
	}

	/**
	 * Replace the global script queue with a fresh instance.
	 *
	 * @return void
	 */
	private function reset_scripts() {
		$GLOBALS['wp_scripts'] = null;
		wp_scripts();
	}

	/**
	 * Enqueue the primary test script in the head.
	 *
	 * @return void
	 */
	private function enqueue_test_script() {
		wp_register_script( 'stf-test', self::SCRIPT_SRC, array(), '1.0', false );
		wp_enqueue_script( 'stf-test' );
	}

	/**
	 * Run the enqueue, head and footer actions and return what they print.
	 *
	 * @return array{head: string, footer: string}
	 */
	private function render_page() {
		do_action( 'wp_enqueue_scripts' );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		ob_start();
		do_action( 'wp_footer' );
		$footer = ob_get_clean();

		return array(
			'head'   => $head,
			'footer' => $footer,
		);
	}

	/**
	 * Assert that a string is printed only in the footer, exactly once.
	 *
	 * @param string $needle The string to look for.
	 * @param array  $page   The rendered page from render_page().
	 * @return void
	 */
	private function assertMovedToFooter( $needle, $page ) {
		$this->assertStringNotContainsString( $needle, $page['head'], 'Script should not print in the head.' );
		$this->assertSame( 1, substr_count( $page['footer'], $needle ), 'Script should print once in the footer.' );
	}

	/**
	 * Assert that a string is printed only in the head, exactly once.
	 *
	 * @param string $needle The string to look for.
	 * @param array  $page   The rendered page from render_page().
	 * @return void
	 */
	private function assertKeptInHead( $needle, $page ) {
		$this->assertSame( 1, substr_count( $page['head'], $needle ), 'Script should print once in the head.' );
		$this->assertStringNotContainsString( $needle, $page['footer'], 'Script should not print in the footer.' );
	}

	/**
	 * Navigate to a page for the given scenario.
	 *
	 * @param string $scenario The scenario, which doubles as the settings key suffix.
	 * @return void
	 */
	private function go_to_scenario( $scenario ) {
		$author_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_date'   => '2020-06-15 12:00:00',
			)
		);

		switch ( $scenario ) {
			case 'home':
				$url = home_url( '/' );
				break;
			case 'search':
				$url = home_url( '/?s=stf' );
				break;
			case '404':
				$url = home_url( '/?p=99999999' );
				break;
			case 'author_archive':
				$url = get_author_posts_url( $author_id );
				break;
			case 'category_archive':
				$term_id = self::factory()->category->create();
				wp_set_post_categories( $post_id, array( $term_id ) );
				$url = get_category_link( $term_id );
				break;
			case 'post_tag_archive':
				$term = self::factory()->tag->create_and_get();
				wp_set_post_tags( $post_id, array( $term->slug ) );
				$url = get_tag_link( $term->term_id );
				break;
			case 'archive':
				$url = get_year_link( 2020 );
				break;
			case 'stf_book_archive':
				self::factory()->post->create( array( 'post_type' => 'stf_book' ) );
				$url = get_post_type_archive_link( 'stf_book' );
				break;
			case 'stf_genre_archive':
				$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'stf_genre' ) );
				wp_set_object_terms( $post_id, array( $term->term_id ), 'stf_genre' );
				$url = get_term_link( $term );
				break;
			default:
				$this->fail( 'Unknown scenario: ' . $scenario );
		}

		$this->go_to( $url );
	}

	/**
	 * Settings-driven page types, as scenario names.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function page_type_provider() {
		return array(
			'home'              => array( 'home' ),
			'search'            => array( 'search' ),
			'404'               => array( '404' ),
			'author archive'    => array( 'author_archive' ),
			'category archive'  => array( 'category_archive' ),
			'tag archive'       => array( 'post_tag_archive' ),
			'date archive'      => array( 'archive' ),
			'post type archive' => array( 'stf_book_archive' ),
			'taxonomy archive'  => array( 'stf_genre_archive' ),
		);
	}

	/**
	 * The plugin registers its specific callbacks at the expected priorities.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_are_registered() {
		$this->assertContains( 'clean_head', $this->get_public_methods( 'wp_enqueue_scripts', 10 ) );
		$this->assertContains( 'stf_includes', $this->get_public_methods( 'stf_include', 10 ) );
		$this->assertContains( 'set_header_scripts', $this->get_public_methods( 'wp_head', 1 ) );
		$this->assertContains( 'print_head_scripts', $this->get_public_methods( 'wp_head', 10 ) );
		$this->assertNotFalse( has_action( 'wp_head', array( $this->get_public_object( 'wp_head', 1 ), 'set_header_scripts' ) ) );
	}

	/**
	 * Get the STF_Public method names attached to a hook at a priority.
	 *
	 * @param string $hook     The hook name.
	 * @param int    $priority The callback priority.
	 * @return string[]
	 */
	private function get_public_methods( $hook, $priority ) {
		global $wp_filter;

		$methods = array();
		if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
			return $methods;
		}

		foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof STF_Public ) {
				$methods[] = $callback['function'][1];
			}
		}

		return $methods;
	}

	/**
	 * Find the STF_Public instance attached to a hook.
	 *
	 * @param string $hook     The hook name.
	 * @param int    $priority The callback priority.
	 * @return STF_Public
	 */
	private function get_public_object( $hook, $priority ) {
		global $wp_filter;

		foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof STF_Public ) {
				return $callback['function'][0];
			}
		}

		$this->fail( 'No STF_Public callback found on ' . $hook );
	}

	/**
	 * A head script on a singular post prints only in the footer.
	 *
	 * @return void
	 */
	public function test_singular_post_moves_head_script_to_footer() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * A singular page also moves its head script to the footer.
	 *
	 * @return void
	 */
	public function test_page_moves_head_script_to_footer() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * The per-post exclude meta keeps the script in the head.
	 *
	 * @dataProvider exclude_meta_provider
	 *
	 * @param mixed $meta_value The stored meta value.
	 * @return void
	 */
	public function test_post_meta_exclude_keeps_script_in_head( $meta_value ) {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'stf_exclude', $meta_value );
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Stored values that exclude a post.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function exclude_meta_provider() {
		return array(
			'boolean style 1' => array( '1' ),
			'legacy on'       => array( 'on' ),
		);
	}

	/**
	 * An empty exclude meta value does not exclude the post.
	 *
	 * @return void
	 */
	public function test_post_meta_empty_does_not_exclude() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'stf_exclude', '' );
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * A singular post of an unsupported post type is left untouched.
	 *
	 * @return void
	 */
	public function test_unsupported_post_type_is_left_in_head() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'stf_note' ) );
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( is_singular( 'stf_note' ) );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * A singular post of a supported custom post type moves its script.
	 *
	 * @return void
	 */
	public function test_supported_custom_post_type_moves_script_to_footer() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'stf_book' ) );
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Each page type moves the script to the footer by default.
	 *
	 * @dataProvider page_type_provider
	 *
	 * @param string $scenario The page type scenario.
	 * @return void
	 */
	public function test_page_type_moves_script_to_footer_by_default( $scenario ) {
		$this->go_to_scenario( $scenario );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Each page type keeps the script in the head when its setting is checked.
	 *
	 * @dataProvider page_type_provider
	 *
	 * @param string $scenario The page type scenario.
	 * @return void
	 */
	public function test_page_type_setting_keeps_script_in_head( $scenario ) {
		update_option( 'scripts-to-footer', array( "stf_exclude_{$scenario}" => '1' ) );
		$this->go_to_scenario( $scenario );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * A setting for a different page type does not affect the current page.
	 *
	 * @return void
	 */
	public function test_unrelated_page_type_setting_is_ignored() {
		update_option( 'scripts-to-footer', array( 'stf_exclude_search' => '1' ) );
		$this->go_to_scenario( 'home' );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * The category archive is skipped entirely when the taxonomy is unsupported.
	 *
	 * @return void
	 */
	public function test_unsupported_taxonomy_archive_is_left_in_head() {
		remove_filter( 'scripts_to_footer_taxonomies', array( $this, 'support_genre_taxonomy' ) );
		$this->go_to_scenario( 'stf_genre_archive' );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Scripts listed in stf_exclude_scripts print in the head with their dependencies.
	 *
	 * @return void
	 */
	public function test_exclude_scripts_filter_prints_script_and_dependencies_in_head() {
		add_filter(
			'stf_exclude_scripts',
			static function () {
				return array( 'stf-test' );
			}
		);

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_register_script( 'stf-dep', self::DEPENDENCY_SRC, array(), '1.0', false );
		wp_register_script( 'stf-test', self::SCRIPT_SRC, array( 'stf-dep' ), '1.0', false );
		wp_enqueue_script( 'stf-test' );

		$page = $this->render_page();

		$this->assertKeptInHead( self::SCRIPT_SRC, $page );
		$this->assertKeptInHead( self::DEPENDENCY_SRC, $page );
		$this->assertLessThan(
			strpos( $page['head'], self::SCRIPT_SRC ),
			strpos( $page['head'], self::DEPENDENCY_SRC ),
			'The dependency should print before the script.'
		);
		$this->assertSame( 1, substr_count( $page['head'] . $page['footer'], self::SCRIPT_SRC ) );
		$this->assertSame( 1, substr_count( $page['head'] . $page['footer'], self::DEPENDENCY_SRC ) );
	}

	/**
	 * Only the listed scripts stay in the head, others still move.
	 *
	 * @return void
	 */
	public function test_exclude_scripts_filter_only_affects_listed_scripts() {
		add_filter(
			'stf_exclude_scripts',
			static function () {
				return array( 'stf-test' );
			}
		);

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();
		wp_enqueue_script( 'stf-other', self::DEPENDENCY_SRC, array(), '1.0', false );

		$page = $this->render_page();

		$this->assertKeptInHead( self::SCRIPT_SRC, $page );
		$this->assertMovedToFooter( self::DEPENDENCY_SRC, $page );
	}

	/**
	 * Handles that are not enqueued are not printed by the exclude filter.
	 *
	 * @return void
	 */
	public function test_exclude_scripts_filter_ignores_unenqueued_handles() {
		add_filter(
			'stf_exclude_scripts',
			static function () {
				return array( 'stf-test', 'stf-missing', 42 );
			}
		);

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_register_script( 'stf-test', self::SCRIPT_SRC, array(), '1.0', false );

		$page = $this->render_page();

		$this->assertStringNotContainsString( self::SCRIPT_SRC, $page['head'] . $page['footer'] );
	}

	/**
	 * The jQuery option keeps jQuery and its dependencies in the head.
	 *
	 * @return void
	 */
	public function test_jquery_header_option_keeps_jquery_in_head() {
		update_option( 'scripts-to-footer', array( 'stf_jquery_header' => '1' ) );
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_enqueue_script( 'jquery' );
		$this->enqueue_test_script();

		$page = $this->render_page();

		foreach ( array( 'jquery-core', 'jquery-migrate' ) as $handle ) {
			$pattern = '/id=["\']' . $handle . '-js["\']/';
			$this->assertSame( 1, preg_match_all( $pattern, $page['head'] ), $handle . ' should print once in the head.' );
			$this->assertSame( 0, preg_match( $pattern, $page['footer'] ), $handle . ' should not print in the footer.' );
		}

		$this->assertMovedToFooter( self::SCRIPT_SRC, $page );
	}

	/**
	 * Without the jQuery option, jQuery moves to the footer with the other scripts.
	 *
	 * @return void
	 */
	public function test_jquery_moves_to_footer_without_option() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_enqueue_script( 'jquery' );

		$page = $this->render_page();

		foreach ( array( 'jquery-core', 'jquery-migrate' ) as $handle ) {
			$pattern = '/id=["\']' . $handle . '-js["\']/';
			$this->assertSame( 0, preg_match( $pattern, $page['head'] ), $handle . ' should not print in the head.' );
			$this->assertSame( 1, preg_match_all( $pattern, $page['footer'] ), $handle . ' should print once in the footer.' );
		}
	}

	/**
	 * The stf_include filter can switch the plugin off for a page.
	 *
	 * @return void
	 */
	public function test_stf_include_filter_false_leaves_script_in_head() {
		add_filter( 'stf_home', '__return_false' );
		$this->go_to_scenario( 'home' );
		$this->enqueue_test_script();

		$page = $this->render_page();
		remove_filter( 'stf_home', '__return_false' );

		$this->assertKeptInHead( self::SCRIPT_SRC, $page );
	}

	/**
	 * The stf_{post_type} filter returning false keeps the script in the head.
	 *
	 * @return void
	 */
	public function test_stf_post_filter_false_keeps_script_in_head_and_receives_post_id() {
		$post_id = self::factory()->post->create();
		$args    = array();
		add_filter(
			'stf_post',
			static function ( $included, $id = null ) use ( &$args ) {
				$args[] = array( $included, $id );
				return false;
			},
			10,
			2
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$page = $this->render_page();

		$this->assertKeptInHead( self::SCRIPT_SRC, $page );
		$this->assertNotEmpty( $args );
		$this->assertSame( array( true, $post_id ), $args[0] );
	}

	/**
	 * The deprecated scripts_to_footer_exclude_page filter keeps the script in the head.
	 *
	 * @dataProvider deprecated_exclude_provider
	 *
	 * @param mixed $return_value The filter return value.
	 * @return void
	 */
	public function test_deprecated_exclude_page_filter_keeps_script_in_head( $return_value ) {
		$post_id = self::factory()->post->create();
		$args    = array();
		add_filter(
			'scripts_to_footer_exclude_page',
			static function ( $value, $id = null ) use ( $return_value, &$args ) {
				$args[] = array( $value, $id );
				return $return_value;
			},
			10,
			2
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
		$this->assertSame( array( null, $post_id ), $args[0] );
	}

	/**
	 * Return values of the deprecated filter that exclude a page.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function deprecated_exclude_provider() {
		return array(
			'true' => array( true ),
			'on'   => array( 'on' ),
		);
	}

	/**
	 * Other return values of the deprecated filter do not exclude a page.
	 *
	 * @return void
	 */
	public function test_deprecated_exclude_page_filter_other_values_do_not_exclude() {
		add_filter( 'scripts_to_footer_exclude_page', '__return_false' );
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->enqueue_test_script();

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Category and tag archives are left alone when their taxonomy is unsupported.
	 *
	 * @dataProvider core_taxonomy_provider
	 *
	 * @param string $scenario The page type scenario.
	 * @param string $taxonomy The taxonomy to remove from the supported list.
	 * @return void
	 */
	public function test_unsupported_core_taxonomy_archive_is_left_in_head( $scenario, $taxonomy ) {
		add_filter(
			'scripts_to_footer_taxonomies',
			static function ( $taxonomies ) use ( $taxonomy ) {
				return array_values( array_diff( $taxonomies, array( $taxonomy ) ) );
			},
			20
		);
		$this->go_to_scenario( $scenario );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Core taxonomy archive scenarios.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function core_taxonomy_provider() {
		return array(
			'category' => array( 'category_archive', 'category' ),
			'post_tag' => array( 'post_tag_archive', 'post_tag' ),
		);
	}

	/**
	 * A post type archive for an unsupported post type is left alone.
	 *
	 * @return void
	 */
	public function test_unsupported_post_type_archive_is_left_in_head() {
		remove_filter( 'scripts_to_footer_post_types', array( $this, 'support_book_post_type' ) );
		$this->go_to_scenario( 'stf_book_archive' );
		$this->assertTrue( is_post_type_archive( 'stf_book' ) );
		$this->enqueue_test_script();

		$this->assertKeptInHead( self::SCRIPT_SRC, $this->render_page() );
	}

	/**
	 * Footer scripts stay in the footer.
	 *
	 * @return void
	 */
	public function test_footer_script_stays_in_footer() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_enqueue_script( 'stf-footer', self::SCRIPT_SRC, array(), '1.0', true );

		$this->assertMovedToFooter( self::SCRIPT_SRC, $this->render_page() );
	}
}
