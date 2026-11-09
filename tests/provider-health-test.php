<?php
/**
 * Standalone test for TX-443 (provider failure notice), with mocked HTTP and no WordPress.
 *
 * Run:  php tests/provider-health-test.php
 *
 * Covers the acceptance criteria: 5 failing calls -> notice + exactly 1 e-mail, one success clears it,
 * max 1 e-mail per 24 h, e-mail switch, error classification. The real OpenAI provider is used, only
 * wp_remote_request() is replaced. Move to PHPUnit when TX-428 sets up the test infrastructure.
 *
 * @package TeraxAI
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );

	// ---- Minimal WordPress stubs (in-memory options, transients, user meta, mail log). ----
	$GLOBALS['t_options']    = array();
	$GLOBALS['t_transients'] = array();
	$GLOBALS['t_usermeta']   = array();
	$GLOBALS['t_mails']      = array();
	$GLOBALS['t_http']       = array(); // Queue of mocked responses.
	$GLOBALS['t_filters']    = array();

	function __( $s ) {
		return $s;
	}
	function esc_html_e( $s ) {
		echo htmlspecialchars( $s );
	}
	function esc_html( $s ) {
		return htmlspecialchars( $s );
	}
	function esc_url( $s ) {
		return $s;
	}
	function esc_js( $s ) {
		return $s;
	}
	function add_action() {}
	function wp_create_nonce() {
		return 'nonce';
	}
	function admin_url( $p = '' ) {
		return 'https://example.test/wp-admin/' . $p;
	}
	function current_user_can() {
		return true;
	}
	function get_current_user_id() {
		return 1;
	}
	function get_user_meta( $u, $k ) {
		return $GLOBALS['t_usermeta'][ $u ][ $k ] ?? '';
	}
	function update_user_meta( $u, $k, $v ) {
		$GLOBALS['t_usermeta'][ $u ][ $k ] = $v;
	}
	function get_option( $k, $d = false ) {
		return array_key_exists( $k, $GLOBALS['t_options'] ) ? $GLOBALS['t_options'][ $k ] : $d;
	}
	function update_option( $k, $v ) {
		$GLOBALS['t_options'][ $k ] = $v;
		return true;
	}
	function delete_option( $k ) {
		unset( $GLOBALS['t_options'][ $k ] );
		return true;
	}
	function get_transient( $k ) {
		return $GLOBALS['t_transients'][ $k ] ?? false;
	}
	function set_transient( $k, $v ) {
		$GLOBALS['t_transients'][ $k ] = $v;
		return true;
	}
	function wp_parse_args( $a, $d ) {
		return array_merge( $d, $a );
	}
	function wp_specialchars_decode( $s ) {
		return $s;
	}
	function is_email( $e ) {
		return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
	}
	function wp_mail( $to, $subject, $body ) {
		$GLOBALS['t_mails'][] = compact( 'to', 'subject', 'body' );
		return true;
	}
	function apply_filters( $tag, $value ) {
		return $GLOBALS['t_filters'][ $tag ] ?? $value;
	}
	function wp_json_encode( $v ) {
		return json_encode( $v );
	}
	function is_wp_error( $r ) {
		return $r instanceof \WP_Error;
	}
	function wp_remote_retrieve_response_code( $r ) {
		return $r['code'];
	}
	function wp_remote_retrieve_body( $r ) {
		return $r['body'];
	}
	function terax_ai_setting( $path, $default = null ) {
		return $GLOBALS['t_settings'][ $path ] ?? $default;
	}
	// Mocked HTTP: pops the next queued response.
	function wp_remote_request() {
		return array_shift( $GLOBALS['t_http'] );
	}
	if ( ! function_exists( 'mb_substr' ) ) {
		function mb_substr( $s, $a, $l = null ) {
			return substr( $s, $a, $l );
		}
	}

	class WP_Error {
		private $m;
		public function __construct( $c, $m ) {
			$this->m = $m;
		}
		public function get_error_message() {
			return $this->m;
		}
	}

	spl_autoload_register(
		function ( $class ) {
			if ( 0 === strpos( $class, 'TeraxAI\\' ) ) {
				$file = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
			}
		}
	);
}

namespace {

	use TeraxAI\Admin\Notices;
	use TeraxAI\AI\ChatRequest;
	use TeraxAI\AI\Message;
	use TeraxAI\AI\ProviderException;
	use TeraxAI\AI\ProviderHealth;
	use TeraxAI\AI\Providers\OpenAI;

	$GLOBALS['t_settings'] = array( 'ai.notify_email' => true );
	$GLOBALS['t_options']['admin_email'] = 'admin@example.test';
	$GLOBALS['t_options']['blogname']    = 'Test Site';

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$fails;
		}
	}
	function http( $code, $body = array() ) {
		$GLOBALS['t_http'][] = array(
			'code' => $code,
			'body' => json_encode( $body ),
		);
	}
	function reset_state() {
		$GLOBALS['t_options'] = array(
			'admin_email' => 'admin@example.test',
			'blogname'    => 'Test Site',
		);
		$GLOBALS['t_transients'] = array();
		$GLOBALS['t_usermeta']   = array();
		$GLOBALS['t_mails']      = array();
		$GLOBALS['t_http']       = array();
		$GLOBALS['t_filters']    = array();
		$GLOBALS['t_settings']   = array( 'ai.notify_email' => true );
	}
	/** One chat call the way ChatService does it. */
	function call_chat() {
		$provider = new OpenAI( 'openai', 'sk-test' );
		$request  = new ChatRequest( 'sys', array( new Message( 'user', 'Hi' ) ), 'gpt-4o', 100, 0.3 );
		try {
			$provider->chat( $request );
			ProviderHealth::get_instance()->success();
			return true;
		} catch ( ProviderException $e ) {
			ProviderHealth::get_instance()->failure( $e );
			return false;
		}
	}
	function notice_html() {
		ob_start();
		Notices::get_instance()->provider_failing();
		return ob_get_clean();
	}

	$health = ProviderHealth::get_instance();
	$err401 = array( 'error' => array( 'message' => 'Incorrect API key provided' ) );

	// 1. Four failures: no notice, no mail.
	reset_state();
	for ( $i = 0; $i < 4; $i++ ) {
		http( 401, $err401 );
		call_chat();
	}
	check( '4 x 401: counter is 4', 4 === $health->state()['count'] );
	check( '4 x 401: not failing yet, no notice', ! $health->is_failing() && '' === notice_html() );
	check( '4 x 401: no e-mail', 0 === count( $GLOBALS['t_mails'] ) );

	// 2. Fifth failure: notice and exactly one e-mail.
	http( 401, $err401 );
	call_chat();
	check( '5 x 401: failing', $health->is_failing() );
	check( '5 x 401: notice shows the key reason', false !== strpos( notice_html(), 'API key is invalid' ) );
	check( '5 x 401: exactly 1 e-mail to the site admin', 1 === count( $GLOBALS['t_mails'] ) && 'admin@example.test' === $GLOBALS['t_mails'][0]['to'] );
	check( '5 x 401: e-mail has reason, vendor message and settings link', false !== strpos( $GLOBALS['t_mails'][0]['body'], 'invalid' ) && false !== strpos( $GLOBALS['t_mails'][0]['body'], 'Incorrect API key' ) && false !== strpos( $GLOBALS['t_mails'][0]['body'], '#/settings/ai' ) );

	// 3. More failures within 24 h: still one e-mail.
	for ( $i = 0; $i < 3; $i++ ) {
		http( 401, $err401 );
		call_chat();
	}
	check( 'failures 6-8 within 24 h: still 1 e-mail', 1 === count( $GLOBALS['t_mails'] ) );

	// 4. Dismissing hides the notice for this streak only.
	// dismiss() stores the streak id in user meta (same write as here; it also needs the AJAX stack).
	update_user_meta( 1, Notices::META, $health->state()['since'] );
	check( 'dismissed: notice hidden for this streak', '' === notice_html() );
	$GLOBALS['t_usermeta'] = array();

	// 5. One success clears everything.
	http( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Hello' ) ) ), 'usage' => array() ) );
	check( 'success call returns true', call_chat() );
	check( 'success: counter reset, notice gone', ! $health->is_failing() && 0 === $health->state()['count'] && '' === notice_html() );

	// 6. Classification of common errors.
	$cases = array(
		array( 401, 'API key' ),
		array( 403, 'API key' ),
		array( 402, 'no credit' ),
		array( 429, 'quota' ),
		array( 404, 'model' ),
		array( 500, 'unavailable' ),
		array( 503, 'unavailable' ),
		array( 0, 'network error or timeout' ),
	);
	foreach ( $cases as $c ) {
		check( "reason({$c[0]}) mentions '{$c[1]}'", false !== stripos( ProviderHealth::reason( $c[0] ), $c[1] ) );
	}

	// 7. Transport error (timeout) is classified as status 0 through the real provider.
	reset_state();
	$GLOBALS['t_http'][] = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
	call_chat();
	check( 'timeout: status 0 and message kept', 0 === $health->state()['status'] && false !== strpos( $health->state()['detail'], 'timed out' ) );

	// 8. E-mail switch off: notice yes, mail no.
	reset_state();
	$GLOBALS['t_settings']['ai.notify_email'] = false;
	for ( $i = 0; $i < 5; $i++ ) {
		http( 401, $err401 );
		call_chat();
	}
	check( 'e-mail disabled: notice yes, e-mail no', $health->is_failing() && 0 === count( $GLOBALS['t_mails'] ) );

	// 9. Filterable threshold.
	reset_state();
	$GLOBALS['t_filters']['terax_ai_failure_threshold'] = 2;
	for ( $i = 0; $i < 2; $i++ ) {
		http( 402, array( 'error' => array( 'message' => 'insufficient_quota' ) ) );
		call_chat();
	}
	check( 'threshold filter = 2: failing after 2, 1 e-mail', $health->is_failing() && 1 === count( $GLOBALS['t_mails'] ) );

	// 10. New API key / settings change forgets the streak.
	$health->on_settings_updated( array( 'ai' => array( 'api_key' => 'new' ) ), array( 'ai' => array( 'api_key' => 'old' ) ) );
	check( 'settings change clears the streak', ! $health->is_failing() );

	// 11. Changing only the e-mail switch keeps the streak.
	http( 402, array( 'error' => array( 'message' => 'insufficient_quota' ) ) );
	call_chat();
	$health->on_settings_updated( array( 'ai' => array( 'notify_email' => false, 'model' => 'm' ) ), array( 'ai' => array( 'notify_email' => true, 'model' => 'm' ) ) );
	check( 'only notify_email changed: streak kept', 1 === $health->state()['count'] );

	echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
	exit( $fails ? 1 : 0 );
}
