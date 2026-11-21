<?php
/**
 * Shared base test case wiring WP_Mock into PHPUnit's lifecycle.
 *
 * Policy: never stub a plugin-owned function (stf_*). Only WordPress core functions,
 * filters and actions are doubled, so every test exercises the plugin's real code.
 *
 * @package Scripts_To_Footer
 */

/**
 * Base test case for the WP_Mock-based unit suite.
 */
class TestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase {

	/**
	 * Translation functions a test class stubs itself, so the passthrough is not registered.
	 *
	 * A passthrough esc_html__() cannot tell esc_html__() from __(). A class that must prove
	 * the escaping variant is used lists it here and registers a marking stub.
	 *
	 * @var string[]
	 */
	protected $own_i18n_stubs = array();

	/**
	 * The messages passed to error_log() since capture_error_log() was called.
	 *
	 * @var string[]
	 */
	protected $logged_errors = array();

	/**
	 * Patchwork handle of the error_log() redefinition, while one is active.
	 *
	 * @var string|null
	 */
	private $error_log_handle = null;

	/**
	 * Boots WP_Mock and registers the translation passthroughs before each test.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		$this->logged_errors = array();

		WP_Mock::setUp();
		self::reset_expected_hooks();

		foreach ( array_diff( array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' ), $this->own_i18n_stubs ) as $i18n ) {
			WP_Mock::userFunction( $i18n, array( 'return_arg' => 0 ) );
		}
	}

	/**
	 * Verifies WP_Mock's hook expectations, then tears WP_Mock down after each test.
	 *
	 * WP_Mock only verifies expectHookAdded()/expectAction()/expectFilter() when asked,
	 * so this runs on every test: a forgotten assertion can't let an expectation pass
	 * silently. Mockery's function expectations are counted as assertions so a test
	 * whose only checks are ->times()/->with() expectations isn't flagged as risky.
	 *
	 * The cleanup is nested in finally blocks so a failed verification, or Mockery::close()
	 * throwing inside WP_Mock::tearDown() (which then skips its own handler cleanup), can't
	 * leave mock state behind for the next test.
	 *
	 * @return void
	 */
	protected function tear_down() {
		try {
			$this->assertConditionsMet();
			$this->addToAssertionCount( Mockery::getContainer()->mockery_getExpectationCount() );
		} finally {
			try {
				WP_Mock::tearDown();
			} finally {
				WP_Mock\Functions\Handler::cleanup();
				$this->restore_error_log();
				parent::tear_down();
			}
		}
	}

	/**
	 * Clears WP_Mock's expectHookAdded() bookkeeping.
	 *
	 * WP_Mock\EventManager::flush() resets expected actions and filters between tests but
	 * not the `callbacks` map that expectActionAdded()/expectFilterAdded() write to, which
	 * also starts out null (so assertHooksAdded() fatals before any expectation exists).
	 * Resetting it here keeps every test's hook expectations its own, whatever the order.
	 *
	 * @return void
	 */
	private static function reset_expected_hooks() {
		$manager = new ReflectionProperty( WP_Mock::class, 'event_manager' );
		if ( PHP_VERSION_ID < 80100 ) {
			$manager->setAccessible( true ); // No-op since PHP 8.1, deprecated in 8.5.
		}

		$callbacks = new ReflectionProperty( WP_Mock\EventManager::class, 'callbacks' );
		if ( PHP_VERSION_ID < 80100 ) {
			$callbacks->setAccessible( true ); // No-op since PHP 8.1, deprecated in 8.5.
		}
		$callbacks->setValue( $manager->getValue(), array() );
	}

	/**
	 * Asserts every expected hook was added and every expected action/filter fired.
	 *
	 * @return void
	 */
	protected function assertConditionsMet() {
		WP_Mock::assertHooksAdded();
		WP_Mock::assertActionsCalled();
		WP_Mock::assertFiltersCalled();
	}

	/**
	 * Registers $filter so it asserts the exact value it receives is $expected before
	 * replying with $reply.
	 *
	 * WP_Mock::onFilter()->with() keys its matches on WP_Mock\Hook::safe_offset(), which
	 * casts scalars via (string): safe_offset( true ) === safe_offset( 1 ) === '1' and
	 * safe_offset( false ) === safe_offset( '' ) === ''. A plain ->with( true ) therefore
	 * still matches when the source passes a loosely-equal value instead of the literal
	 * the filter documents. WP_Mock\InvokedFilterValue's callback receives the real
	 * invoked arguments, so the value can be asserted strictly.
	 *
	 * @param string $filter   The filter hook name.
	 * @param mixed  $expected The exact first value the filter must receive.
	 * @param mixed  $reply    The value the filter should return.
	 * @param array  $extra    Exact additional arguments the filter must receive.
	 * @return void
	 */
	protected function stub_filter_strict( $filter, $expected, $reply, array $extra = array() ) {
		WP_Mock::onFilter( $filter )->with( $expected, ...$extra )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( ...$args ) use ( $filter, $expected, $reply, $extra ) {
					$this->assertSame( array_merge( array( $expected ), $extra ), $args, "{$filter} must receive the exact arguments it documents." );

					return $reply;
				}
			)
		);
	}

	/**
	 * Registers a function expectation that must receive exactly $exact_args.
	 *
	 * WP_Mock::userFunction()->with() and Mockery's with() compare loosely (array() matches
	 * false, '' and null; true matches 1), so the 'return' closure re-checks the real
	 * arguments with assertSame().
	 *
	 * @param string $name       The function name.
	 * @param array  $exact_args The exact arguments, types included.
	 * @param mixed  $reply      The value the function returns.
	 * @param int    $times      How many calls are expected.
	 * @return void
	 */
	protected function expect_function_strict( $name, array $exact_args, $reply, $times = 1 ) {
		WP_Mock::userFunction(
			$name,
			array(
				'times'  => $times,
				'return' => function ( ...$args ) use ( $name, $exact_args, $reply ) {
					$this->assertSame( $exact_args, $args, "{$name}() must receive the exact arguments." );

					return $reply;
				},
			)
		);
	}

	/**
	 * Redirects error_log() into $logged_errors, so a test can assert on what was logged.
	 *
	 * WP_Mock::userFunction() refuses internal functions, so this redefines error_log with
	 * Patchwork directly; error_log must be listed in patchwork.json's redefinable-internals.
	 * tear_down() restores it.
	 *
	 * @return void
	 */
	protected function capture_error_log() {
		$this->error_log_handle = \Patchwork\redefine(
			'error_log',
			function ( $message ) {
				$this->logged_errors[] = $message;

				return true;
			}
		);
	}

	/**
	 * Restores the real error_log(), if capture_error_log() redefined it.
	 *
	 * @return void
	 */
	private function restore_error_log() {
		if ( null !== $this->error_log_handle ) {
			\Patchwork\restore( $this->error_log_handle );
			$this->error_log_handle = null;
		}
	}

	/**
	 * A faithful port of WordPress core's checked() (via __checked_selected_helper()).
	 *
	 * @param mixed $checked The value to test.
	 * @param mixed $current The value to compare against.
	 * @param bool  $display Whether to echo the result.
	 * @return string
	 */
	protected static function core_checked( $checked, $current = true, $display = true ) {
		$is_checked = (string) $checked === (string) $current;

		if ( $display && $is_checked ) {
			echo " checked='checked'";
		}

		return $is_checked ? " checked='checked'" : '';
	}

	/**
	 * Reads a non-public property, for asserting state the plugin keeps internally.
	 *
	 * @param object $instance The object.
	 * @param string $property The property name.
	 * @return mixed
	 */
	protected function get_property( $instance, $property ) {
		$ref = new ReflectionProperty( $instance, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true ); // No-op since PHP 8.1, deprecated in 8.5.
		}

		return $ref->getValue( $instance );
	}

	/**
	 * Writes a non-public property, for arranging state the plugin keeps internally.
	 *
	 * @param object $instance The object.
	 * @param string $property The property name.
	 * @param mixed  $value    The value.
	 * @return void
	 */
	protected function set_property( $instance, $property, $value ) {
		$ref = new ReflectionProperty( $instance, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true ); // No-op since PHP 8.1, deprecated in 8.5.
		}
		$ref->setValue( $instance, $value );
	}
}
