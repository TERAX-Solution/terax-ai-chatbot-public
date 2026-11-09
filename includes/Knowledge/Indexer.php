<?php
/**
 * Keeps the knowledge index in sync with site content.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

use TeraxAI\Support\Features;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Indexer
 *
 * - Re-indexes a post whenever it is saved / deleted.
 * - Full rebuilds run in batches (WP-Cron, and also driven by the admin UI
 *   polling /knowledge/status so it works where WP-Cron is disabled).
 */
class Indexer {

	use Base;

	const STATE      = 'terax_ai_index_state';
	const BATCH_HOOK = 'terax_ai_index_batch';
	const BATCH_SIZE = 15;
	const CUSTOM_ID  = 0; // post_id used for the "custom knowledge" text.

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'on_save' ), 20, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'trashed_post', array( $this, 'on_delete' ) );
		add_action( self::BATCH_HOOK, array( $this, 'run_batch' ) );
		add_action( 'terax_ai_settings_updated', array( $this, 'on_settings_updated' ), 10, 2 );
	}

	/**
	 * Post saved.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $this->is_indexable( $post ) ) {
			$this->index_post( $post );
		} else {
			ChunkRepository::get_instance()->delete_for_post( $post_id );
		}
	}

	/**
	 * Post deleted / trashed.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function on_delete( $post_id ) {
		ChunkRepository::get_instance()->delete_for_post( $post_id );
	}

	/**
	 * Re-index when knowledge settings change.
	 *
	 * @param array $current New settings.
	 * @param array $old     Old settings.
	 * @return void
	 */
	public function on_settings_updated( $current, $old ) {
		if ( $current['knowledge']['custom_text'] !== $old['knowledge']['custom_text'] ) {
			$this->index_custom_text();
		}
		// Newly excluded pages leave the index at once: the rebuild below only removes chunks older than its start (one-second resolution).
		$added = array_diff( array_map( 'intval', (array) $current['knowledge']['exclude_ids'] ), array_map( 'intval', (array) $old['knowledge']['exclude_ids'] ) );
		foreach ( $added as $id ) {
			ChunkRepository::get_instance()->delete_for_post( $id );
		}
		if ( $current['knowledge']['post_types'] !== $old['knowledge']['post_types'] || $current['knowledge']['exclude_ids'] !== $old['knowledge']['exclude_ids'] ) {
			self::queue_full_rebuild();
		}
	}

	/**
	 * Whether a post should be in the index.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public function is_indexable( $post ) {
		$types   = (array) terax_ai_setting( 'knowledge.post_types', array( 'page', 'post' ) );
		$exclude = array_map( 'intval', (array) terax_ai_setting( 'knowledge.exclude_ids', array() ) );

		$ok = $post instanceof \WP_Post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& in_array( $post->post_type, $types, true )
			&& ! in_array( (int) $post->ID, $exclude, true );

		/**
		 * Filter whether a post is indexed.
		 *
		 * @param bool     $ok   Indexable.
		 * @param \WP_Post $post Post.
		 */
		return (bool) apply_filters( 'terax_ai_is_indexable', $ok, $post );
	}

	/**
	 * Index a single post.
	 *
	 * @param \WP_Post $post Post.
	 * @return int Chunks written.
	 */
	public function index_post( \WP_Post $post ) {
		$text = ContentExtractor::extract( $post );
		$repo = ChunkRepository::get_instance();

		if ( '' === $text ) {
			$repo->delete_for_post( $post->ID );
			return 0;
		}

		// Prefix each chunk with the page title so retrieval & the LLM keep context.
		$title  = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$chunks = array_map(
			function ( $c ) use ( $title ) {
				return $title . "\n" . $c;
			},
			Chunker::split( $text )
		);

		return $repo->replace_for_post( $post->ID, $title, get_permalink( $post ), $chunks );
	}

	/**
	 * Index the admin-provided custom knowledge text.
	 *
	 * @return void
	 */
	public function index_custom_text() {
		$text = (string) terax_ai_setting( 'knowledge.custom_text', '' );
		$repo = ChunkRepository::get_instance();
		if ( '' === trim( $text ) ) {
			$repo->delete_for_post( self::CUSTOM_ID );
			return;
		}
		$repo->replace_for_post( self::CUSTOM_ID, __( 'Practice information', 'terax-ai-assistant' ), home_url( '/' ), Chunker::split( $text ) );
	}

	/**
	 * Queue a full rebuild.
	 *
	 * @return array State.
	 */
	public static function queue_full_rebuild() {
		$types = (array) terax_ai_setting( 'knowledge.post_types', array( 'page', 'post' ) );
		$ids   = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'has_password'   => false,
			)
		);

		$limit = Features::limit( 'indexed_items' );
		$total = count( $ids );
		if ( $limit >= 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		// Existing chunks stay searchable during the rebuild; stale ones are removed at the end.
		// Posts saved while a rebuild runs are indexed by on_save() and kept.
		$state = array(
			'status'   => 'running',
			'queue'    => array_map( 'intval', $ids ),
			'total'    => count( $ids ),
			'done'     => 0,
			'skipped'  => max( 0, $total - count( $ids ) ),
			'started'  => time(),
			'finished' => 0,
		);
		update_option( self::STATE, $state, false );

		wp_clear_scheduled_hook( self::BATCH_HOOK );
		wp_schedule_single_event( time() + 5, self::BATCH_HOOK );

		self::get_instance()->index_custom_text();

		return $state;
	}

	/**
	 * Process one batch of the rebuild queue.
	 *
	 * @return array State.
	 */
	public function run_batch() {
		$state = $this->state();
		if ( 'running' !== $state['status'] ) {
			return $state;
		}

		// Simple lock so cron + admin polling don't double-process.
		if ( get_transient( 'terax_ai_index_lock' ) ) {
			return $state;
		}
		set_transient( 'terax_ai_index_lock', 1, 60 );

		$batch = array_splice( $state['queue'], 0, self::BATCH_SIZE );
		foreach ( $batch as $id ) {
			$post = get_post( $id );
			if ( $post && $this->is_indexable( $post ) ) {
				$this->index_post( $post );
			}
			++$state['done'];
		}

		if ( empty( $state['queue'] ) ) {
			$state['status']   = 'idle';
			$state['finished'] = time();
			// Anything not rewritten since the rebuild started is stale (deleted, excluded, over limit).
			ChunkRepository::get_instance()->delete_stale( $state['started'], array( self::CUSTOM_ID ) );
		} else {
			wp_schedule_single_event( time() + 2, self::BATCH_HOOK );
		}

		update_option( self::STATE, $state, false );
		delete_transient( 'terax_ai_index_lock' );

		return $state;
	}

	/**
	 * Current state.
	 *
	 * @return array
	 */
	public function state() {
		return wp_parse_args(
			(array) get_option( self::STATE, array() ),
			array(
				'status'   => 'idle',
				'queue'    => array(),
				'total'    => 0,
				'done'     => 0,
				'skipped'  => 0,
				'started'  => 0,
				'finished' => 0,
			)
		);
	}
}
