<?php
/**
 * Data access for knowledge chunks.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Class ChunkRepository
 */
class ChunkRepository {

	use Base;

	/**
	 * Replace all chunks of a source.
	 *
	 * @param int      $post_id Post id (0 = custom knowledge text).
	 * @param string   $title   Title.
	 * @param string   $url     URL.
	 * @param string[] $chunks  Chunk texts.
	 * @return int Number of chunks written.
	 */
	public function replace_for_post( $post_id, $title, $url, array $chunks ) {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );

		$this->delete_for_post( $post_id );

		$now = current_time( 'mysql', true );
		foreach ( array_values( $chunks ) as $i => $content ) {
			$wpdb->insert(
				$table,
				array(
					'post_id'      => (int) $post_id,
					'chunk_index'  => $i,
					'title'        => mb_substr( (string) $title, 0, 250 ),
					'url'          => mb_substr( (string) $url, 0, 500 ),
					'content'      => $content,
					'content_hash' => md5( $content ),
					'updated_at'   => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return count( $chunks );
	}

	/**
	 * Delete chunks of one source.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function delete_for_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( terax_ai_table( 'chunks' ), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}

	/**
	 * Delete chunks not written since a timestamp.
	 *
	 * @param int   $since      Unix time.
	 * @param int[] $except_ids Sources to always keep.
	 * @return void
	 */
	public function delete_stale( $since, array $except_ids = array() ) {
		global $wpdb;
		$ids = implode( ',', array_map( 'intval', $except_ids ? $except_ids : array( -1 ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name comes from terax_ai_table(); $ids is a list of integers; the date is prepared.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . terax_ai_table( 'chunks' ) . " WHERE updated_at < %s AND post_id NOT IN ({$ids})", gmdate( 'Y-m-d H:i:s', (int) $since ) ) );
	}

	/**
	 * Remove everything.
	 *
	 * @return void
	 */
	public function truncate() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . terax_ai_table( 'chunks' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed statement, the table name comes from terax_ai_table().
	}

	/**
	 * Stats for the dashboard.
	 *
	 * @return array
	 */
	public function stats() {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );
		return array(
			'chunks'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'sources' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table}" ),
			'updated' => (string) $wpdb->get_var( "SELECT MAX(updated_at) FROM {$table}" ),
		);
	}

	/**
	 * Indexed sources list.
	 *
	 * @return array
	 */
	public function sources() {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );
		return $wpdb->get_results( "SELECT post_id, title, url, COUNT(*) AS chunks, MAX(updated_at) AS updated_at FROM {$table} GROUP BY post_id, title, url ORDER BY title ASC LIMIT 1000", ARRAY_A );
	}

	/**
	 * Full-text search (MySQL / MariaDB).
	 *
	 * @param string $query Query.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function search_fulltext( $query, $limit ) {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, post_id, title, url, content, MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE) AS score
				FROM {$table}
				WHERE MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE)
				ORDER BY score DESC LIMIT %d",
				$query,
				$query,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * General-purpose chunks for questions that match nothing: the owner's custom text first, then the front page.
	 *
	 * @param string $home_url Front page URL with trailing slash.
	 * @param int    $limit    Limit.
	 * @return array
	 */
	public function overview( $home_url, $limit ) {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, post_id, title, url, content FROM {$table} WHERE post_id = 0 OR url = %s ORDER BY (post_id = 0) DESC, id ASC LIMIT %d", $home_url, (int) $limit ),
			ARRAY_A
		);
		foreach ( (array) $rows as &$row ) {
			$row['score'] = 0.0;
		}
		unset( $row );
		return (array) $rows;
	}

	/**
	 * Portable keyword search (any DB, incl. SQLite). Scores in PHP.
	 *
	 * @param string[] $terms Terms.
	 * @param int      $limit Limit.
	 * @return array
	 */
	public function search_like( array $terms, $limit ) {
		global $wpdb;
		$table = terax_ai_table( 'chunks' );
		if ( empty( $terms ) ) {
			return array();
		}

		$where = array();
		$args  = array();
		foreach ( $terms as $term ) {
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$where[] = '(content LIKE %s OR title LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
		}

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where holds fixed LIKE fragments with %s placeholders for $args.
			$wpdb->prepare( "SELECT id, post_id, title, url, content FROM {$table} WHERE " . implode( ' OR ', $where ) . ' LIMIT 300', $args ),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$haystack_title = mb_strtolower( $row['title'] );
			$haystack       = mb_strtolower( $row['content'] );
			$score          = 0.0;
			foreach ( $terms as $term ) {
				$score += 3 * substr_count( $haystack_title, $term ) + min( 5, substr_count( $haystack, $term ) );
			}
			$row['score'] = $score;
		}
		unset( $row );

		usort(
			$rows,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $rows, 0, $limit );
	}
}
