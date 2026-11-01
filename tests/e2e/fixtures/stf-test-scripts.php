<?php
/**
 * E2E mu-plugin fixture: enqueue a known set of front-end scripts.
 *
 * Every spec asserts where these handles print relative to `</head>`, so each
 * one covers a distinct case the plugin has to handle:
 *
 *   stf-test-header   plain header script, no dependencies.
 *   stf-test-dep-a    header script, the root of a dependency chain.
 *   stf-test-dep-b    header script, depends on stf-test-dep-a.
 *   stf-test-chain    header script, depends on stf-test-dep-b.
 *   stf-test-inline   header script with an inline `before` script, which
 *                     prints as `<script id="stf-test-inline-js-before">`.
 *   stf-test-footer   already registered for the footer.
 *   jquery            core jQuery (prints as jquery-core / jquery-migrate).
 *
 * @package Scripts_To_Footer\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without STF_TEST_FIXTURES: only the e2e environment defines it.
if ( ! defined( 'STF_TEST_FIXTURES' ) ) {
	return;
}

/**
 * Enqueue the fixture scripts on every front-end request.
 *
 * Runs at the default priority, the same as the plugin's own
 * `clean_head()` callback; ordering does not matter because the plugin only
 * removes the `wp_head` printers, it does not touch the queue.
 *
 * @return void
 */
function stf_test_scripts_enqueue() {

	$src = WPMU_PLUGIN_URL . '/stf-test-assets/noop.js';
	$ver = '1.0.0';

	wp_enqueue_script( 'stf-test-header', $src, array(), $ver, false );

	wp_enqueue_script( 'stf-test-dep-a', $src, array(), $ver, false );
	wp_enqueue_script( 'stf-test-dep-b', $src, array( 'stf-test-dep-a' ), $ver, false );
	wp_enqueue_script( 'stf-test-chain', $src, array( 'stf-test-dep-b' ), $ver, false );

	wp_enqueue_script( 'stf-test-inline', $src, array(), $ver, false );
	wp_add_inline_script( 'stf-test-inline', 'window.stfTestInline = true;', 'before' );

	wp_enqueue_script( 'stf-test-footer', $src, array(), $ver, true );

	wp_enqueue_script( 'jquery' );
}
add_action( 'wp_enqueue_scripts', 'stf_test_scripts_enqueue' );
