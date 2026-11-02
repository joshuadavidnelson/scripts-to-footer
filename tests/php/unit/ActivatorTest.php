<?php
/**
 * Tests for STF_Activator.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for STF_Activator.
 *
 * @covers STF_Activator
 */
class ActivatorTest extends TestCase {

	/**
	 * Activate() flushes rewrite rules exactly once.
	 *
	 * @covers STF_Activator::activate
	 *
	 * @return void
	 */
	public function test_activate_flushes_rewrite_rules_once() {
		WP_Mock::userFunction( 'flush_rewrite_rules', array( 'times' => 1 ) );

		STF_Activator::activate();
	}
}
