<?php
/**
 * PHPUnit bootstrap (TX-428): loads the WordPress test suite and the plugin.
 *
 * Runs inside wp-env (`npm run test:php`), where the test suite and the test database are provided. The location of the
 * suite is read from the environment, so it also works in other setups (a local install of the WordPress tests):
 *
 *   WP_TESTS_DIR                 folder with includes/functions.php of the WordPress test suite
 *   WP_PHPUNIT__DIR              same, as set by the wp-phpunit package
 *   WP_PHPUNIT__TESTS_CONFIG     wp-tests-config.php (set by wp-env)
 *   WP_TESTS_CONFIG_FILE_PATH    same, the name the WordPress test suite itself reads
 *
 * @package TeraxAI
 */

$terax_ai_plugin_dir = dirname( __DIR__, 2 );

$terax_ai_candidates = array_filter(
	array(
		getenv( 'WP_TESTS_DIR' ),
		getenv( 'WP_PHPUNIT__DIR' ),
		'/wordpress-phpunit',
		'/tmp/wordpress-tests-lib',
	)
);

$terax_ai_tests_dir = '';
foreach ( $terax_ai_candidates as $terax_ai_candidate ) {
	if ( is_readable( rtrim( $terax_ai_candidate, '/' ) . '/includes/functions.php' ) ) {
		$terax_ai_tests_dir = rtrim( $terax_ai_candidate, '/' );
		break;
	}
}

if ( '' === $terax_ai_tests_dir ) {
	fwrite( STDERR, "Could not find the WordPress test suite (includes/functions.php).\nTried: " . implode( ', ', $terax_ai_candidates ) . "\nRun the tests with: npm run test:php (needs Docker and wp-env), or set WP_TESTS_DIR.\n" );
	exit( 1 );
}

// wp-env names the config file WP_PHPUNIT__TESTS_CONFIG, the WordPress test suite reads WP_TESTS_CONFIG_FILE_PATH.
if ( false === getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) && false !== getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_TESTS_CONFIG_FILE_PATH=' . getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

// The WordPress test suite needs the PHPUnit polyfills from Composer.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $terax_ai_plugin_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $terax_ai_tests_dir . '/includes/functions.php';

// Load the plugin before WordPress finishes starting, and create its tables (what activation does).
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $terax_ai_plugin_dir ) {
		require $terax_ai_plugin_dir . '/terax-ai-assistant.php';
		// The Pro add-on starts on plugins_loaded priority 20, after the free plugin. It is skipped for the "free plugin alone"
		// run (TERAX_TEST_NO_PRO=1) and absent in the public copy of the free plugin.
		if ( ! getenv( 'TERAX_TEST_NO_PRO' ) && is_readable( $terax_ai_plugin_dir . '/pro/terax-ai-assistant-pro.php' ) ) {
			require $terax_ai_plugin_dir . '/pro/terax-ai-assistant-pro.php';
		}
	}
);
tests_add_filter(
	'setup_theme',
	static function () {
		\TeraxAI\Core\Install::activate();
	}
);

require $terax_ai_tests_dir . '/includes/bootstrap.php';
