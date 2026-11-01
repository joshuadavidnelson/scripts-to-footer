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
	 * Deactivate() flushes rewrite rules exactly once.
	 *
	 * @covers STF_Deactivator::deactivate
	 *
	 * @return void
	 */
	public function test_deactivate_flushes_rewrite_rules_once() {
		WP_Mock::userFunction( 'flush_rewrite_rules', array( 'times' => 1 ) );

		STF_Deactivator::deactivate();
	}
}
