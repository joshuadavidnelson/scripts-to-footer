<?php
/**
 * Common functions.
 *
 * @since      0.6.0
 * @package    Scripts_To_Footer
 * @subpackage functions
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
