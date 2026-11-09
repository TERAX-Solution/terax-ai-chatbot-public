<?php
/**
 * Keyword retriever (no embeddings needed – works with every LLM vendor).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Class KeywordRetriever
 *
 * MySQL FULLTEXT when available, merged with a portable LIKE search that
 * matches word stems (useful for German compounds and plurals).
 */
class KeywordRetriever implements RetrieverInterface {

	/**
	 * Stop words (DE + EN) removed from queries.
	 *
	 * @var string[]
	 */
	private static $stop = array( 'und', 'oder', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'ist', 'sind', 'bei', 'mit', 'von', 'für', 'fur', 'auf', 'ich', 'sie', 'wir', 'ihr', 'ihre', 'ihren', 'wie', 'was', 'wann', 'wo', 'kann', 'können', 'konnen', 'haben', 'hat', 'gibt', 'es', 'auch', 'nicht', 'mir', 'mich', 'mein', 'meine', 'bitte', 'danke', 'hallo', 'zu', 'im', 'in', 'an', 'am', 'the', 'and', 'or', 'a', 'an', 'is', 'are', 'do', 'does', 'you', 'your', 'can', 'i', 'my', 'me', 'what', 'when', 'where', 'how', 'to', 'of', 'for', 'in', 'on', 'at', 'with', 'have', 'has', 'there', 'please', 'hello', 'hi', 'thanks', 'about' );

	/**
	 * {@inheritDoc}
	 */
	public function retrieve( $query, $limit = 5 ) {
		$repo  = ChunkRepository::get_instance();
		$terms = self::terms( $query );

		if ( empty( $terms ) ) {
			return array();
		}

		$results = array();

		if ( get_option( 'terax_ai_fulltext' ) ) {
			foreach ( $repo->search_fulltext( implode( ' ', $terms ), $limit ) as $row ) {
				$row['score']          = (float) $row['score'] * 10;
				$results[ $row['id'] ] = $row;
			}
		}

		if ( count( $results ) < $limit ) {
			foreach ( $repo->search_like( self::stems( $terms ), $limit ) as $row ) {
				if ( ! isset( $results[ $row['id'] ] ) ) {
					$results[ $row['id'] ] = $row;
				}
			}
		}

		// Nothing matched (e.g. a German question on English content): give the model the owner's own text and the
		// front page, so it can still answer general questions. Greetings never get here (no search terms).
		if ( empty( $results ) ) {
			return $repo->overview( trailingslashit( home_url() ), min( 3, $limit ) );
		}

		$results = array_values( $results );
		usort(
			$results,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Meaningful lowercase terms of a query.
	 *
	 * @param string $query Query.
	 * @return string[]
	 */
	public static function terms( $query ) {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( (string) $query ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_filter(
			$words,
			function ( $w ) {
				return mb_strlen( $w ) >= 3 && ! in_array( $w, self::$stop, true );
			}
		);
		return array_slice( array_values( array_unique( $words ) ), 0, 12 );
	}

	/**
	 * Stems that appear in almost every answer of a practice assistant and say nothing
	 * about which page an answer came from.
	 *
	 * @var string[]
	 */
	private static $generic = array( 'praxis', 'zahnarztpraxis', 'termin', 'behandl', 'erklär', 'fragen', 'helfen', 'kontakt', 'bitte' );

	/**
	 * Distinct stemmed words of a text (no cap), for overlap checks.
	 *
	 * @param string $text Text.
	 * @param int    $min  Minimum word length.
	 * @return string[]
	 */
	public static function tokens( $text, $min = 5 ) {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_filter(
			$words,
			function ( $w ) use ( $min ) {
				return mb_strlen( $w ) >= $min && ! in_array( $w, self::$stop, true );
			}
		);
		$stems = self::stems( array_values( array_unique( $words ) ), false );
		return array_values( array_diff( $stems, self::$generic ) );
	}

	/**
	 * Query words that mean the same as page words stemming cannot connect
	 * (e.g. "offen" / "open" vs. "Öffnungszeiten"). Keys and values are stems.
	 *
	 * @var array<string,string[]>
	 */
	private static $synonyms = array(
		'offen'          => array( 'öffn', 'uhr' ),
		'open'           => array( 'öffn', 'uhr' ),
		'opening'        => array( 'öffn', 'uhr' ),
		'hours'          => array( 'öffn', 'uhr' ),
		'kostet'         => array( 'preis', 'euro' ),
		'kosten'         => array( 'preis', 'euro' ),
		'preis'          => array( 'kosten', 'euro' ),
		'preise'         => array( 'kosten', 'euro' ),
		'price'          => array( 'preis', 'euro' ),
		'cost'           => array( 'preis', 'euro' ),

		// German question on English content (and the other way round) shares no words with the page.
		// Common business intents are bridged here; anything else falls back to the overview in retrieve().
		'bieten'         => array( 'servic', 'offer', 'leist' ),
		'biete'          => array( 'servic', 'offer', 'leist' ),
		'anbieten'       => array( 'servic', 'offer', 'leist' ),
		'anbietet'       => array( 'servic', 'offer', 'leist' ),
		'angebot'        => array( 'servic', 'offer' ),
		'angebote'       => array( 'servic', 'offer' ),
		'leistung'       => array( 'servic', 'offer' ),
		'leistungen'     => array( 'servic', 'offer' ),
		'dienstleistung' => array( 'servic', 'offer' ),
		'offer'          => array( 'angebot', 'leist' ),
		'offers'         => array( 'angebot', 'leist' ),
		'service'        => array( 'leist', 'angebot' ),
		'services'       => array( 'leist', 'angebot' ),
		'contact'        => array( 'kontakt' ),
		'reach'          => array( 'kontakt' ),
		'kontakt'        => array( 'contact' ),
		'erreichen'      => array( 'contact', 'kontakt' ),
		'kontaktieren'   => array( 'contact' ),
		'unternehmen'    => array( 'company', 'agency', 'team' ),
		'firma'          => array( 'company', 'agency', 'team' ),
		'mitarbeiter'    => array( 'team', 'staff' ),
		'company'        => array( 'unternehmen', 'team' ),
		'agency'         => array( 'unternehmen', 'agentur' ),
		'staff'          => array( 'mitarbeiter', 'team' ),
		'adresse'        => array( 'address', 'location' ),
		'standort'       => array( 'address', 'location' ),
		'anfahrt'        => array( 'address', 'location' ),
		'address'        => array( 'adresse', 'standort' ),
		'location'       => array( 'adresse', 'standort' ),
		'karriere'       => array( 'career', 'job' ),
		'bewerbung'      => array( 'career', 'job' ),
		'career'         => array( 'karriere', 'stellen' ),
		'careers'        => array( 'karriere', 'stellen' ),
		'jobs'           => array( 'karriere', 'stellen' ),
		'referenzen'     => array( 'project', 'portfolio', 'work' ),
		'projekte'       => array( 'project', 'portfolio', 'work' ),
		'projects'       => array( 'projekt', 'referenz' ),
		'portfolio'      => array( 'projekt', 'referenz' ),
		'neuigkeiten'    => array( 'blog', 'news' ),
		'aktuelles'      => array( 'blog', 'news' ),
		'news'           => array( 'aktuell', 'neuigkeit' ),
	);

	/**
	 * Crude stemming: trim common endings so "Implantate" matches "Implantat",
	 * and reduce participles ("geöffnet") to their stem ("öffn").
	 *
	 * @param string[] $terms    Terms.
	 * @param bool     $synonyms Also add the synonym stems (for searching, not for overlap checks).
	 * @return string[]
	 */
	public static function stems( array $terms, $synonyms = true ) {
		$out = array();
		foreach ( $terms as $t ) {
			$word = $t;
			if ( mb_strlen( $t ) > 6 ) {
				$t = preg_replace( '/(ungen|ung|en|er|es|e|n|s)$/u', '', $t );

				// Participle ge-…-t / ge-…-et; keep the stem at 4+ characters.
				if ( preg_match( '/^ge(.{4,}?)(et|t)$/u', $word, $m ) ) {
					$t = $m[1];
				}
			}
			$out[] = $t;

			if ( $synonyms && isset( self::$synonyms[ $word ] ) ) {
				$out = array_merge( $out, self::$synonyms[ $word ] );
			}
		}
		return array_values( array_unique( $out ) );
	}
}
