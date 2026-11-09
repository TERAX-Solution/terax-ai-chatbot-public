<?php
/**
 * Anthropic Claude provider.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\ChatResponse;
use TeraxAI\AI\ProviderException;

defined( 'ABSPATH' ) || exit;

/**
 * Claude via the Messages API.
 */
class Anthropic extends AbstractProvider {

	const BASE    = 'https://api.anthropic.com/v1';
	const VERSION = '2023-06-01';

	/**
	 * {@inheritDoc}
	 *
	 * @throws ProviderException On transport or HTTP error.
	 */
	public function chat( ChatRequest $request ) {
		$body = array(
			'model'      => $request->model,
			'max_tokens' => $request->max_tokens,
			'system'     => $request->system,
			'messages'   => array_map(
				function ( $m ) {
					return $m->to_array();
				},
				$request->messages
			),
		);

		if ( null !== $request->temperature ) {
			$body['temperature'] = $request->temperature;
		}

		try {
			$data = $this->request( 'POST', self::BASE . '/messages', $this->headers(), $body );
		} catch ( ProviderException $e ) {
			// Newer Claude models reject `temperature`; retry once without it.
			if ( 400 !== $e->status || ! isset( $body['temperature'] ) || false === stripos( $e->getMessage(), 'temperature' ) ) {
				throw $e;
			}
			unset( $body['temperature'] );
			$data = $this->request( 'POST', self::BASE . '/messages', $this->headers(), $body );
		}

		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}

		return new ChatResponse(
			$text,
			$data['model'] ?? $request->model,
			$data['usage']['input_tokens'] ?? 0,
			$data['usage']['output_tokens'] ?? 0
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_models() {
		$data = $this->request( 'GET', self::BASE . '/models?limit=100', $this->headers() );
		return array_values( array_filter( wp_list_pluck( (array) ( $data['data'] ?? array() ), 'id' ) ) );
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::VERSION,
		);
	}
}
