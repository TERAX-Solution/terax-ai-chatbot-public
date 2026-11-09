<?php
/**
 * Booking provider registry.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Booking;

use TeraxAI\Booking\Contracts\BookingProviderInterface;
use TeraxAI\Booking\Providers\ExternalLink;
use TeraxAI\Support\Features;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class BookingRegistry
 */
class BookingRegistry {

	use Base;

	/**
	 * Registered providers.
	 *
	 * @return BookingProviderInterface[] id => instance
	 */
	public function all() {
		/**
		 * Register booking providers. Return id => class name.
		 *
		 * @param array $providers Providers.
		 */
		$classes = apply_filters(
			'terax_ai_booking_providers',
			array( 'external_link' => ExternalLink::class )
		);

		$out = array();
		foreach ( $classes as $id => $class ) {
			if ( class_exists( $class ) && is_subclass_of( $class, BookingProviderInterface::class ) ) {
				$out[ $id ] = new $class();
			}
		}
		return $out;
	}

	/**
	 * Active provider if booking is enabled, allowed and configured.
	 *
	 * @return BookingProviderInterface|null
	 */
	public function active() {
		if ( ! terax_ai_setting( 'booking.enabled' ) || ! Features::can( 'booking' ) ) {
			return null;
		}
		$all = $this->all();
		$id  = (string) terax_ai_setting( 'booking.provider', 'external_link' );
		if ( ! isset( $all[ $id ] ) || ! $all[ $id ]->is_ready() ) {
			return null;
		}
		return $all[ $id ];
	}

	/**
	 * Widget action for the active provider.
	 *
	 * @param array $context Context.
	 * @return array|null
	 */
	public function action( array $context = array() ) {
		$provider = $this->active();
		return $provider ? $provider->action( $context ) : null;
	}
}
