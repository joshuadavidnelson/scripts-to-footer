<?php
/**
 * PHPUnit bootstrap for the WP_Mock-based unit suite.
 *
 * @package Scripts_To_Footer
 */

// Composer autoloader: PHPUnit, WP_Mock, Mockery and the polyfills.
require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

/*
 * The plugin's own constants. scripts-to-footer.php is deliberately NOT required: it
 * defines STF_PLUGIN_NAME and STF_VERSION without defined() guards and registers
 * WordPress hooks at file scope, so the classes under test are loaded directly instead.
 */
define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
define( 'STF_PLUGIN_NAME', 'scripts-to-footer' );
define( 'STF_VERSION', '0.7.5' );
define( 'STF_DIR', dirname( __DIR__, 3 ) );
define( 'STF_URL', 'https://example.org/wp-content/plugins/scripts-to-footer/' );

WP_Mock::setUsePatchwork( true );

// Strict mode makes any WordPress function, filter or action reached without a stub
// registered for the CURRENT test fail, instead of silently returning null or passing
// the value through. Without it a missing stub only fails when it is the first test in
// the run to reach that function, so results would depend on execution order.
// activateStrictMode() is a no-op once WP_Mock is bootstrapped, so it must come first.
WP_Mock::activateStrictMode();
WP_Mock::bootstrap();

require __DIR__ . '/../Support/class-stf-test-wp-die-exception.php';
require __DIR__ . '/../Support/wp-die.php';
require __DIR__ . '/../includes/TestCase.php';

// Plugin files under test. uninstall.php is procedural and is included per test.
$stf_includes = dirname( __DIR__, 3 ) . '/includes/';
require $stf_includes . 'class-stf-loader.php';
require $stf_includes . 'class-stf-i18n.php';
require $stf_includes . 'class-stf-functions.php';
require $stf_includes . 'functions.php';
require $stf_includes . 'class-stf-admin.php';
require $stf_includes . 'class-stf-public.php';
require $stf_includes . 'class-stf-admin-settings.php';
require $stf_includes . 'class-stf-activator.php';
require $stf_includes . 'class-stf-deactivator.php';
require $stf_includes . 'class-scripts-to-footer.php';
unset( $stf_includes );
