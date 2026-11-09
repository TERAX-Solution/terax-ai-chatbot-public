<?php
/**
 * Reusable HTTP mock for the PHPUnit tests (TX-428): no test ever reaches the network.
 *
 *   HttpMock::respond( 'api.anthropic.com', array( 'content' => array( ... ) ) );   // JSON answer for that host
 *   HttpMock::respond( 'api.openai.com', 'plain text', 500 );                       // any body, any status
 *   HttpMock::respond( 'api.mistral.ai', fn( $args, $url ) => array( ... ) );       // decide per request
 *   ... run the code under test ...
 *   $requests = HttpMock::requests( 'api.anthropic.com' );                          // what was sent
 *
 * A host without a mock fails with a WP_Error instead of going out to the internet. Install happens in
 * TestCase::set_up(), reset in tear_down().
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests\Mocks;

/**
 * Class HttpMock
 */
class HttpMock {

	/**
	 * Host => response definition.
	 *
	 * @var array
	 */
	private static $responses = array();

	/**
	 * Captured requests, oldest first.
	 *
	 * @var array
	 */
	private static $requests = array();

	/**
	 * Whether the filter is registered.
	 *
	 * @var bool
	 */
	private static $installed = false;

	/**
	 * Start intercepting wp_remote_* calls.
	 *
	 * @return void
	 */
	public static function install() {
		if ( ! self::$installed ) {
			add_filter( 'pre_http_request', array( self::class, 'intercept' ), 10, 3 );
			self::$installed = true;
		}
	}

	/**
	 * Stop intercepting and forget everything.
	 *
	 * @return void
	 */
	public static function reset() {
		remove_filter( 'pre_http_request', array( self::class, 'intercept' ), 10 );
		self::$installed = false;
		self::$responses = array();
		self::$requests  = array();
	}

	/**
	 * Set the answer for a host.
	 *
	 * @param string                $host    Host name, e.g. "api.anthropic.com".
	 * @param array|string|callable $body    Array (sent as JSON), string (sent as is) or a callable( $args, $url ) returning either.
	 * @param int                   $status  HTTP status.
	 * @param array                 $headers Response headers.
	 * @return void
	 */
	public static function respond( $host, $body = array(), $status = 200, array $headers = array() ) {
		self::install();
		self::$responses[ strtolower( $host ) ] = array(
			'body'    => $body,
			'status'  => (int) $status,
			'headers' => $headers,
		);
	}

	/**
	 * Requests that were sent, optionally for one host.
	 *
	 * Each entry: url, host, method, headers, body (raw string), json (decoded body or null).
	 *
	 * @param string|null $host Host name or null for all.
	 * @return array
	 */
	public static function requests( $host = null ) {
		if ( null === $host ) {
			return self::$requests;
		}
		return array_values(
			array_filter(
				self::$requests,
				static function ( $r ) use ( $host ) {
					return strtolower( $host ) === $r['host'];
				}
			)
		);
	}

	/**
	 * The last request, optionally for one host.
	 *
	 * @param string|null $host Host name or null.
	 * @return array|null
	 */
	public static function last( $host = null ) {
		$all = self::requests( $host );
		return $all ? end( $all ) : null;
	}

	/**
	 * The pre_http_request filter.
	 *
	 * @param false|array|\WP_Error $pre  Short-circuit value.
	 * @param array                 $args Request args.
	 * @param string                $url  URL.
	 * @return array|\WP_Error
	 */
	public static function intercept( $pre, $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$raw  = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';

		self::$requests[] = array(
			'url'     => $url,
			'host'    => $host,
			'method'  => isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET',
			'headers' => isset( $args['headers'] ) ? (array) $args['headers'] : array(),
			'body'    => $raw,
			'json'    => '' !== $raw ? json_decode( $raw, true ) : null,
		);

		if ( ! isset( self::$responses[ $host ] ) ) {
			return new \WP_Error( 'http_request_failed', 'HttpMock: no mock for host ' . $host );
		}

		$def  = self::$responses[ $host ];
		$body = is_callable( $def['body'] ) ? call_user_func( $def['body'], $args, $url ) : $def['body'];

		return array(
			'headers'  => array_merge( array( 'content-type' => 'application/json' ), $def['headers'] ),
			'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			'response' => array(
				'code'    => $def['status'],
				'message' => get_status_header_desc( $def['status'] ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
