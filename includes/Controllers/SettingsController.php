<?php
/**
 * Settings endpoints.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Booking\Providers\ExternalLink;
use TeraxAI\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsController
 */
class SettingsController {

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get() {
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * POST /settings
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update( \WP_REST_Request $request ) {
		Settings::get_instance()->update( (array) $request->get_json_params() );
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Provider id => problem code, only for providers that have a stored key with a problem.
	 *
	 * @return array
	 */
	private function key_problems() {
		$out      = array();
		$settings = Settings::get_instance();
		foreach ( array_keys( (array) terax_ai_setting( 'ai.api_keys', array() ) ) as $provider ) {
			$problem = $settings->key_problem( $provider );
			if ( $problem ) {
				$out[ $provider ] = $problem;
			}
		}
		return $out;
	}

	/**
	 * Settings + options the UI needs.
	 *
	 * @return array
	 */
	private function payload() {
		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$types[] = array(
					'value' => $type->name,
					'label' => $type->labels->name,
				);
			}
		}

		return array(
			'settings' => Settings::get_instance()->for_admin(),
			'options'  => array(
				'post_types'      => $types,
				'booking_presets' => ExternalLink::presets(),
				'industries'      => \TeraxAI\Support\Industry::for_admin(),
				'key_problems'    => $this->key_problems(),
			),
		);
	}
}
