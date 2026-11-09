<?php
/**
 * Knowledge index endpoints.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Knowledge\ChunkRepository;
use TeraxAI\Knowledge\Indexer;
use TeraxAI\Knowledge\KeywordRetriever;

defined( 'ABSPATH' ) || exit;

/**
 * Class KnowledgeController
 */
class KnowledgeController {

	/**
	 * GET /knowledge/status — also advances a running rebuild by one batch,
	 * so indexing completes even when WP-Cron is disabled.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$indexer = Indexer::get_instance();
		$state   = 'running' === $indexer->state()['status'] ? $indexer->run_batch() : $indexer->state();
		return rest_ensure_response( $this->format( $state ) );
	}

	/**
	 * POST /knowledge/rebuild
	 *
	 * @return \WP_REST_Response
	 */
	public function rebuild() {
		Indexer::queue_full_rebuild();
		return rest_ensure_response( $this->format( Indexer::get_instance()->run_batch() ) );
	}

	/**
	 * GET /knowledge/sources
	 *
	 * @return \WP_REST_Response
	 */
	public function sources() {
		return rest_ensure_response( ChunkRepository::get_instance()->sources() );
	}

	/**
	 * POST /knowledge/search { query } — lets the admin see what the bot will "read".
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search( \WP_REST_Request $request ) {
		$query     = sanitize_text_field( (string) $request->get_param( 'query' ) );
		$retriever = apply_filters( 'terax_ai_retriever', null );
		$retriever = $retriever ? $retriever : new KeywordRetriever();
		$results   = $retriever->retrieve( $query, (int) terax_ai_setting( 'knowledge.max_chunks', 5 ) );
		return rest_ensure_response(
			array_map(
				function ( $r ) {
					return array(
						'title'   => $r['title'],
						'url'     => $r['url'],
						'score'   => round( (float) $r['score'], 2 ),
						'excerpt' => mb_substr( $r['content'], 0, 400 ),
					);
				},
				$results
			)
		);
	}

	/**
	 * Public state shape.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private function format( array $state ) {
		unset( $state['queue'], $state['keep'] );
		return $state + ChunkRepository::get_instance()->stats();
	}
}
