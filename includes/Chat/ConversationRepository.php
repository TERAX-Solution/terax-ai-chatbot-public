<?php
/**
 * Conversation log storage.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Class ConversationRepository
 */
class ConversationRepository {

	use Base;

	/**
	 * Find or create a conversation by session id.
	 *
	 * @param string $session_id Session id from the widget.
	 * @param string $page_url   Page the chat started on.
	 * @param string $ip_hash    Salted IP hash.
	 * @return int Conversation id.
	 */
	public function find_or_create( $session_id, $page_url, $ip_hash ) {
		global $wpdb;
		$table = terax_ai_table( 'conversations' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id ) );
		if ( $id ) {
			return $id;
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'session_id' => $session_id,
				'page_url'   => mb_substr( $page_url, 0, 500 ),
				'ip_hash'    => $ip_hash,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Append a message.
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $role            user|assistant.
	 * @param string $content         Text.
	 * @param array  $extra           sources, input_tokens, output_tokens, provider, model.
	 * @return void
	 */
	public function add_message( $conversation_id, $role, $content, array $extra = array() ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			terax_ai_table( 'messages' ),
			array(
				'conversation_id' => (int) $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'sources'         => empty( $extra['sources'] ) ? null : wp_json_encode( $extra['sources'] ),
				'answered'        => isset( $extra['answered'] ) && ! $extra['answered'] ? 0 : 1,
				'input_tokens'    => (int) ( $extra['input_tokens'] ?? 0 ),
				'output_tokens'   => (int) ( $extra['output_tokens'] ?? 0 ),
				'created_at'      => $now,
			)
		);

		// message_count = number of visitor questions.
		$conv = terax_ai_table( 'conversations' );
		if ( 'user' === $role ) {
			$this->bump( $conversation_id );
		} elseif ( ! empty( $extra['provider'] ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$conv} SET provider = %s, model = %s WHERE id = %d", $extra['provider'], (string) ( $extra['model'] ?? '' ), $conversation_id ) );
		}
	}

	/**
	 * Count a message without storing its content (when logging is disabled).
	 *
	 * @param int $conversation_id Conversation.
	 * @return void
	 */
	public function bump( $conversation_id ) {
		global $wpdb;
		$conv = terax_ai_table( 'conversations' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$conv} SET message_count = message_count + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $conversation_id ) );
	}

	/**
	 * Count a booking button click.
	 *
	 * @param string $session_id Session id.
	 * @return void
	 */
	public function record_booking_click( $session_id ) {
		global $wpdb;
		$table = terax_ai_table( 'conversations' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET booking_clicks = booking_clicks + 1 WHERE session_id = %s", $session_id ) );
	}

	/**
	 * Paginated list.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Per page.
	 * @param string $search   Search in messages.
	 * @return array { items, total }
	 */
	public function paginate( $page = 1, $per_page = 20, $search = '' ) {
		global $wpdb;
		$conv   = terax_ai_table( 'conversations' );
		$msgs   = terax_ai_table( 'messages' );
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$where  = '1=1';
		$args   = array();

		if ( '' !== $search ) {
			$where  = "id IN (SELECT conversation_id FROM {$msgs} WHERE content LIKE %s)";
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table names come from terax_ai_table(); $where holds fixed fragments with %s placeholders for $args.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$conv} WHERE {$where}", $args ) : "SELECT COUNT(*) FROM {$conv}" );
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The replacements are $args plus the two limits, built with array_merge().
			$wpdb->prepare(
				"SELECT c.*, (SELECT content FROM {$msgs} m WHERE m.conversation_id = c.id AND m.role = 'user' ORDER BY m.id ASC LIMIT 1) AS first_message
				FROM {$conv} c WHERE {$where} ORDER BY c.updated_at DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * One conversation with messages.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function find( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name comes from terax_ai_table(); the id is prepared.
		$conv = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . terax_ai_table( 'conversations' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $conv ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name comes from terax_ai_table(); the id is prepared.
		$messages = $wpdb->get_results( $wpdb->prepare( 'SELECT id, role, content, sources, input_tokens, output_tokens, created_at FROM ' . terax_ai_table( 'messages' ) . ' WHERE conversation_id = %d ORDER BY id ASC', $id ), ARRAY_A );
		foreach ( $messages as &$m ) {
			$m['sources'] = $m['sources'] ? json_decode( $m['sources'], true ) : array();
		}
		$conv['messages'] = $messages;
		return $conv;
	}

	/**
	 * One batch of messages for the CSV export, oldest first, after a given message id (keyset paging,
	 * so a large export never loads everything into memory and never slows down on later pages).
	 *
	 * @param string|null $from_utc Include messages created at or after this UTC datetime.
	 * @param string|null $to_utc   Include messages created before this UTC datetime.
	 * @param int         $after_id Only messages with a higher id.
	 * @param int         $limit    Batch size.
	 * @return array[] Rows: id, created_at, conversation_id, page_url, role, content, sources (decoded).
	 */
	public function export_batch( $from_utc, $to_utc, $after_id, $limit = 500 ) {
		global $wpdb;
		$conv  = terax_ai_table( 'conversations' );
		$msgs  = terax_ai_table( 'messages' );
		$where = array( 'm.id > %d' );
		$args  = array( (int) $after_id );
		if ( $from_utc ) {
			$where[] = 'm.created_at >= %s';
			$args[]  = $from_utc;
		}
		if ( $to_utc ) {
			$where[] = 'm.created_at < %s';
			$args[]  = $to_utc;
		}
		$args[] = (int) $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.id, m.created_at, m.conversation_id, c.page_url, m.role, m.content, m.sources FROM {$msgs} m INNER JOIN {$conv} c ON c.id = m.conversation_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY m.id ASC LIMIT %d', $args ), ARRAY_A );
		foreach ( (array) $rows as &$row ) {
			$row['sources'] = $row['sources'] ? json_decode( $row['sources'], true ) : array();
		}
		return (array) $rows;
	}

	/**
	 * Delete one conversation.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete( terax_ai_table( 'messages' ), array( 'conversation_id' => (int) $id ) );
		$wpdb->delete( terax_ai_table( 'conversations' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete every conversation and message.
	 *
	 * @return int Number of conversations removed.
	 */
	public function delete_all() {
		global $wpdb;
		$conv  = terax_ai_table( 'conversations' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv}" );
		$wpdb->query( 'DELETE FROM ' . terax_ai_table( 'messages' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed statement, the table name comes from terax_ai_table().
		$wpdb->query( "DELETE FROM {$conv}" );
		return $count;
	}

	/**
	 * Delete conversations older than N days.
	 *
	 * @param int $days Days.
	 * @return void
	 */
	public function purge_older_than( $days ) {
		global $wpdb;
		$conv   = terax_ai_table( 'conversations' );
		$msgs   = terax_ai_table( 'messages' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$msgs} WHERE conversation_id IN (SELECT id FROM {$conv} WHERE updated_at < %s)", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$conv} WHERE updated_at < %s", $cutoff ) );
	}

	/**
	 * Replies of the last N days and how many of them the assistant could not answer (needs conversation logging).
	 *
	 * @param int $days Days.
	 * @return array { replies, unanswered }
	 */
	public function answer_stats( $days = 30 ) {
		global $wpdb;
		$msgs  = terax_ai_table( 'messages' );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS replies, COALESCE(SUM(answered = 0),0) AS unanswered FROM {$msgs} WHERE role = 'assistant' AND created_at >= %s", $since ), ARRAY_A );
		return array(
			'replies'    => (int) ( $row['replies'] ?? 0 ),
			'unanswered' => (int) ( $row['unanswered'] ?? 0 ),
		);
	}

	/**
	 * Visitor questions that got an unanswered reply, newest first.
	 *
	 * @param int $days  Days.
	 * @param int $limit Max rows.
	 * @return array[] Rows: question, created_at, conversation_id.
	 */
	public function unanswered_questions( $days = 30, $limit = 500 ) {
		global $wpdb;
		$msgs  = terax_ai_table( 'messages' );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.created_at, a.conversation_id,
				(SELECT u.content FROM {$msgs} u WHERE u.conversation_id = a.conversation_id AND u.role = 'user' AND u.id < a.id ORDER BY u.id DESC LIMIT 1) AS question
				FROM {$msgs} a WHERE a.role = 'assistant' AND a.answered = 0 AND a.created_at >= %s ORDER BY a.id DESC LIMIT %d",
				$since,
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Dashboard stats for the last N days.
	 *
	 * @param int $days Days.
	 * @return array
	 */
	public function stats( $days = 30 ) {
		global $wpdb;
		$conv  = terax_ai_table( 'conversations' );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS conversations, COALESCE(SUM(message_count),0) AS messages, COALESCE(SUM(booking_clicks),0) AS booking_clicks FROM {$conv} WHERE created_at >= %s", $since ), ARRAY_A );

		$daily = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS day, COUNT(*) AS conversations, COALESCE(SUM(booking_clicks),0) AS booking_clicks FROM {$conv} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", $since ), ARRAY_A );

		return array(
			'conversations'  => (int) $row['conversations'],
			'messages'       => (int) $row['messages'],
			'booking_clicks' => (int) $row['booking_clicks'],
			'daily'          => $daily,
		);
	}
}
