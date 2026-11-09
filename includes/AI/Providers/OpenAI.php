<?php
/**
 * OpenAI GPT provider.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI Chat Completions.
 */
class OpenAI extends OpenAICompatible {

	/**
	 * {@inheritDoc}
	 */
	protected function base_url() {
		return 'https://api.openai.com/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function requires_key() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function max_tokens_field() {
		return 'max_completion_tokens';
	}

	/**
	 * Reasoning models (o-series, gpt-5*) reject custom temperature.
	 *
	 * @param string $model Model.
	 * @return bool
	 */
	protected function supports_temperature( $model ) {
		return ! preg_match( '/^(o\d|gpt-5)/', $model );
	}

	/**
	 * Only chat-capable models.
	 *
	 * @return string[]
	 */
	public function list_models() {
		return array_values(
			array_filter(
				parent::list_models(),
				function ( $id ) {
					return (bool) preg_match( '/^(gpt-|o\d|chatgpt-)/', $id ) && ! preg_match( '/(audio|realtime|tts|transcribe|image|search)/', $id );
				}
			)
		);
	}
}
