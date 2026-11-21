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
	 * Activate() flushes rewrite rules exactly once, with no arguments (a true would also hard-flush .htaccess).
	 *
	 * @covers STF_Activator::activate
	 *
	 * @return void
	 */
	public function test_activate_flushes_rewrite_rules_once() {
		$this->expect_function_strict( 'flush_rewrite_rules', array(), null );

		STF_Activator::activate();
	}
}
