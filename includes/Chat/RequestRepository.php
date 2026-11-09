<?php
/**
 * Data access for visitor requests (leads from the chat).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Class RequestRepository
 *
 * One row per submitted form: type "lead" (sales mode) or "callback" (TX-459); status "new" or "done".
 */
class RequestRepository {

	use Base;

	/**
	 * Store a request.
	 *
	 * @param array $row type, session_id, page_url, name, phone, email, message, ip_hash.
	 * @return int Row id (0 on failure).
	 */
	public function add( array $row ) {
		global $wpdb;
		$ok = $wpdb->insert(
			terax_ai_table( 'requests' ),
			array(
				'type'       => mb_substr( (string) ( $row['type'] ?? 'lead' ), 0, 20 ),
				'session_id' => mb_substr( (string) ( $row['session_id'] ?? '' ), 0, 64 ),
				'page_url'   => mb_substr( (string) ( $row['page_url'] ?? '' ), 0, 500 ),
				'name'       => mb_substr( (string) ( $row['name'] ?? '' ), 0, 120 ),
				'phone'      => mb_substr( (string) ( $row['phone'] ?? '' ), 0, 50 ),
				'email'      => mb_substr( (string) ( $row['email'] ?? '' ), 0, 190 ),
				'message'    => (string) ( $row['message'] ?? '' ),
				'ip_hash'    => (string) ( $row['ip_hash'] ?? '' ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Page of requests, newest first.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Per page.
	 * @param string $status   '' (all), 'new' or 'done'.
	 * @return array { items, total }
	 */
	public function paginate( $page = 1, $per_page = 20, $status = '' ) {
		global $wpdb;
		$table  = terax_ai_table( 'requests' );
		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * $per_page;
		$where  = in_array( $status, array( 'new', 'done' ), true ) ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, type, status, name, phone, email, message, page_url, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Number of requests nobody has handled yet.
	 *
	 * @return int
	 */
	public function count_new() {
		global $wpdb;
		$table = terax_ai_table( 'requests' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new'" );
	}

	/**
	 * Mark a request as new or done.
	 *
	 * @param int    $id     Request id.
	 * @param string $status 'new' or 'done'.
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'new', 'done' ), true ) ) {
			return false;
		}
		return false !== $wpdb->update( terax_ai_table( 'requests' ), array( 'status' => $status ), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete one request.
	 *
	 * @param int $id Request id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( terax_ai_table( 'requests' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Number of requests created in the last N days.
	 *
	 * @param int $days Days.
	 * @return int
	 */
	public function count_since( $days = 30 ) {
		global $wpdb;
		$table = terax_ai_table( 'requests' );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since ) );
	}

	/**
	 * GDPR: delete requests older than N days (same retention as conversations).
	 *
	 * @param int $days Days.
	 * @return void
	 */
	public function purge_older_than( $days ) {
		global $wpdb;
		$table  = terax_ai_table( 'requests' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}
}
