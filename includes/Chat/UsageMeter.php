<?php
/**
 * Monthly usage counter (for plan limits).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class UsageMeter
 */
class UsageMeter {

	const OPTION = 'terax_ai_usage';

	/**
	 * Current month usage.
	 *
	 * @return array { month, messages, input_tokens, output_tokens }
	 */
	public static function current() {
		$month = gmdate( 'Y-m' );
		$usage = get_option( self::OPTION, array() );
		if ( ! is_array( $usage ) || ( $usage['month'] ?? '' ) !== $month ) {
			$usage = array(
				'month'         => $month,
				'messages'      => 0,
				'input_tokens'  => 0,
				'output_tokens' => 0,
			);
		}
		return $usage;
	}

	/**
	 * Record one answered message.
	 *
	 * @param int $in  Input tokens.
	 * @param int $out Output tokens.
	 * @return void
	 */
	public static function record( $in, $out ) {
		$usage = self::current();
		++$usage['messages'];
		$usage['input_tokens']  += (int) $in;
		$usage['output_tokens'] += (int) $out;
		update_option( self::OPTION, $usage, false );
	}
}
