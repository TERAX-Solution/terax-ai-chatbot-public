<?php
/**
 * Dashboard data.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Chat\ConversationRepository;
use TeraxAI\Chat\UsageMeter;
use TeraxAI\Knowledge\ChunkRepository;
use TeraxAI\Knowledge\Indexer;
use TeraxAI\Support\Features;
use TeraxAI\Booking\BookingRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardController
 */
class DashboardController {

	/**
	 * GET /dashboard
	 *
	 * @return \WP_REST_Response
	 */
	public function index() {
		$usage = UsageMeter::current();
		$key   = \TeraxAI\Support\Settings::get_instance()->api_key( (string) terax_ai_setting( 'ai.provider' ) );

		return rest_ensure_response(
			array(
				'stats'      => ConversationRepository::get_instance()->stats( 30 ) + array( 'leads' => \TeraxAI\Chat\RequestRepository::get_instance()->count_since( 30 ) ) + array( 'answers' => ConversationRepository::get_instance()->answer_stats( 30 ) ),
				'usage'      => $usage + array( 'limit' => Features::limit( 'messages_per_month' ) ),
				'index'      => ChunkRepository::get_instance()->stats() + array( 'state' => array_diff_key( Indexer::get_instance()->state(), array_flip( array( 'queue', 'keep' ) ) ) ),
				'license'    => array(
					'plan'       => Features::plan(),
					'plan_label' => Features::plan_label(),
				),
				'sales_mode' => \TeraxAI\Chat\SalesMode::enabled(),
				'checks'     => array(
					'ai_ready'      => ( 'managed' === terax_ai_setting( 'ai.mode' ) || ! Features::can( 'byok' ) ) ? Features::can( 'managed_ai' ) : ( '' !== $key || 'openai_compatible' === terax_ai_setting( 'ai.provider' ) ),
					// Managed AI is selected but the plan no longer includes it (downgrade, expiry): the chat cannot answer.
					// Stored key that cannot be used: unreadable after a salt change or move, or kept as plain text without OpenSSL (TX-503).
					'key_problem'   => \TeraxAI\Support\Settings::get_instance()->key_problem( (string) terax_ai_setting( 'ai.provider' ) ),
					'managed_lost'  => 'managed' === terax_ai_setting( 'ai.mode' ) && ! Features::can( 'managed_ai' ),
					'booking_ready' => null !== BookingRegistry::get_instance()->active(),
					'enabled'       => (bool) terax_ai_setting( 'assistant.enabled' ),
					'fulltext'      => (bool) get_option( 'terax_ai_fulltext' ),
					'setup_done'    => (bool) terax_ai_setting( 'setup.completed' ),
				),
				'last_error' => get_option( 'terax_ai_last_error', null ),
			)
		);
	}
}
