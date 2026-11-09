<?php
/**
 * Google Gemini provider.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\ChatResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Gemini via the Generative Language API.
 */
class Gemini extends AbstractProvider {

	const BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * {@inheritDoc}
	 */
	public function chat( ChatRequest $request ) {
		$contents = array();
		foreach ( $request->messages as $m ) {
			$contents[] = array(
				'role'  => 'assistant' === $m->role ? 'model' : 'user',
				'parts' => array( array( 'text' => $m->content ) ),
			);
		}

		$config = array( 'maxOutputTokens' => $request->max_tokens );
		if ( null !== $request->temperature ) {
			$config['temperature'] = $request->temperature;
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => $config,
		);
		if ( '' !== $request->system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $request->system ) ) );
		}

		$model = rawurlencode( preg_replace( '#^models/#', '', $request->model ) );
		$data  = $this->request( 'POST', self::BASE . "/models/{$model}:generateContent", $this->headers(), $body );

		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= $part['text'] ?? '';
		}

		return new ChatResponse(
			$text,
			$data['modelVersion'] ?? $request->model,
			$data['usageMetadata']['promptTokenCount'] ?? 0,
			$data['usageMetadata']['candidatesTokenCount'] ?? 0
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_models() {
		$data   = $this->request( 'GET', self::BASE . '/models?pageSize=200', $this->headers() );
		$models = array();
		foreach ( (array) ( $data['models'] ?? array() ) as $m ) {
			if ( in_array( 'generateContent', (array) ( $m['supportedGenerationMethods'] ?? array() ), true ) ) {
				$models[] = preg_replace( '#^models/#', '', $m['name'] );
			}
		}
		return $models;
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'x-goog-api-key' => $this->api_key );
	}
}
