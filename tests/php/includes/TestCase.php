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
	 * Boots WP_Mock and registers the translation passthroughs before each test.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		WP_Mock::setUp();
		self::reset_expected_hooks();

		foreach ( array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' ) as $i18n ) {
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
	 * @return void
	 */
	protected function tear_down() {
		$this->assertConditionsMet();
		$this->addToAssertionCount( Mockery::getContainer()->mockery_getExpectationCount() );

		WP_Mock::tearDown();

		parent::tear_down();
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
		$manager->setAccessible( true );

		$callbacks = new ReflectionProperty( WP_Mock\EventManager::class, 'callbacks' );
		$callbacks->setAccessible( true );
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
	 * Reads a non-public property, for asserting state the plugin keeps internally.
	 *
	 * @param object $instance The object.
	 * @param string $property The property name.
	 * @return mixed
	 */
	protected function get_property( $instance, $property ) {
		$ref = new ReflectionProperty( $instance, $property );
		$ref->setAccessible( true );

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
		$ref->setAccessible( true );
		$ref->setValue( $instance, $value );
	}
}
