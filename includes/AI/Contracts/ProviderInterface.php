<?php
/**
 * LLM provider contract.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI\Contracts;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\ChatResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ProviderInterface
 *
 * Every LLM vendor implements this. The rest of the plugin only talks to
 * this interface, so switching or adding a model never touches chat logic.
 */
interface ProviderInterface {

	/**
	 * Unique provider id (e.g. "anthropic").
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Send a chat request and return the assistant reply.
	 *
	 * @param ChatRequest $request Normalized request.
	 * @return ChatResponse
	 * @throws \TeraxAI\AI\ProviderException On any API / transport error.
	 */
	public function chat( ChatRequest $request );

	/**
	 * List model IDs available to the configured credentials.
	 *
	 * @return string[]
	 * @throws \TeraxAI\AI\ProviderException On API error.
	 */
	public function list_models();
}
