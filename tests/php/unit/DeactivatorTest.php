<?php
/**
 * Tests for STF_Deactivator.
 *
 * @package Scripts_To_Footer
 */

/**
 * Unit tests for STF_Deactivator.
 *
 * @covers STF_Deactivator
 */
class DeactivatorTest extends TestCase {

	/**
	 * Deactivate() flushes rewrite rules exactly once, with no arguments (a true would also hard-flush .htaccess).
	 *
	 * @covers STF_Deactivator::deactivate
	 *
	 * @return void
	 */
	public function test_deactivate_flushes_rewrite_rules_once() {
		$this->expect_function_strict( 'flush_rewrite_rules', array(), null );

		STF_Deactivator::deactivate();
	}
}
