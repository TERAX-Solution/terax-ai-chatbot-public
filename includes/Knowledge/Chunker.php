<?php
/**
 * Text chunker.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Splits text into overlapping chunks on paragraph / sentence boundaries.
 */
class Chunker {

	/**
	 * Split text.
	 *
	 * @param string $text    Plain text.
	 * @param int    $size    Target chunk size in characters.
	 * @param int    $overlap Characters carried into the next chunk.
	 * @return string[]
	 */
	public static function split( $text, $size = 1200, $overlap = 150 ) {
		$text = trim( preg_replace( "/[ \t]+/u", ' ', (string) $text ) );
		if ( '' === $text ) {
			return array();
		}

		// Paragraphs first, then sentences for very long paragraphs.
		$pieces = array();
		foreach ( preg_split( "/\n{2,}/u", $text ) as $para ) {
			$para = trim( $para );
			if ( '' === $para ) {
				continue;
			}
			if ( mb_strlen( $para ) <= $size ) {
				$pieces[] = $para;
				continue;
			}
			foreach ( preg_split( '/(?<=[.!?])\s+/u', $para ) as $sentence ) {
				// Hard-split pathological sentences.
				while ( mb_strlen( $sentence ) > $size ) {
					$pieces[] = mb_substr( $sentence, 0, $size );
					$sentence = mb_substr( $sentence, $size );
				}
				$pieces[] = $sentence;
			}
		}

		$chunks  = array();
		$current = '';
		foreach ( $pieces as $piece ) {
			if ( '' !== $current && mb_strlen( $current ) + mb_strlen( $piece ) + 1 > $size ) {
				$chunks[] = $current;
				$tail     = $overlap > 0 ? mb_substr( $current, -$overlap ) : '';
				$current  = '' !== $tail ? $tail . ' ' . $piece : $piece;
			} else {
				$current = '' === $current ? $piece : $current . "\n" . $piece;
			}
		}
		if ( '' !== trim( $current ) ) {
			$chunks[] = $current;
		}

		return $chunks;
	}
}
