<?php
/**
 * Groups unanswered visitor questions so a practice sees topics, not 40 near-identical lines (TX-460).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Knowledge\KeywordRetriever;

defined( 'ABSPATH' ) || exit;

/**
 * Class Insights
 */
class Insights {

	/**
	 * Two questions are the same topic when at least this share of their keywords overlap.
	 */
	const SIMILARITY = 0.5;

	/**
	 * Group questions by keyword overlap (lower-case, stemmed words of 5+ letters, Jaccard).
	 *
	 * @param array[] $rows Rows with question and created_at, newest first.
	 * @return array[] Groups newest first: question (newest wording), count, last_at, variants (other wordings, max 3).
	 */
	public static function group( array $rows ) {
		$groups = array();
		foreach ( $rows as $row ) {
			$question = trim( (string) ( $row['question'] ?? '' ) );
			if ( '' === $question ) {
				continue;
			}
			$tokens = KeywordRetriever::tokens( $question );
			$match  = null;
			if ( $tokens ) {
				foreach ( $groups as $i => $g ) {
					if ( $g['tokens'] && self::similarity( $tokens, $g['tokens'] ) >= self::SIMILARITY ) {
						$match = $i;
						break;
					}
				}
			}
			if ( null === $match ) {
				$groups[] = array(
					'question' => $question,
					'tokens'   => $tokens,
					'count'    => 1,
					'last_at'  => (string) ( $row['created_at'] ?? '' ),
					'variants' => array(),
				);
				continue;
			}
			++$groups[ $match ]['count'];
			if ( $question !== $groups[ $match ]['question'] && count( $groups[ $match ]['variants'] ) < 3 && ! in_array( $question, $groups[ $match ]['variants'], true ) ) {
				$groups[ $match ]['variants'][] = $question;
			}
		}
		foreach ( $groups as &$g ) {
			unset( $g['tokens'] );
		}
		return $groups;
	}

	/**
	 * Jaccard similarity of two keyword lists.
	 *
	 * @param string[] $a First.
	 * @param string[] $b Second.
	 * @return float 0..1
	 */
	public static function similarity( array $a, array $b ) {
		$union = count( array_unique( array_merge( $a, $b ) ) );
		return $union ? (float) ( count( array_intersect( $a, $b ) ) / $union ) : 0.0;
	}
}
