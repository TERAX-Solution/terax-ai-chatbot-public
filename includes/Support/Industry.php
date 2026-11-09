<?php
/**
 * Industry presets: one setting switches the assistant's "personality".
 *
 * @package TeraxAI
 */

namespace TeraxAI\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Industry
 *
 * A preset is one PHP file in config/industries/ that returns an array (see dental.php for the keys).
 * Adding an industry = adding one file, or registering it with the `terax_ai_industries` filter.
 */
class Industry {

	const DEFAULT_ID = 'dental';

	/**
	 * All presets, keyed by id (the file name without .php).
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$all = array();
		$dir = TERAX_AI_DIR . 'config/industries/';

		foreach ( (array) glob( $dir . '*.php' ) as $file ) {
			$id = sanitize_key( basename( $file, '.php' ) );
			$p  = require $file;
			if ( is_array( $p ) ) {
				$all[ $id ] = $p;
			}
		}

		// Keep the shipped order stable (dental first), then anything else alphabetically.
		$order = array(
			'dental'         => 0,
			'driving_school' => 1,
			'general'        => 2,
		);
		uksort(
			$all,
			function ( $a, $b ) use ( $order ) {
				return array( $order[ $a ] ?? 99, $a ) <=> array( $order[ $b ] ?? 99, $b );
			}
		);

		/**
		 * Register or change industry presets.
		 *
		 * @param array $all Presets by id.
		 */
		$all = apply_filters( 'terax_ai_industries', $all );

		// Presets from add-ons may omit keys: complete them from the default one.
		$base = isset( $all[ self::DEFAULT_ID ] ) ? $all[ self::DEFAULT_ID ] : array();
		foreach ( $all as $id => $p ) {
			$all[ $id ] = array_merge( $base, (array) $p );
		}

		return $all;
	}

	/**
	 * Id of the industry chosen in the settings. Unknown or unsafe values fall back to dental,
	 * so installs from before presets existed keep their behaviour.
	 *
	 * @return string
	 */
	public static function current_id() {
		$id = sanitize_key( (string) terax_ai_setting( 'assistant.industry', self::DEFAULT_ID ) );
		return isset( self::all()[ $id ] ) ? $id : self::DEFAULT_ID;
	}

	/**
	 * A preset by id; unknown ids give the default (dental) preset.
	 *
	 * @param string $id Industry id.
	 * @return array
	 */
	public static function get( $id ) {
		$all = self::all();
		$id  = sanitize_key( (string) $id );
		return isset( $all[ $id ] ) ? $all[ $id ] : $all[ self::DEFAULT_ID ];
	}

	/**
	 * The active preset.
	 *
	 * @return array
	 */
	public static function current() {
		$all = self::all();
		return $all[ self::current_id() ];
	}

	/**
	 * What the admin needs to switch industries: labels, booking systems and the recommended texts.
	 *
	 * @return array<string,array>
	 */
	public static function for_admin() {
		$out = array();
		foreach ( self::all() as $id => $p ) {
			$out[ $id ] = array(
				'label'           => $p['label'],
				'booking_presets' => array_values( (array) $p['booking_presets'] ),
				'sales_allowed'   => ! empty( $p['sales_allowed'] ),
				'texts'           => array(
					'name'           => $p['assistant_name'],
					'business_type'  => $p['business_type'],
					'greeting'       => $p['greeting'],
					'suggestions'    => array_values( (array) $p['suggestions'] ),
					'consent_text'   => $p['consent_text'],
					'booking_preset' => $p['booking_preset'],
					'booking_label'  => $p['booking_label'],
				),
			);
		}
		return $out;
	}
}
