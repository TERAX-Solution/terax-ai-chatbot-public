<?php
/**
 * Uninstall: remove all plugin data.
 *
 * @package TeraxAI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// vendor/ is not committed; fall back to the built-in autoloader (same as the main plugin file).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/autoload-fallback.php';
}

\TeraxAI\Database\Migrations\Schema::down();

foreach ( array( 'terax_ai_settings', 'terax_ai_license', 'terax_ai_db_version', 'terax_ai_fulltext', 'terax_ai_index_state', 'terax_ai_usage', 'terax_ai_last_error' ) as $terax_ai_option ) {
	delete_option( $terax_ai_option );
}

delete_transient( 'terax_ai_index_lock' );
delete_transient( 'terax_ai_update_info' );

// Rate-limit counters have a per-visitor, per-hour key, so remove them by prefix.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_terax_ai_rl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_terax_ai_rl_' ) . '%'
	)
);

wp_clear_scheduled_hook( 'terax_ai_daily' );
wp_clear_scheduled_hook( 'terax_ai_index_batch' );
