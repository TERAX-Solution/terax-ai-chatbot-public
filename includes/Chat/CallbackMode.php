<?php
/**
 * Call-back requests: gate.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Support\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Class CallbackMode
 *
 * The "please call me back" form is offered when the plan has the `callback_requests` feature and the
 * site owner left `assistant.callback` on. Checked on every use, so a hand-edited option cannot unlock it.
 */
class CallbackMode {

	/**
	 * Whether the call-back form may be offered right now.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) terax_ai_setting( 'assistant.callback', true ) && Features::can( 'callback_requests' );
	}
}
