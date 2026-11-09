<?php
/**
 * Plugin Name: TERAX E2E mock LLM (tests only)
 * Description: Answers calls to the Anthropic API locally so the E2E tests need no key, network or money.
 *
 * Does nothing unless wp-config.php defines TERAX_E2E_TOKEN and the suite has switched the mock on (flag set by
 * e2e-login.php, expires after an hour). Never ship or install this file on a real site.
 *
 * The fake model:
 *  - visitor asks for an appointment ("Termin") -> short reply ending with [[BOOKING]];
 *  - "notoken" in the question -> a booking-related reply that forgets the token (tests the keyword fallback);
 *  - visitor asks about "ufo" (unanswerable on purpose) -> decline ending with [[NO_SOURCES]];
 *  - anything else -> quotes the first <source> of the system prompt, so a source link shows up under the answer.
 * In "fail" mode every call gets an HTTP 500 whose body contains a vendor error text (tests that it never reaches visitors).
 * Every request that reaches the mock is recorded (last 20, read back through e2e-login.php) so tests can assert on the body.
 *
 * @package TeraxAI
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'TERAX_E2E_TOKEN' ) || '' === (string) TERAX_E2E_TOKEN ) {
	return;
}

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		// Only while the suite runs (it switches the flag on in global-setup), never for a developer's own chats.
		$mode = get_transient( 'terax_e2e_mock' );
		if ( ! $mode || 0 !== strpos( $url, 'https://api.anthropic.com/v1/messages' ) ) {
			return $pre;
		}

		$body     = json_decode( (string) ( $args['body'] ?? '' ), true );
		$system   = (string) ( $body['system'] ?? '' );
		$messages = (array) ( $body['messages'] ?? array() );
		$last     = end( $messages );
		$question = is_array( $last ) ? (string) ( $last['content'] ?? '' ) : '';

		$captured   = get_transient( 'terax_e2e_captured' );
		$captured   = is_array( $captured ) ? $captured : array();
		$captured[] = array(
			'url'  => $url,
			'body' => $body,
		);
		set_transient( 'terax_e2e_captured', array_slice( $captured, -20 ), HOUR_IN_SECONDS );

		if ( 'fail' === $mode ) {
			return array(
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'error' => array( 'message' => 'SECRET-VENDOR-ERROR overloaded, key sk-e2e-leak' ) ) ),
				'response' => array(
					'code'    => 500,
					'message' => 'Internal Server Error',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( preg_match( '/notoken/i', $question ) ) {
			$text = 'Gern helfe ich Ihnen bei der Terminvereinbarung.';
		} elseif ( preg_match( '/termin/i', $question ) ) {
			$text = 'Gern! Sie können hier direkt einen Termin buchen. [[BOOKING]]';
		} elseif ( preg_match( '/ufo/i', $question ) ) {
			$text = 'Dazu kann ich Ihnen leider nichts sagen. [[NO_SOURCES]]';
		} elseif ( preg_match( '~<source id="\d+" title="([^"]*)" url="[^"]*">\s*(.*?)\s*</source>~s', $system, $m ) ) {
			$text = sprintf( 'Auf unserer Seite „%s“ steht: %s', $m[1], mb_substr( trim( preg_replace( '/\s+/', ' ', $m[2] ) ), 0, 160 ) );
		} else {
			$text = 'Dazu kann ich Ihnen leider nichts sagen. [[NO_SOURCES]]';
		}

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'model'   => (string) ( $body['model'] ?? 'mock' ),
					'content' => array(
						array(
							'type' => 'text',
							'text' => $text,
						),
					),
					'usage'   => array(
						'input_tokens'  => 10,
						'output_tokens' => 10,
					),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
