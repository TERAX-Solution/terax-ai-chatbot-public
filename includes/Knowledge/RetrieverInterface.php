<?php
/**
 * Retriever contract.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the most relevant site content for a question.
 * Swap in an embeddings / vector-DB retriever with the `terax_ai_retriever` filter.
 */
interface RetrieverInterface {

	/**
	 * Retrieve relevant chunks.
	 *
	 * @param string $query Search query (user question, possibly with context).
	 * @param int    $limit Max chunks.
	 * @return array[] Each: id, post_id, title, url, content, score.
	 */
	public function retrieve( $query, $limit = 5 );
}
