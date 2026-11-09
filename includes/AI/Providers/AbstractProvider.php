<?php
/**
 * Base provider with shared HTTP handling.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

use TeraxAI\AI\Contracts\ProviderInterface;
use TeraxAI\AI\ProviderException;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractProvider
 *
 * Concrete providers only translate between the normalized ChatRequest /
 * ChatResponse and the vendor's JSON. Transport, timeouts and error
 * handling live here.
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Provider id from config/providers.php.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * API key (plaintext, in memory only).
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Extra options (e.g. base_url).
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param string $id      Provider id.
	 * @param string $api_key API key.
	 * @param array  $options Options.
	 */
	public function __construct( $id, $api_key, array $options = array() ) {
		$this->id      = $id;
		$this->api_key = (string) $api_key;
		$this->options = $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Perform a JSON HTTP request.
	 *
	 * @param string     $method  GET|POST.
	 * @param string     $url     URL.
	 * @param array      $headers Headers.
	 * @param array|null $body    JSON body.
	 * @return array Decoded JSON.
	 * @throws ProviderException On transport or HTTP error.
	 */
	protected function request( $method, $url, array $headers = array(), $body = null ) {
		if ( '' === $this->api_key && $this->requires_key() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
			throw new ProviderException( __( 'No API key configured for this provider.', 'terax-ai-assistant' ), 401 );
		}

		$args = array(
			'method'  => $method,
			'timeout' => (int) apply_filters( 'terax_ai_http_timeout', 45, $this->id ),
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		/**
		 * Filter outgoing provider HTTP args (proxies, custom headers, testing).
		 */
		$args     = apply_filters( 'terax_ai_provider_http_args', $args, $url, $this->id );
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
			throw new ProviderException( $response->get_error_message(), 0 );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
			throw new ProviderException( $this->extract_error( is_array( $data ) ? $data : array(), $status ), $status );
		}

		if ( ! is_array( $data ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
			throw new ProviderException( __( 'Invalid JSON response from AI provider.', 'terax-ai-assistant' ), $status );
		}

		return $data;
	}

	/**
	 * Whether this provider needs an API key.
	 *
	 * @return bool
	 */
	protected function requires_key() {
		return true;
	}

	/**
	 * Pull a human-readable error from a vendor error payload.
	 *
	 * @param array $data   Decoded body.
	 * @param int   $status HTTP status.
	 * @return string
	 */
	protected function extract_error( array $data, $status ) {
		$message = '';
		if ( isset( $data['error']['message'] ) ) {
			$message = $data['error']['message'];
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];
		} elseif ( isset( $data['message'] ) ) {
			$message = $data['message'];
		}

		/* translators: 1: HTTP status, 2: message */
		return sprintf( __( 'AI provider error (HTTP %1$d): %2$s', 'terax-ai-assistant' ), $status, $message ? $message : 'unknown' );
	}
}
