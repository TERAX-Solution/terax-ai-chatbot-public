<?php
/**
 * Standalone test for TX-430 scenario 11: what each provider class sends to the vendor, with mocked HTTP and no WordPress.
 *
 * Run:  php tests/providers-test.php
 *
 * Every provider gets the same normalized ChatRequest; the captured HTTP request (URL, headers, JSON body) is asserted.
 * Only wp_remote_request() is replaced. The chat pipeline itself (ChatService, REST, quota, rate limit) is covered by
 * tests/e2e/chat.spec.js. Move to PHPUnit when TX-428 sets up the test infrastructure.
 *
 * @package TeraxAI
 */

namespace {

	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['t_http']    = array(); // Queue of mocked responses.
	$GLOBALS['t_sent']    = array(); // Captured requests.
	$GLOBALS['t_filters'] = array();

	function __( $s ) {
		return $s;
	}
	function apply_filters( $tag, $value ) {
		return $GLOBALS['t_filters'][ $tag ] ?? $value;
	}
	function wp_json_encode( $v ) {
		return json_encode( $v );
	}
	function is_wp_error( $r ) {
		return false;
	}
	function untrailingslashit( $s ) {
		return rtrim( $s, '/\\' );
	}
	function wp_remote_retrieve_response_code( $r ) {
		return $r['code'];
	}
	function wp_remote_retrieve_body( $r ) {
		return $r['body'];
	}
	function wp_remote_request( $url, $args ) {
		$GLOBALS['t_sent'][] = array(
			'url'     => $url,
			'method'  => $args['method'],
			'headers' => $args['headers'],
			'body'    => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
		);
		return array_shift( $GLOBALS['t_http'] );
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

	use TeraxAI\AI\ChatRequest;
	use TeraxAI\AI\Message;
	use TeraxAI\AI\ProviderException;
	use TeraxAI\AI\Providers\Anthropic;
	use TeraxAI\AI\Providers\Gemini;
	use TeraxAI\AI\Providers\Mistral;
	use TeraxAI\AI\Providers\OpenAI;
	use TeraxAI\AI\Providers\OpenAICompatible;

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
	/** The one request the last call sent. */
	function sent() {
		$all = $GLOBALS['t_sent'];
		$GLOBALS['t_sent'] = array();
		return $all ? $all[0] : null;
	}
	function req( $model, $temperature = 0.3 ) {
		return new ChatRequest(
			'SYSTEM PROMPT',
			array(
				new Message( 'user', 'Frage 1' ),
				new Message( 'assistant', 'Antwort 1' ),
				new Message( 'user', 'Frage 2' ),
			),
			$model,
			500,
			$temperature
		);
	}

	// ---- Anthropic ----
	http(
		200,
		array(
			'model'   => 'claude-x',
			'content' => array( array( 'type' => 'text', 'text' => 'Hallo ' ), array( 'type' => 'text', 'text' => 'Welt' ) ),
			'usage'   => array( 'input_tokens' => 11, 'output_tokens' => 7 ),
		)
	);
	$r = ( new Anthropic( 'anthropic', 'sk-a' ) )->chat( req( 'claude-haiku-4-5' ) );
	$s = sent();
	check( 'Anthropic: POST to the messages endpoint', 'POST' === $s['method'] && 'https://api.anthropic.com/v1/messages' === $s['url'] );
	check( 'Anthropic: key and version headers', 'sk-a' === $s['headers']['x-api-key'] && '2023-06-01' === $s['headers']['anthropic-version'] );
	check( 'Anthropic: system prompt is a top-level field, not a message', 'SYSTEM PROMPT' === $s['body']['system'] && 'system' !== $s['body']['messages'][0]['role'] );
	check( 'Anthropic: messages keep role and content in order', array( array( 'role' => 'user', 'content' => 'Frage 1' ), array( 'role' => 'assistant', 'content' => 'Antwort 1' ), array( 'role' => 'user', 'content' => 'Frage 2' ) ) === $s['body']['messages'] );
	check( 'Anthropic: model, max_tokens, temperature', 'claude-haiku-4-5' === $s['body']['model'] && 500 === $s['body']['max_tokens'] && 0.3 === $s['body']['temperature'] );
	check( 'Anthropic: text blocks joined, tokens read', 'Hallo Welt' === $r->text && 11 === $r->input_tokens && 7 === $r->output_tokens && 'claude-x' === $r->model );

	// A model that rejects temperature: one retry without it.
	http( 400, array( 'error' => array( 'message' => '`temperature` is deprecated for this model' ) ) );
	http( 200, array( 'content' => array( array( 'type' => 'text', 'text' => 'ok' ) ) ) );
	$r  = ( new Anthropic( 'anthropic', 'sk-a' ) )->chat( req( 'claude-new' ) );
	$two = $GLOBALS['t_sent'];
	$GLOBALS['t_sent'] = array();
	check( 'Anthropic: retries once without temperature when the model rejects it', 2 === count( $two ) && isset( $two[0]['body']['temperature'] ) && ! isset( $two[1]['body']['temperature'] ) && 'ok' === $r->text );

	// Other 400 errors are not retried.
	http( 400, array( 'error' => array( 'message' => 'bad request' ) ) );
	$status = null;
	try {
		( new Anthropic( 'anthropic', 'sk-a' ) )->chat( req( 'claude-haiku-4-5' ) );
	} catch ( ProviderException $e ) {
		$status = $e->status;
	}
	check( 'Anthropic: another HTTP 400 is thrown, not retried', 400 === $status && 1 === count( $GLOBALS['t_sent'] ) );
	$GLOBALS['t_sent'] = array();

	// ---- OpenAI ----
	$ok = array(
		'model'   => 'gpt-x',
		'choices' => array( array( 'message' => array( 'content' => 'Antwort' ) ) ),
		'usage'   => array( 'prompt_tokens' => 5, 'completion_tokens' => 3 ),
	);
	http( 200, $ok );
	$r = ( new OpenAI( 'openai', 'sk-o' ) )->chat( req( 'gpt-4o-mini' ) );
	$s = sent();
	check( 'OpenAI: chat completions endpoint with Bearer key', 'https://api.openai.com/v1/chat/completions' === $s['url'] && 'Bearer sk-o' === $s['headers']['Authorization'] );
	check( 'OpenAI: system prompt is the first message', array( 'role' => 'system', 'content' => 'SYSTEM PROMPT' ) === $s['body']['messages'][0] && 4 === count( $s['body']['messages'] ) );
	check( 'OpenAI: max_completion_tokens (not max_tokens)', 500 === $s['body']['max_completion_tokens'] && ! isset( $s['body']['max_tokens'] ) );
	check( 'OpenAI: gpt-4o keeps the temperature', 0.3 === $s['body']['temperature'] );
	check( 'OpenAI: reply and tokens read', 'Antwort' === $r->text && 5 === $r->input_tokens && 3 === $r->output_tokens );

	foreach ( array( 'gpt-5', 'gpt-5-mini', 'o3', 'o4-mini' ) as $model ) {
		http( 200, $ok );
		( new OpenAI( 'openai', 'sk-o' ) )->chat( req( $model ) );
		$s = sent();
		check( "OpenAI: no temperature for $model", ! isset( $s['body']['temperature'] ) );
	}

	http( 200, $ok );
	( new OpenAI( 'openai', 'sk-o' ) )->chat( req( 'gpt-4.1', null ) );
	check( 'OpenAI: no temperature when none is set', ! isset( sent()['body']['temperature'] ) );

	// ---- Gemini ----
	http(
		200,
		array(
			'modelVersion'  => 'gemini-x',
			'candidates'    => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Guten ' ), array( 'text' => 'Tag' ) ) ) ) ),
			'usageMetadata' => array( 'promptTokenCount' => 9, 'candidatesTokenCount' => 4 ),
		)
	);
	$r = ( new Gemini( 'gemini', 'g-key' ) )->chat( req( 'models/gemini-2.5-flash' ) );
	$s = sent();
	check( 'Gemini: generateContent URL, "models/" prefix not doubled', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' === $s['url'] );
	check( 'Gemini: key in the x-goog-api-key header, not in the URL', 'g-key' === $s['headers']['x-goog-api-key'] && false === strpos( $s['url'], 'g-key' ) );
	check( 'Gemini: systemInstruction holds the system prompt', array( 'parts' => array( array( 'text' => 'SYSTEM PROMPT' ) ) ) === $s['body']['systemInstruction'] );
	check( 'Gemini: contents use the roles user / model', array( 'user', 'model', 'user' ) === array_column( $s['body']['contents'], 'role' ) && 'Antwort 1' === $s['body']['contents'][1]['parts'][0]['text'] );
	check( 'Gemini: maxOutputTokens and temperature in generationConfig', 500 === $s['body']['generationConfig']['maxOutputTokens'] && 0.3 === $s['body']['generationConfig']['temperature'] );
	check( 'Gemini: parts joined, tokens read', 'Guten Tag' === $r->text && 9 === $r->input_tokens && 4 === $r->output_tokens && 'gemini-x' === $r->model );

	// ---- Mistral ----
	http( 200, $ok );
	( new Mistral( 'mistral', 'm-key' ) )->chat( req( 'mistral-small-latest' ) );
	$s = sent();
	check( 'Mistral: its own endpoint with Bearer key', 'https://api.mistral.ai/v1/chat/completions' === $s['url'] && 'Bearer m-key' === $s['headers']['Authorization'] );
	check( 'Mistral: classic max_tokens and temperature', 500 === $s['body']['max_tokens'] && ! isset( $s['body']['max_completion_tokens'] ) && 0.3 === $s['body']['temperature'] );
	check( 'Mistral: system prompt is the first message', 'system' === $s['body']['messages'][0]['role'] );

	// ---- OpenAI-compatible (own base URL, key optional) ----
	http( 200, $ok );
	( new OpenAICompatible( 'openai_compatible', '', array( 'base_url' => 'http://localhost:11434/v1/' ) ) )->chat( req( 'llama3' ) );
	$s = sent();
	check( 'OpenAI-compatible: base URL without double slash, no Authorization header without a key', 'http://localhost:11434/v1/chat/completions' === $s['url'] && ! isset( $s['headers']['Authorization'] ) );

	$status = null;
	try {
		( new OpenAICompatible( 'openai_compatible', '', array() ) )->chat( req( 'llama3' ) );
	} catch ( ProviderException $e ) {
		$status = 'thrown';
	}
	check( 'OpenAI-compatible: missing base URL is an error, nothing is sent', 'thrown' === $status && array() === $GLOBALS['t_sent'] );

	// ---- Errors and keys ----
	$status = null;
	try {
		( new OpenAI( 'openai', '' ) )->chat( req( 'gpt-4o' ) );
	} catch ( ProviderException $e ) {
		$status = $e->status;
	}
	check( 'Provider that needs a key: no key -> 401 before any request', 401 === $status && array() === $GLOBALS['t_sent'] );

	http( 500, array( 'error' => array( 'message' => 'SECRET vendor text' ) ) );
	$msg = '';
	$status = 0;
	try {
		( new OpenAI( 'openai', 'sk-o' ) )->chat( req( 'gpt-4o' ) );
	} catch ( ProviderException $e ) {
		$msg    = $e->getMessage();
		$status = $e->status;
	}
	check( 'HTTP 500 becomes a ProviderException with status and vendor text (for the admin log only)', 500 === $status && false !== strpos( $msg, 'HTTP 500' ) && false !== strpos( $msg, 'SECRET vendor text' ) );
	$GLOBALS['t_sent'] = array();

	echo $fails ? "\n$fails FAILED\n" : "\nAll good\n";
	exit( $fails ? 1 : 0 );
}
