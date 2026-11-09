<?php
/**
 * Sales mode: gate and helpers.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Support\Features;
use TeraxAI\Support\Industry;

defined( 'ABSPATH' ) || exit;

/**
 * Class SalesMode
 *
 * `assistant.mode = sales` makes the assistant point gently to the next step and offer a lead form.
 * It only counts when the plan has the `sales_mode` feature AND the industry allows it (never for dental),
 * checked here on every use, so a hand-edited option cannot switch it on.
 */
class SalesMode {

	/**
	 * Whether sales mode may be used with these settings (plan and industry).
	 *
	 * @param array|null $settings Settings (null = saved settings).
	 * @return bool
	 */
	public static function allowed( $settings = null ) {
		$settings = null === $settings ? terax_ai_setting() : $settings;
		$industry = Industry::get( (string) ( $settings['assistant']['industry'] ?? Industry::DEFAULT_ID ) );

		return ! empty( $industry['sales_allowed'] ) && Features::can( 'sales_mode' );
	}

	/**
	 * Whether sales mode is switched on and allowed right now.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'sales' === terax_ai_setting( 'assistant.mode', 'informative' ) && self::allowed();
	}

	/**
	 * Server-side guard for saving settings: sales mode falls back to informative when it is not allowed.
	 *
	 * @param array $settings Merged settings about to be saved.
	 * @return array
	 */
	public static function enforce( array $settings ) {
		$mode = $settings['assistant']['mode'] ?? 'informative';
		if ( 'sales' !== $mode || ! self::allowed( $settings ) ) {
			$settings['assistant']['mode'] = 'informative';
		}
		return $settings;
	}

	/**
	 * Text of the lead form button for the active industry.
	 *
	 * @return string
	 */
	public static function cta_label() {
		return (string) ( Industry::current()['cta_label'] ?? 'Anfrage senden' );
	}
}
