<?php
/**
 * Mistral AI provider (EU hosting – good default for GDPR-sensitive clients).
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Mistral uses the OpenAI chat format.
 */
class Mistral extends OpenAICompatible {

	/**
	 * {@inheritDoc}
	 */
	protected function base_url() {
		return 'https://api.mistral.ai/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function requires_key() {
		return true;
	}
}
