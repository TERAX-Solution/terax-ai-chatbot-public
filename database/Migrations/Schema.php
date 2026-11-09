<?php
/**
 * Database schema.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Database\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 *
 * Uses dbDelta (idempotent: creates or alters tables to match). To change the
 * schema, edit the SQL and bump TERAX_AI_DB_VERSION — Install::maybe_upgrade()
 * re-runs this on the next request.
 *
 * Plain $wpdb is used (not a PDO ORM) so the plugin works on every host:
 * socket/port DB_HOST formats, SQLite (Playground) and managed hosting.
 */
class Schema {

	/**
	 * Create / update all tables.
	 *
	 * @return void
	 */
	public static function up() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$chunks  = terax_ai_table( 'chunks' );
		$convs   = terax_ai_table( 'conversations' );
		$msgs    = terax_ai_table( 'messages' );
		$reqs    = terax_ai_table( 'requests' );

		dbDelta(
			"CREATE TABLE {$chunks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			chunk_index int(11) NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			url varchar(500) NOT NULL DEFAULT '',
			content text NOT NULL,
			content_hash char(32) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			FULLTEXT KEY search (title,content)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$convs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			page_url varchar(500) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			message_count int(11) NOT NULL DEFAULT 0,
			booking_clicks int(11) NOT NULL DEFAULT 0,
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$msgs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'user',
			content text NOT NULL,
			sources text NULL,
			answered tinyint(1) NOT NULL DEFAULT 1,
			input_tokens int(11) NOT NULL DEFAULT 0,
			output_tokens int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$reqs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'lead',
			status varchar(10) NOT NULL DEFAULT 'new',
			session_id varchar(64) NOT NULL DEFAULT '',
			page_url varchar(500) NOT NULL DEFAULT '',
			name varchar(120) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			message text NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
			) {$charset};"
		);

		update_option( 'terax_ai_fulltext', self::has_fulltext() ? 1 : 0, false );
	}

	/**
	 * Whether the chunks table has a usable FULLTEXT index (MySQL/MariaDB).
	 *
	 * @return bool
	 */
	public static function has_fulltext() {
		global $wpdb;
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return false;
		}
		$table = terax_ai_table( 'chunks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'search'" );
		return ! empty( $rows );
	}

	/**
	 * Drop all tables.
	 *
	 * @return void
	 */
	public static function down() {
		global $wpdb;
		foreach ( array( 'chunks', 'conversations', 'messages', 'requests' ) as $name ) {
			$table = terax_ai_table( $name );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
