<?php
/**
 * The exception the unit suite's wp_die() stand-in throws.
 *
 * @package Scripts_To_Footer
 */

/**
 * The exception wp_die() throws in the unit suite.
 */
class STF_Test_Wp_Die_Exception extends Exception {

	/**
	 * The title passed to wp_die().
	 *
	 * @var mixed
	 */
	public $title;

	/**
	 * The args passed to wp_die().
	 *
	 * @var mixed
	 */
	public $args;

	/**
	 * Builds the exception for a wp_die() call.
	 *
	 * @param mixed $message The wp_die() message, kept as the exception message.
	 * @param mixed $title   The wp_die() title.
	 * @param mixed $args    The wp_die() args.
	 * @return self
	 */
	public static function from_wp_die( $message, $title, $args ) {
		return new self( $message, $title, $args );
	}

	/**
	 * Builds the exception from the arguments wp_die() received.
	 *
	 * @param mixed $message The wp_die() message, kept as the exception message.
	 * @param mixed $title   The wp_die() title.
	 * @param mixed $args    The wp_die() args.
	 */
	public function __construct( $message = '', $title = '', $args = array() ) {
		parent::__construct( $message );
		$this->title = $title;
		$this->args  = $args;
	}
}
