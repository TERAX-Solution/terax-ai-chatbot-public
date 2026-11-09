<?php
/**
 * Public chat endpoint.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Chat\ChatService;
use TeraxAI\Chat\ConversationRepository;
use TeraxAI\Chat\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatController
 */
class ChatController {

	/**
	 * POST /chat
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send( \WP_REST_Request $request ) {
		$result = ChatService::get_instance()->handle(
			array(
				'message'    => $request->get_param( 'message' ),
				'history'    => (array) $request->get_param( 'history' ),
				'session_id' => $request->get_param( 'session_id' ),
				'page_url'   => $request->get_param( 'page_url' ),
				'recent_cta' => $request->get_param( 'recent_cta' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST /callback — "please call me back" form (public, rate limited, honeypot; TX-459).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function callback( \WP_REST_Request $request ) {
		$result = \TeraxAI\Chat\LeadService::get_instance()->submit(
			array(
				'name'           => $request->get_param( 'name' ),
				'phone'          => $request->get_param( 'phone' ),
				'preferred_time' => $request->get_param( 'preferred_time' ),
				'message'        => $request->get_param( 'message' ),
				'consent'        => $request->get_param( 'consent' ),
				'website'        => $request->get_param( 'website' ),
				'session_id'     => $request->get_param( 'session_id' ),
				'page_url'       => $request->get_param( 'page_url' ),
			),
			'callback'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST /lead — lead form of the sales mode (public, rate limited, honeypot).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function lead( \WP_REST_Request $request ) {
		$result = \TeraxAI\Chat\LeadService::get_instance()->submit(
			array(
				'name'       => $request->get_param( 'name' ),
				'phone'      => $request->get_param( 'phone' ),
				'email'      => $request->get_param( 'email' ),
				'message'    => $request->get_param( 'message' ),
				'consent'    => $request->get_param( 'consent' ),
				'website'    => $request->get_param( 'website' ),
				'session_id' => $request->get_param( 'session_id' ),
				'page_url'   => $request->get_param( 'page_url' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST /booking-click — analytics for the booking button.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function booking_click( \WP_REST_Request $request ) {
		// A public endpoint that writes to the DB: cap it per visitor (own bucket, so chat and leads are not affected).
		// Over the limit we still answer ok, so a counted-out visitor sees no error and a bot learns nothing.
		$allowed = RateLimiter::hit( RateLimiter::ip_hash(), 'click', (int) apply_filters( 'terax_ai_booking_click_limit_per_hour', 60 ) );
		$session = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $request->get_param( 'session_id' ) );
		if ( $allowed && $session ) {
			ConversationRepository::get_instance()->record_booking_click( substr( $session, 0, 64 ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
