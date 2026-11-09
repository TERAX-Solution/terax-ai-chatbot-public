<?php
/**
 * Feature gates and limits of the free plugin (TX-525).
 *
 * The free plugin works fully on its own: nothing here asks a license. An add-on (the Pro plugin) changes the answers
 * through the `terax_ai_can`, `terax_ai_limit`, `terax_ai_plan` and `terax_ai_plan_label` filters.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Features
 */
class Features {

	/**
	 * What the free plugin allows. Paid features are off until an add-on turns them on.
	 */
	const DEFAULTS = array(
		'byok'              => true,
		'booking'           => true,
		'conversation_log'  => true,
		'managed_ai'        => false,
		'remove_branding'   => false,
		'custom_prompt'     => false,
		'updates'           => false,
		'insights_report'   => false,
		'sales_mode'        => false,
		'callback_requests' => false,
	);

	/**
	 * Feature gate.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function can( $feature ) {
		$can = ! empty( self::DEFAULTS[ $feature ] );

		/**
		 * Filter a feature gate. The Pro add-on switches paid features on.
		 *
		 * @param bool   $can     Allowed.
		 * @param string $feature Feature key.
		 * @param string $plan    Plan slug.
		 */
		return (bool) apply_filters( 'terax_ai_can', $can, $feature, self::plan() );
	}

	/**
	 * Numeric limit. The free plugin has no artificial limits (-1 = unlimited).
	 *
	 * @param string $name Limit key: messages_per_month, indexed_items, log_retention_days.
	 * @return int
	 */
	public static function limit( $name ) {
		/**
		 * Filter a limit.
		 *
		 * @param int    $value Limit, -1 = unlimited.
		 * @param string $name  Limit key.
		 * @param string $plan  Plan slug.
		 */
		return (int) apply_filters( 'terax_ai_limit', -1, $name, self::plan() );
	}

	/**
	 * Plan slug: 'free' unless an add-on says otherwise.
	 *
	 * @return string
	 */
	public static function plan() {
		return (string) apply_filters( 'terax_ai_plan', 'free' );
	}

	/**
	 * Plan name for the admin.
	 *
	 * @return string
	 */
	public static function plan_label() {
		return (string) apply_filters( 'terax_ai_plan_label', 'Free', self::plan() );
	}
}
