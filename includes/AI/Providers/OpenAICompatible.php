<?php
/**
 * Any vendor that speaks the OpenAI Chat Completions format.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\ChatResponse;
use TeraxAI\AI\ProviderException;

defined( 'ABSPATH' ) || exit;

/**
 * Generic OpenAI-format provider (Groq, Together, OpenRouter, Ollama, LM Studio, Azure-style gateways …).
 * Subclasses only override base_url() and a few flags.
 */
class OpenAICompatible extends AbstractProvider {

	/**
	 * Base URL, e.g. https://api.groq.com/openai/v1.
	 *
	 * @return string
	 * @throws ProviderException If not configured.
	 */
	protected function base_url() {
		$url = untrailingslashit( (string) ( $this->options['base_url'] ?? '' ) );
		if ( '' === $url ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
			throw new ProviderException( __( 'Base URL is required for the OpenAI-compatible provider.', 'terax-ai-assistant' ) );
		}
		return $url;
	}

	/**
	 * Name of the max-tokens field (OpenAI's newer models use max_completion_tokens).
	 *
	 * @return string
	 */
	protected function max_tokens_field() {
		return 'max_tokens';
	}

	/**
	 * Whether to send temperature (some reasoning models reject it).
	 *
	 * @param string $model Model.
	 * @return bool
	 */
	protected function supports_temperature( $model ) {
		return true;
	}

	/**
	 * Local servers (Ollama) may not need a key.
	 *
	 * @return bool
	 */
	protected function requires_key() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function chat( ChatRequest $request ) {
		$messages = array();
		if ( '' !== $request->system ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $request->system,
			);
		}
		foreach ( $request->messages as $m ) {
			$messages[] = $m->to_array();
		}

		$body = array(
			'model'                   => $request->model,
			'messages'                => $messages,
			$this->max_tokens_field() => $request->max_tokens,
		);

		if ( null !== $request->temperature && $this->supports_temperature( $request->model ) ) {
			$body['temperature'] = $request->temperature;
		}

		$data = $this->request( 'POST', $this->base_url() . '/chat/completions', $this->headers(), $body );

		return new ChatResponse(
			$data['choices'][0]['message']['content'] ?? '',
			$data['model'] ?? $request->model,
			$data['usage']['prompt_tokens'] ?? 0,
			$data['usage']['completion_tokens'] ?? 0
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_models() {
		$data = $this->request( 'GET', $this->base_url() . '/models', $this->headers() );
		$ids  = wp_list_pluck( (array) ( $data['data'] ?? array() ), 'id' );
		sort( $ids );
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	protected function headers() {
		return '' === $this->api_key ? array() : array( 'Authorization' => 'Bearer ' . $this->api_key );
	}
}
