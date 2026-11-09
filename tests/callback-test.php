<?php
/**
 * Standalone test for TX-459 (call-back form), no WordPress needed.
 *
 *   php tests/callback-test.php
 *
 * Covers: the plan gate and the settings switch, the [[CALLBACK]] rule in the prompt (only when enabled, never together
 * with the booking token), call-back validation (phone is required, consent, honeypot, preferred time) and that
 * the sales-mode lead form still works as before. Move to PHPUnit when TX-428 sets up the infrastructure.
 *
 * @package TeraxAI
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'TERAX_AI_DIR', dirname( __DIR__ ) . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );

	$GLOBALS['t_settings']   = array();
	$GLOBALS['t_can']        = array();
	$GLOBALS['t_transients'] = array();

	function __( $s ) {
		return $s;
	}
	function apply_filters( $tag, $value ) {
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
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_textarea_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_email( $s ) {
		return preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', (string) $s );
	}
	function is_email( $s ) {
		return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL );
	}
	function esc_url_raw( $s ) {
		return filter_var( $s, FILTER_VALIDATE_URL ) ? $s : '';
	}
	function get_transient( $k ) {
		return $GLOBALS['t_transients'][ $k ] ?? false;
	}
	function set_transient( $k, $v ) {
		$GLOBALS['t_transients'][ $k ] = $v;
	}
	function terax_ai_config( $name ) {
		return require TERAX_AI_DIR . "config/{$name}.php";
	}
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
	if ( ! function_exists( 'mb_substr' ) ) {
		function mb_substr( $s, $a, $l = null ) {
			return substr( $s, $a, $l );
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

	use TeraxAI\Chat\CallbackMode;
	use TeraxAI\Chat\LeadService;
	use TeraxAI\Chat\PromptBuilder;

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "
";
		if ( ! $ok ) {
			++$fails;
		}
	}
	function setup_site( $plan_has_callback, $switch = true ) {
		$GLOBALS['t_settings'] = array(
			'assistant.industry' => 'dental',
			'assistant.mode'     => 'informative',
			'assistant.callback' => $switch,
		);
		$GLOBALS['t_can']      = array( 'callback_requests' => $plan_has_callback );
	}
	function prompt( $booking = true ) {
		return PromptBuilder::system( array( array( 'title' => 'T', 'url' => 'https://example.test/', 'content' => 'Wir sind montags bis freitags da.' ) ), $booking );
	}

	// 1. Gate: plan AND the settings switch.
	setup_site( true );
	check( 'plan with callback_requests and switch on: enabled', CallbackMode::enabled() );
	setup_site( true, false );
	check( 'switch off: not enabled', ! CallbackMode::enabled() );
	setup_site( false );
	check( 'plan without callback_requests: not enabled even if the switch is on', ! CallbackMode::enabled() );
	check( 'works for dental (unlike sales mode)', 'dental' === terax_ai_setting( 'assistant.industry' ) );

	// 2. Prompt.
	setup_site( true );
	$on = prompt( false );
	check( 'prompt names the callback token when enabled', false !== strpos( $on, PromptBuilder::CALLBACK_TOKEN ) );
	check( 'prompt forbids mixing it with the booking token', false !== strpos( $on, 'Never use this token together with the booking token' ) );
	setup_site( false );
	check( 'prompt has no callback token when the plan lacks the feature', false === strpos( prompt( false ), PromptBuilder::CALLBACK_TOKEN ) );
	setup_site( true, false );
	check( 'prompt has no callback token when the switch is off', false === strpos( prompt( false ), PromptBuilder::CALLBACK_TOKEN ) );

	// 3. Validation of the call-back form.
	$ok  = array(
		'name'           => 'Erika Muster',
		'phone'          => '+49 30 1234567',
		'preferred_time' => 'morning',
		'message'        => 'Zahnschmerzen seit gestern.',
		'consent'        => 'true',
		'website'        => '',
		'session_id'     => 'abc-123',
		'page_url'       => 'https://example.test/kontakt',
	);
	$row = LeadService::validate( $ok, 'callback' );
	check( 'valid call-back is accepted with type "callback"', is_array( $row ) && 'callback' === $row['type'] && 'Erika Muster' === $row['name'] );
	check( 'the preferred time and reason are stored together in the message', is_array( $row ) && 0 === strpos( $row['message'], 'Preferred time: morning' ) && false !== strpos( $row['message'], 'Zahnschmerzen' ) );
	check( 'no e-mail is stored for a call-back', is_array( $row ) && '' === $row['email'] );
	$any = LeadService::validate( array_merge( $ok, array( 'preferred_time' => 'midnight', 'message' => '' ) ), 'callback' );
	check( 'unknown preferred time becomes "any"', is_array( $any ) && 'Preferred time: any' === $any['message'] );
	check( 'phone is required for a call-back', 'phone' === LeadService::validate( array_merge( $ok, array( 'phone' => '' ) ), 'callback' ) );
	check( 'a phone with 3 digits is rejected', 'phone' === LeadService::validate( array_merge( $ok, array( 'phone' => '123' ) ), 'callback' ) );
	check( 'an e-mail alone is not enough for a call-back', 'phone' === LeadService::validate( array_merge( $ok, array( 'phone' => '', 'email' => 'a@b.test' ) ), 'callback' ) );
	check( 'no consent: rejected', 'consent' === LeadService::validate( array_merge( $ok, array( 'consent' => 'false' ) ), 'callback' ) );
	check( 'no name: rejected', 'name' === LeadService::validate( array_merge( $ok, array( 'name' => ' ' ) ), 'callback' ) );
	check( 'honeypot filled: treated as spam', 'spam' === LeadService::validate( array_merge( $ok, array( 'website' => 'http://spam.test' ) ), 'callback' ) );

	// 4. The sales-mode lead form is unchanged.
	$lead = LeadService::validate( array_merge( $ok, array( 'phone' => '', 'email' => 'max@example.test' ) ) );
	check( 'lead form: e-mail alone is still enough and the type stays "lead"', is_array( $lead ) && 'lead' === $lead['type'] && 'max@example.test' === $lead['email'] );
	check( 'lead form: no "Preferred time" prefix', is_array( $lead ) && false === strpos( $lead['message'], 'Preferred time' ) );

	echo $fails ? "
$fails FAILED
" : "
ALL PASSED
";
	exit( $fails ? 1 : 0 );
}
