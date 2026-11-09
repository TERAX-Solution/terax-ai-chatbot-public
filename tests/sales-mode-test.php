<?php
/**
 * Standalone test for TX-470 (sales mode and lead form), no WordPress needed.
 *
 *   php tests/sales-mode-test.php
 *
 * Covers: the plan and industry gate (never for dental), the server-side guard on save, the sales section of the
 * prompt (and its absence in informative mode), lead form validation (consent, honeypot, contact) and the separate
 * rate-limit bucket. Move to PHPUnit when TX-428 sets up the infrastructure.
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

	use TeraxAI\Chat\LeadService;
	use TeraxAI\Chat\PromptBuilder;
	use TeraxAI\Chat\RateLimiter;
	use TeraxAI\Chat\SalesMode;

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$fails;
		}
	}
	function setup_site( $industry, $mode, $plan_has_sales, $with_texts = true ) {
		$preset                  = \TeraxAI\Support\Industry::get( $industry );
		$GLOBALS['t_settings']   = array(
			'assistant.industry' => $industry,
			'assistant.mode'     => $mode,
			'assistant.phone'    => '+49 30 1234567',
		);
		if ( $with_texts && 'dental' !== $industry ) {
			$GLOBALS['t_settings']['assistant.business_type'] = $preset['business_type'];
			$GLOBALS['t_settings']['assistant.name']          = $preset['assistant_name'];
		}
		$GLOBALS['t_can'] = array( 'sales_mode' => $plan_has_sales );
	}
	function prompt( $booking = true ) {
		return PromptBuilder::system( array( array( 'title' => 'T', 'url' => 'https://example.test/', 'content' => 'Klasse B kostet 2.000 Euro.' ) ), $booking );
	}

	// 1. The gate: plan AND industry.
	setup_site( 'driving_school', 'sales', true );
	check( 'driving school + sales + plan with sales_mode: enabled', SalesMode::enabled() );
	setup_site( 'driving_school', 'sales', false );
	check( 'plan without sales_mode: not enabled even if the setting says sales', ! SalesMode::enabled() );
	setup_site( 'dental', 'sales', true );
	check( 'dental: never enabled, even with the plan feature and mode = sales', ! SalesMode::enabled() );
	setup_site( 'driving_school', 'informative', true );
	check( 'informative mode: not enabled', ! SalesMode::enabled() );

	// 2. Server-side guard on save.
	setup_site( 'dental', 'informative', true );
	$saved = SalesMode::enforce( array( 'assistant' => array( 'industry' => 'dental', 'mode' => 'sales' ) ) );
	check( 'saving mode = sales for dental is turned back to informative', 'informative' === $saved['assistant']['mode'] );
	$GLOBALS['t_can'] = array( 'sales_mode' => false );
	$saved            = SalesMode::enforce( array( 'assistant' => array( 'industry' => 'general', 'mode' => 'sales' ) ) );
	check( 'saving mode = sales without the plan feature is turned back to informative', 'informative' === $saved['assistant']['mode'] );
	$GLOBALS['t_can'] = array( 'sales_mode' => true );
	$saved            = SalesMode::enforce( array( 'assistant' => array( 'industry' => 'general', 'mode' => 'sales' ) ) );
	check( 'saving mode = sales for general with the plan feature is kept', 'sales' === $saved['assistant']['mode'] );

	// 3. Prompt.
	setup_site( 'driving_school', 'informative', true );
	$informative = prompt();
	check( 'informative: no sales section and no lead token', false === strpos( $informative, '## Sales mode' ) && false === strpos( $informative, PromptBuilder::LEAD_TOKEN ) );

	setup_site( 'driving_school', 'sales', true );
	$sales_book = prompt( true );
	check( 'sales + booking: has the sales section, the CTA text and the booking token rule', false !== strpos( $sales_book, '## Sales mode' ) && false !== strpos( $sales_book, 'Jetzt anmelden' ) );
	check( 'sales + booking: the buying-interest rule uses the booking token, not the lead token', false !== strpos( $sales_book, 'with the exact token ' . PromptBuilder::BOOKING_TOKEN ) && false === strpos( $sales_book, 'with the exact token ' . PromptBuilder::LEAD_TOKEN ) );
	$sales_lead = prompt( false );
	check( 'sales without booking: the buying-interest rule uses the lead token', false !== strpos( $sales_lead, 'with the exact token ' . PromptBuilder::LEAD_TOKEN ) );
	check( 'sales: asks for a nudge at most every 2-3 replies and not twice in a row', false !== strpos( $sales_lead, 'at most once every 2-3 replies' ) && false !== strpos( $sales_lead, 'last two replies' ) );
	check( 'sales: forbids inventing discounts, prices or availability', false !== strpos( $sales_lead, 'Never invent discounts, prices, availability or deadlines' ) );
	check( 'sales: still no medical wording for driving school', ! preg_match( '/doctor|112|diagnos|medication|treatment|practice|health/i', $sales_lead ) );

	setup_site( 'dental', 'sales', true );
	check( 'dental with mode = sales: prompt has no sales section', false === strpos( prompt(), '## Sales mode' ) );

	// 4. Lead form validation.
	$ok = array(
		'name'       => '  Max   Muster ',
		'email'      => 'max@example.test',
		'phone'      => '',
		'message'    => 'Ich möchte mich anmelden.',
		'consent'    => 'true',
		'website'    => '',
		'session_id' => 'abc-123_<script>',
		'page_url'   => 'https://example.test/anmeldung',
	);
	$row = LeadService::validate( $ok );
	check( 'valid lead (e-mail only) is accepted', is_array( $row ) && 'Max Muster' === $row['name'] && 'max@example.test' === $row['email'] );
	check( 'session id is reduced to safe characters', is_array( $row ) && 'abc-123script' === $row['session_id'] );
	check( 'honeypot filled: treated as spam', 'spam' === LeadService::validate( array_merge( $ok, array( 'website' => 'http://spam.test' ) ) ) );
	check( 'no consent: rejected', 'consent' === LeadService::validate( array_merge( $ok, array( 'consent' => 'false' ) ) ) );
	check( 'consent missing: rejected', 'consent' === LeadService::validate( array_diff_key( $ok, array( 'consent' => 1 ) ) ) );
	check( 'no name: rejected', 'name' === LeadService::validate( array_merge( $ok, array( 'name' => '   ' ) ) ) );
	check( 'neither phone nor e-mail: rejected', 'contact' === LeadService::validate( array_merge( $ok, array( 'email' => '', 'phone' => '' ) ) ) );
	check( 'phone with 3 digits only: rejected', 'contact' === LeadService::validate( array_merge( $ok, array( 'email' => '', 'phone' => '123' ) ) ) );
	check( 'invalid e-mail: rejected', 'email' === LeadService::validate( array_merge( $ok, array( 'email' => 'not-an-email' ) ) ) );
	$phone_row = LeadService::validate( array_merge( $ok, array( 'email' => '', 'phone' => '+49 (30) 123 456 7 ext' ) ) );
	check( 'phone only is accepted and letters are removed', is_array( $phone_row ) && '' === $phone_row['email'] && '+49 (30) 123 456 7' === $phone_row['phone'] );
	$long = LeadService::validate( array_merge( $ok, array( 'message' => str_repeat( 'a', 5000 ) ) ) );
	check( 'message is cut to 1000 characters', is_array( $long ) && 1000 === strlen( $long['message'] ) );
	check( 'array values are rejected', 'name' === LeadService::validate( array_merge( $ok, array( 'name' => array( 'x' ) ) ) ) );
	$injected = LeadService::validate( array_merge( $ok, array( 'email' => "a@b.test
Bcc: x@y.test" ) ) );
	check( 'a newline in the e-mail cannot reach a mail header (rejected, or newline removed)', is_string( $injected ) || false === strpos( $injected['email'], "
" ) );

	// 5. The lead form has its own rate-limit bucket and a limit of its own.
	$GLOBALS['t_transients'] = array();
	$hash                    = str_repeat( 'a', 64 );
	$allowed                 = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		if ( RateLimiter::hit( $hash, 'lead', 5 ) ) {
			++$allowed;
		}
	}
	check( 'lead bucket: 5 per hour, then blocked', 5 === $allowed );
	check( 'the chat bucket is not affected by lead hits', RateLimiter::hit( $hash ) );
	$prefix = 'terax_ai_rl_' . substr( $hash, 0, 32 );
	check( 'the chat keeps its old counter key (no bucket prefix)', (bool) array_filter( array_keys( $GLOBALS['t_transients'] ), function ( $k ) use ( $prefix ) {
		return 0 === strpos( $k, $prefix );
	} ) );

	// 6. No nagging: pause the nudge when the last two replies already closed with a question.
	$h = function ( $role, $text ) {
		return new \TeraxAI\AI\Message( $role, $text );
	};
	check( 'recent_nudge: empty history is false', false === \TeraxAI\Chat\ChatService::recent_nudge( array() ) );
	check( 'recent_nudge: replies without a closing question are false', false === \TeraxAI\Chat\ChatService::recent_nudge( array( $h( 'user', 'Hi' ), $h( 'assistant', 'Hallo. Wie kann ich helfen' ), $h( 'user', 'Preis?' ), $h( 'assistant', 'Klasse B kostet 2.000 Euro.' ) ) ) );
	check( 'recent_nudge: the last reply ends with a question', true === \TeraxAI\Chat\ChatService::recent_nudge( array( $h( 'user', 'Preis?' ), $h( 'assistant', "Klasse B kostet 2.000 Euro.\n\nM\u00f6chten Sie sich anmelden?" ) ) ) );
	check( 'recent_nudge: a question three replies ago does not count', false === \TeraxAI\Chat\ChatService::recent_nudge( array( $h( 'assistant', 'M\u00f6chten Sie sich anmelden?' ), $h( 'assistant', 'A.' ), $h( 'assistant', 'B.' ) ) ) );
	setup_site( 'driving_school', 'sales', true );
	$paused = PromptBuilder::system( array(), true, array( 'recent_nudge' => true ) );
	check( 'prompt with recent_nudge tells the model not to nudge again', false !== strpos( $paused, 'do NOT suggest a next step' ) );
	check( 'prompt without recent_nudge has no such line', false === strpos( prompt(), 'do NOT suggest a next step' ) );
	setup_site( 'driving_school', 'informative', true );
	check( 'informative mode ignores recent_nudge (prompt unchanged)', PromptBuilder::system( array(), true, array( 'recent_nudge' => true ) ) === PromptBuilder::system( array(), true ) );

	echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
	exit( $fails ? 1 : 0 );
}
