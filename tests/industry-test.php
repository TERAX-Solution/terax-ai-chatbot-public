<?php
/**
 * Standalone test for TX-469 (industry presets), no WordPress needed.
 *
 *   php tests/industry-test.php             run the checks
 *   php tests/industry-test.php --snapshot  rewrite tests/snapshots/prompt-dental.txt (only when the dental prompt
 *                                           is changed on purpose)
 *
 * Covers: the dental prompt is byte-identical to the snapshot taken before the presets existed, other industries
 * contain no medical wording, the booking keyword fallback per industry, unknown ids fall back to dental, and a 4th
 * industry can be added through a filter or one config file. Move to PHPUnit when TX-428 sets up the infrastructure.
 *
 * @package TeraxAI
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'TERAX_AI_DIR', dirname( __DIR__ ) . '/' );

	$GLOBALS['t_settings'] = array();
	$GLOBALS['t_filters']  = array();

	function __( $s ) {
		return $s;
	}
	function apply_filters( $tag, $value ) {
		if ( isset( $GLOBALS['t_filters'][ $tag ] ) ) {
			return call_user_func( $GLOBALS['t_filters'][ $tag ], $value );
		}
		return $value;
	}
	function get_bloginfo() {
		return 'Test Site';
	}
	function wp_specialchars_decode( $s ) {
		return $s;
	}
	function get_locale() {
		return 'de_DE';
	}
	function wp_date() {
		return 'Thursday, 5 November 2026';
	}
	function sanitize_key( $s ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) );
	}
	function trailingslashit( $s ) {
		return rtrim( $s, '/' ) . '/';
	}
	function terax_ai_config( $name ) {
		return require TERAX_AI_DIR . "config/{$name}.php";
	}
	/** Settings: defaults from config/settings.php with test overrides on top ("assistant.industry" => ...). */
	function terax_ai_setting( $path = null, $default = null ) {
		$all = terax_ai_config( 'settings' );
		foreach ( $GLOBALS['t_settings'] as $p => $v ) {
			$keys = explode( '.', $p );
			$ref  = &$all;
			foreach ( $keys as $k ) {
				$ref = &$ref[ $k ];
			}
			$ref = $v;
			unset( $ref );
		}
		if ( null === $path ) {
			return $all;
		}
		$value = $all;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return $default;
			}
			$value = $value[ $key ];
		}
		return $value;
	}
	if ( ! function_exists( 'mb_strtolower' ) ) {
		function mb_strtolower( $s ) {
			return strtolower( $s );
		}
	}
}

namespace TeraxAI\Support {
	/** Stub with switchable features: $GLOBALS['t_can']['sales_mode'] = true. */
	class Features {
		public static function can( $feature ) {
			return ! empty( $GLOBALS['t_can'][ $feature ] );
		}
	}
}

namespace {

	spl_autoload_register(
		function ( $class ) {
			if ( 0 === strpos( $class, 'TeraxAI\\' ) ) {
				$file = TERAX_AI_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
			}
		}
	);

	use TeraxAI\Chat\ChatService;
	use TeraxAI\Chat\PromptBuilder;
	use TeraxAI\Support\Industry;

	$snapshot_file = __DIR__ . '/snapshots/prompt-dental.txt';

	function prompt( $industry = null, $booking = true ) {
		$GLOBALS['t_settings'] = array(
			'assistant.phone' => '+49 30 1234567',
			'assistant.email' => 'info@example.test',
		);
		if ( null !== $industry ) {
			$GLOBALS['t_settings']['assistant.industry'] = $industry;
			// The admin "Apply recommended texts" button copies these into the settings; do the same here.
			$preset = Industry::all()[ $industry ] ?? null;
			if ( $preset && 'dental' !== $industry ) {
				$GLOBALS['t_settings']['assistant.business_type'] = $preset['business_type'];
				$GLOBALS['t_settings']['assistant.name']          = $preset['assistant_name'];
			}
		}
		$chunks = array(
			array(
				'title'   => 'Leistungen',
				'url'     => 'https://example.test/leistungen/',
				'content' => 'Wir bieten Beratung an.',
			),
		);
		return PromptBuilder::system( $chunks, $booking );
	}

	/** Which booking pattern the chat uses for the current industry (same lookup as ChatService). */
	function booking_regex( $industry ) {
		$GLOBALS['t_settings'] = array( 'assistant.industry' => $industry );
		$preset                = Industry::current();
		return $preset['booking_pattern'];
	}

	if ( in_array( '--snapshot', $argv, true ) ) {
		if ( ! is_dir( dirname( $snapshot_file ) ) ) {
			mkdir( dirname( $snapshot_file ), 0777, true );
		}
		file_put_contents( $snapshot_file, prompt() );
		echo "Snapshot written: $snapshot_file (" . strlen( prompt() ) . " bytes)\n";
		exit( 0 );
	}

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$fails;
		}
	}

	// 1. Dental stays exactly as it was.
	// Git may check the snapshot out with CRLF on Windows; the prompt itself uses LF.
	$expected = str_replace( "\r\n", "\n", file_get_contents( $snapshot_file ) );
	check( 'dental prompt (setting not set) is identical to the snapshot', prompt() === $expected );
	check( 'dental prompt (industry = dental) is identical to the snapshot', prompt( 'dental' ) === $expected );
	check( 'dental prompt keeps the medical and 112 rules', false !== strpos( prompt( 'dental' ), 'You are not a doctor' ) && false !== strpos( prompt( 'dental' ), '112' ) );

	// 2. Driving school: no medical wording, own rules.
	$driving = prompt( 'driving_school' );
	check( 'driving school: no "doctor", "112", "diagnos", "medication" or "practice" wording', ! preg_match( '/doctor|112|diagnos|medication|treatment|practice|health/i', $driving ) );
	check( 'driving school: business type is driving school', false !== stripos( $driving, 'driving school' ) );
	check( 'driving school: contains the pass-rate and licence-law rules', false !== stripos( $driving, 'pass rate' ) && false !== stripos( $driving, 'licence' ) );
	check( 'driving school: contact details are still inserted', false !== strpos( $driving, '+49 30 1234567' ) );
	check( 'driving school: booking token rule still present', false !== strpos( $driving, PromptBuilder::BOOKING_TOKEN ) );

	// 3. General business: neutral.
	$general = prompt( 'general' );
	check( 'general: no medical wording', ! preg_match( '/doctor|112|diagnos|medication|treatment|practice|health/i', $general ) );
	check( 'general: still has a safety section', false !== strpos( $general, '## Safety (strict)' ) );

	// 4. Booking keyword fallback per industry.
	$driving_re = booking_regex( 'driving_school' );
	check( 'driving school: "Ich möchte mich anmelden" triggers booking', 1 === preg_match( $driving_re, 'Ich möchte mich anmelden' ) );
	check( 'driving school: "Wie melde ich mich an?" (separable verb) triggers booking', 1 === preg_match( $driving_re, 'Wie melde ich mich an?' ) );
	check( 'driving school: "Wo kann ich mich anmelden?" triggers booking', 1 === preg_match( $driving_re, 'Wo kann ich mich anmelden?' ) );
	check( 'driving school: "Ich melde mich morgen bei Ihnen." does not', 0 === preg_match( $driving_re, 'Ich melde mich morgen bei Ihnen.' ) );
	check( 'driving school: "Gibt es eine Probestunde?" triggers booking', 1 === preg_match( $driving_re, 'Gibt es eine Probestunde?' ) );
	check( 'driving school: "Was kostet der Führerschein?" does not', 0 === preg_match( $driving_re, 'Was kostet der Führerschein?' ) );
	$dental_re = booking_regex( 'dental' );
	check( 'dental: "Ich brauche einen Termin" triggers booking', 1 === preg_match( $dental_re, 'Ich brauche einen Termin' ) );
	check( 'dental: pattern equals the old ChatService constant', ChatService::BOOKING_PATTERN === $dental_re );
	check( 'dental: "Ich möchte mich anmelden" does not (unchanged behaviour)', 0 === preg_match( $dental_re, 'Ich möchte mich anmelden' ) );

	// 5. Fallbacks and extension.
	$GLOBALS['t_settings'] = array( 'assistant.industry' => 'does_not_exist' );
	check( 'unknown industry id falls back to dental', 'dental' === Industry::current_id() );
	$GLOBALS['t_settings'] = array( 'assistant.industry' => '../../etc/passwd' );
	check( 'path-like industry id is rejected', 'dental' === Industry::current_id() );
	check( 'three industries ship by default', array( 'dental', 'driving_school', 'general' ) === array_keys( Industry::all() ) );

	$GLOBALS['t_filters']['terax_ai_industries'] = function ( $all ) {
		$all['gym'] = array_merge(
			$all['general'],
			array(
				'label'       => 'Gym',
				'extra_rules' => array( 'Never promise weight loss results.' ),
			)
		);
		return $all;
	};
	check( 'a 4th industry can be registered with the terax_ai_industries filter', isset( Industry::all()['gym'] ) );
	check( 'the 4th industry rules reach the prompt', false !== strpos( prompt( 'gym' ), 'Never promise weight loss results.' ) );
	$GLOBALS['t_filters'] = array();

	// 6. Every preset is complete.
	$required = array( 'label', 'business_type', 'assistant_name', 'safety_rules', 'extra_rules', 'greeting', 'suggestions', 'booking_presets', 'booking_preset', 'booking_label', 'booking_pattern', 'consent_text', 'sales_allowed', 'cta_label' );
	foreach ( Industry::all() as $id => $preset ) {
		$missing = array_diff( $required, array_keys( $preset ) );
		check( "preset '$id' defines every key", array() === $missing );
		check( "preset '$id' booking pattern is a valid regex", false !== @preg_match( $preset['booking_pattern'], 'x' ) );
		check( "preset '$id' default booking preset is in its list", in_array( $preset['booking_preset'], $preset['booking_presets'], true ) );
	}

	echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
	exit( $fails ? 1 : 0 );
}
