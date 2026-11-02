<?php
/**
 * E2E mu-plugin fixture: generic filter-override mechanism for the
 * Playwright suite.
 *
 * A single `show_in_rest` option, `stf_test_filter_overrides`, holds a JSON
 * *string* (a nested object setting does not round-trip reliably through
 * `/wp/v2/settings` on older WordPress) mapping hook name to an override spec:
 *
 *   <bool|int|string>          bare scalar -> the filter returns it verbatim
 *   { "set": <any> }           -> the filter returns the value verbatim
 *   { "append": [ ... ] }      -> array_merge()'d onto the incoming array value
 *   { "remove": [ ... ] }      -> array_diff()'d from the incoming array value
 *
 * Each object form takes an optional "priority" (default 10). Note that the
 * plugin hooks its own `stf_includes()` onto `stf_include` at priority 10 and
 * ignores the incoming value, so an `stf_include` override needs a later
 * priority to win.
 *
 * Registered at file scope, not deferred to an action: mu-plugins load before
 * regular plugins, so these add_filter() calls are in place before the
 * plugin's dynamically-built hook names (`"stf_{$post_type}"`,
 * `"stf_{$type}"`) ever fire.
 *
 * SECURITY GUARD: only hook names starting with `stf_` or
 * `scripts_to_footer_` are registered; anything else is silently skipped, so
 * this cannot become a general-purpose arbitrary-hook injection surface.
 *
 * @package Scripts_To_Footer\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without STF_TEST_FIXTURES: only the e2e environment defines it.
if ( ! defined( 'STF_TEST_FIXTURES' ) ) {
	return;
}

/**
 * Register the `stf_test_filter_overrides` option.
 *
 * @return void
 */
function stf_test_filters_register_settings() {

	register_setting(
		'options',
		'stf_test_filter_overrides',
		array(
			'type'         => 'string',
			'default'      => '',
			'show_in_rest' => true,
			'description'  => 'E2E fixture: JSON-encoded map of hook name to override spec; see stf-test-filters.php.',
		)
	);
}
add_action( 'init', 'stf_test_filters_register_settings' );

/**
 * Resolve a decoded override spec into a mode/value/priority triple.
 *
 * @param mixed $spec Decoded override spec.
 * @return array{mode: string, value: mixed, priority: int}|null Null for an
 *                     object with none of the recognized keys.
 */
function stf_test_filters_resolve_spec( $spec ) {

	$mode     = 'set';
	$value    = $spec;
	$priority = 10;

	if ( is_array( $spec ) ) {
		if ( array_key_exists( 'append', $spec ) ) {
			$mode  = 'append';
			$value = $spec['append'];
		} elseif ( array_key_exists( 'remove', $spec ) ) {
			$mode  = 'remove';
			$value = $spec['remove'];
		} elseif ( array_key_exists( 'set', $spec ) ) {
			$value = $spec['set'];
		} else {
			return null;
		}

		if ( isset( $spec['priority'] ) ) {
			$priority = (int) $spec['priority'];
		}
	}

	return array(
		'mode'     => $mode,
		'value'    => $value,
		'priority' => $priority,
	);
}

/**
 * Decode `stf_test_filter_overrides` and register one filter per entry.
 *
 * @return void
 */
function stf_test_filters_register_overrides() {

	$raw = get_option( 'stf_test_filter_overrides', '' );

	if ( ! is_string( $raw ) || '' === $raw ) {
		return;
	}

	$overrides = json_decode( $raw, true );

	if ( ! is_array( $overrides ) ) {
		return;
	}

	foreach ( $overrides as $hook => $spec ) {

		// SECURITY GUARD -- see the file docblock.
		$hook = (string) $hook;
		if ( 0 !== strpos( $hook, 'stf_' ) && 0 !== strpos( $hook, 'scripts_to_footer_' ) ) {
			continue;
		}

		$resolved = stf_test_filters_resolve_spec( $spec );

		if ( null === $resolved ) {
			continue;
		}

		add_filter(
			$hook,
			static function ( ...$args ) use ( $resolved ) {
				$incoming = isset( $args[0] ) ? $args[0] : null;

				if ( 'append' === $resolved['mode'] ) {
					return array_values( array_unique( array_merge( (array) $incoming, (array) $resolved['value'] ) ) );
				}

				if ( 'remove' === $resolved['mode'] ) {
					return array_values( array_diff( (array) $incoming, (array) $resolved['value'] ) );
				}

				return $resolved['value'];
			},
			$resolved['priority'],
			10
		);
	}
}
stf_test_filters_register_overrides();
