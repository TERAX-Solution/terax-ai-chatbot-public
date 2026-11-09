<?php
/**
 * External booking link / widget hand-off.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Booking\Providers;

use TeraxAI\Booking\Contracts\BookingProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Opens the practice's existing online booking page (new tab or modal).
 */
class ExternalLink implements BookingProviderInterface {

	/**
	 * Known booking platforms (labels + URL hint for the admin UI).
	 *
	 * @return array
	 */
	public static function presets() {
		return apply_filters(
			'terax_ai_booking_presets',
			array(
				'drflex'       => array(
					'label' => 'Dr. Flex',
					'hint'  => 'https://…dr-flex.de/… (your practice booking link)',
				),
				'doctolib'     => array(
					'label' => 'Doctolib',
					'hint'  => 'https://www.doctolib.de/…',
				),
				'calendly'     => array(
					'label' => 'Calendly',
					'hint'  => 'https://calendly.com/your-practice',
				),
				'registration' => array(
					'label' => 'Registration form (Anmeldeformular)',
					'hint'  => 'https://…/anmeldung',
				),
				'custom'       => array(
					'label' => 'Custom URL',
					'hint'  => 'https://…',
				),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'external_link';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Booking link (Dr. Flex, Doctolib, Calendly …)', 'terax-ai-assistant' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready() {
		return '' !== (string) terax_ai_setting( 'booking.url', '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function action( array $context = array() ) {
		if ( ! $this->is_ready() ) {
			return null;
		}
		return array(
			'type'    => 'link',
			'url'     => esc_url_raw( terax_ai_setting( 'booking.url' ) ),
			'label'   => (string) terax_ai_setting( 'booking.label', __( 'Book appointment', 'terax-ai-assistant' ) ),
			'open_in' => 'modal' === terax_ai_setting( 'booking.open_in' ) ? 'modal' : 'new_tab',
		);
	}
}
