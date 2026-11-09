<?php
/**
 * Normalized chat request.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor-neutral request passed to every provider.
 */
final class ChatRequest {

	/**
	 * System prompt.
	 *
	 * @var string
	 */
	public $system = '';

	/**
	 * Conversation, oldest first; last item is the user's new message.
	 *
	 * @var Message[]
	 */
	public $messages = array();

	/**
	 * Model id.
	 *
	 * @var string
	 */
	public $model = '';

	/**
	 * Max output tokens.
	 *
	 * @var int
	 */
	public $max_tokens = 700;

	/**
	 * Sampling temperature (null = vendor default).
	 *
	 * @var float|null
	 */
	public $temperature = null;

	/**
	 * Constructor.
	 *
	 * @param string    $system      System prompt.
	 * @param Message[] $messages    Messages.
	 * @param string    $model       Model.
	 * @param int       $max_tokens  Max tokens.
	 * @param float     $temperature Temperature.
	 */
	public function __construct( $system, array $messages, $model, $max_tokens = 700, $temperature = null ) {
		$this->system      = (string) $system;
		$this->messages    = $messages;
		$this->model       = (string) $model;
		$this->max_tokens  = max( 50, (int) $max_tokens );
		$this->temperature = null === $temperature ? null : (float) $temperature;
	}
}
