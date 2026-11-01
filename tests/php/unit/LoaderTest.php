<?php
/**
 * Tests for STF_Loader.
 *
 * @package Scripts_To_Footer
 */

/**
 * @covers STF_Loader
 */
class LoaderTest extends TestCase {

	/**
	 * A new loader starts with empty collections.
	 *
	 * @return void
	 */
	public function test_constructor_initializes_empty_collections() {
		$loader = new STF_Loader();

		$this->assertSame( array(), $this->get_property( $loader, 'actions' ) );
		$this->assertSame( array(), $this->get_property( $loader, 'filters' ) );
	}

	/**
	 * Add_action() and add_filter() queue hooks with the default priority and arg count.
	 *
	 * @return void
	 */
	public function test_add_queues_hooks_with_defaults() {
		$loader    = new STF_Loader();
		$component = new stdClass();

		$loader->add_action( 'init', $component, 'go' );
		$loader->add_filter( 'the_title', $component, 'filter', 5, 2 );

		$this->assertSame(
			array(
				array(
					'hook'          => 'init',
					'component'     => $component,
					'callback'      => 'go',
					'priority'      => 10,
					'accepted_args' => 1,
				),
			),
			$this->get_property( $loader, 'actions' )
		);
		$this->assertSame(
			array(
				array(
					'hook'          => 'the_title',
					'component'     => $component,
					'callback'      => 'filter',
					'priority'      => 5,
					'accepted_args' => 2,
				),
			),
			$this->get_property( $loader, 'filters' )
		);
	}

	/**
	 * Run() registers every queued action and filter with WordPress.
	 *
	 * @return void
	 */
	public function test_run_registers_queued_hooks() {
		$loader    = new STF_Loader();
		$component = new stdClass();

		$loader->add_action( 'init', $component, 'go', 3, 0 );
		$loader->add_action( 'wp_head', $component, 'head' );
		$loader->add_filter( 'the_title', $component, 'filter', 5, 2 );

		WP_Mock::expectActionAdded( 'init', array( $component, 'go' ), 3, 0 );
		WP_Mock::expectActionAdded( 'wp_head', array( $component, 'head' ), 10, 1 );
		WP_Mock::expectFilterAdded( 'the_title', array( $component, 'filter' ), 5, 2 );

		$loader->run();

		$this->assertConditionsMet();
	}
}
