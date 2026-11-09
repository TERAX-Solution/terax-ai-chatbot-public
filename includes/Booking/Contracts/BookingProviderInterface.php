<?php
/**
 * Booking provider contract.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Booking\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A way to book an appointment.
 *
 * Today: ExternalLink (hand-off to Dr. Flex / Doctolib / Calendly …).
 * Later: a live provider can implement this and return
 * `array( 'type' => 'slots', … )` actions that the widget renders in-chat.
 */
interface BookingProviderInterface {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Admin label.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the provider is configured and usable.
	 *
	 * @return bool
	 */
	public function is_ready();

	/**
	 * Action payload sent to the chat widget when booking is requested.
	 *
	 * @param array $context Chat context (e.g. 'reason' extracted from the conversation).
	 * @return array|null e.g. { type: 'link', url, label, open_in }.
	 */
	public function action( array $context = array() );
}
