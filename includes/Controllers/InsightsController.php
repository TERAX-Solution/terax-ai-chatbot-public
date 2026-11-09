<?php
/**
 * Admin endpoint for the Insights page: questions the assistant could not answer (TX-460).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Chat\ConversationRepository;
use TeraxAI\Chat\Insights;
use TeraxAI\Support\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Class InsightsController
 */
class InsightsController {

	/**
	 * GET /insights?days=30
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ) {
		$days    = (int) $request->get_param( 'days' );
		$days    = min( 90, max( 1, 0 !== $days ? $days : 30 ) );
		$logging = (bool) terax_ai_setting( 'privacy.log_conversations' ) && Features::can( 'conversation_log' );
		$repo    = ConversationRepository::get_instance();

		return rest_ensure_response(
			array(
				'logging' => $logging,
				'days'    => $days,
				'stats'   => $repo->answer_stats( $days ),
				'groups'  => Insights::group( $repo->unanswered_questions( $days ) ),
			)
		);
	}
}
