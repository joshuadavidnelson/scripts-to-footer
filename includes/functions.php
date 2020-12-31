<?php
/**
 * Common functions.
 *
 * @package    Scripts_To_Footer
 * @subpackage Scripts_To_Footer/includes
 * @author     Joshua David Nelson <josh@joshuadnelson.com>
 * @copyright  Copyright (c) 2021, Joshua David Nelson
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL-2.0+
 * @link       https://github.com/joshuadavidnelson/scripts-to-footer
 **/

/**
 * Get custom settings.
 *
 * @since 0.6.0
 * @param string $key     The key.
 * @param mixed  $default The default value.
 * @param string $setting The settings field.
 */
function stf_get_option( $key, $default = false, $setting = null ) {

	// Get settings field options.
	$setting = $setting ? $setting : 'scripts-to-footer';
	$options = get_option( $setting, $default );

	if ( ! is_array( $options ) || ! array_key_exists( $key, $options ) ) {
		return '';
	}

	return is_array( $options[ $key ] ) ? stripslashes_deep( $options[ $key ] ) : stripslashes( wp_kses_decode_entities( $options[ $key ] ) );

}
