<?php
/**
 * Scheduled jobs.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Core;

use TeraxAI\Chat\ConversationRepository;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cron
 *
 * One daily hook other modules attach to (`terax_ai_daily`), plus data retention.
 */
class Cron {

	use Base;

	const DAILY = 'terax_ai_daily';

	/**
	 * Register handlers.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::DAILY, array( $this, 'purge_old_conversations' ) );
	}

	/**
	 * Schedule events.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
	}

	/**
	 * Clear events.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::DAILY );
		wp_clear_scheduled_hook( 'terax_ai_index_batch' );
	}

	/**
	 * GDPR: delete conversations older than the retention period.
	 *
	 * @return void
	 */
	public function purge_old_conversations() {
		$days = (int) terax_ai_setting( 'privacy.retention_days', 30 );

		// The plan caps how long conversations may be kept (Free 7, Basis 30, Praxis and up 90 days).
		$cap = \TeraxAI\Support\Features::limit( 'log_retention_days' );
		if ( $cap > 0 ) {
			$days = $days > 0 ? min( $days, $cap ) : $cap;
		}

		if ( $days > 0 ) {
			ConversationRepository::get_instance()->purge_older_than( $days );
			// Leads left in the chat are personal data too: same retention.
			\TeraxAI\Chat\RequestRepository::get_instance()->purge_older_than( $days );
		}
	}
}
