<?php
/**
 * Masks e-mail addresses and phone numbers in text that is stored in the database.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Class PiiMasker
 *
 * Best effort (regex), not a guarantee: names, addresses and health details are not detected.
 * Only the stored copy is masked; the AI provider still receives the original message.
 */
class PiiMasker {

	/**
	 * Replace e-mails with [E-Mail] and phone numbers with [Telefon].
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function mask( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return $text;
		}

		$text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9\-]+(?:\.[A-Z0-9\-]+)*\.[A-Z]{2,}/i', '[E-Mail]', $text );

		// Digit groups joined by space, dash, slash, dot or brackets, optionally starting with + or (.
		return preg_replace_callback(
			'/(?<![\w\[])\(?\+?\d[\d\s\/().\-]{5,}\d(?!\w)/',
			function ( $m ) {
				$candidate = $m[0];
				$digits    = strlen( preg_replace( '/\D/', '', $candidate ) );

				// Dates (12.10.2026, 2026-10-12) are not phone numbers.
				if ( preg_match( '/^\d{1,2}\.\d{1,2}\.\d{2,4}$|^\d{4}-\d{2}-\d{2}$/', $candidate ) ) {
					return $candidate;
				}

				return ( $digits >= 7 && $digits <= 15 ) ? '[Telefon]' : $candidate;
			},
			$text
		);
	}
}
