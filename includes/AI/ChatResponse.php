<?php
/**
 * Normalized chat response.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor-neutral response returned by every provider.
 */
final class ChatResponse {

	/**
	 * Assistant text.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Model that actually answered.
	 *
	 * @var string
	 */
	public $model;

	/**
	 * Input tokens used.
	 *
	 * @var int
	 */
	public $input_tokens;

	/**
	 * Output tokens used.
	 *
	 * @var int
	 */
	public $output_tokens;

	/**
	 * Constructor.
	 *
	 * @param string $text          Text.
	 * @param string $model         Model.
	 * @param int    $input_tokens  Input tokens.
	 * @param int    $output_tokens Output tokens.
	 */
	public function __construct( $text, $model = '', $input_tokens = 0, $output_tokens = 0 ) {
		$this->text          = trim( (string) $text );
		$this->model         = (string) $model;
		$this->input_tokens  = (int) $input_tokens;
		$this->output_tokens = (int) $output_tokens;
	}
}
