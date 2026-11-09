<?php
/**
 * Chat message value object.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable chat message. Roles: "user" | "assistant".
 * (The system prompt is passed separately on ChatRequest.)
 */
final class Message {

	/**
	 * Role.
	 *
	 * @var string
	 */
	public $role;

	/**
	 * Text content.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Constructor.
	 *
	 * @param string $role    Role.
	 * @param string $content Content.
	 */
	public function __construct( $role, $content ) {
		$this->role    = 'assistant' === $role ? 'assistant' : 'user';
		$this->content = (string) $content;
	}

	/**
	 * Array form.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'role'    => $this->role,
			'content' => $this->content,
		);
	}
}
