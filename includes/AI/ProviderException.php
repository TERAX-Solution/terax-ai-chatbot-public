<?php
/**
 * Provider exception.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown for any failure talking to an LLM vendor.
 */
class ProviderException extends \Exception {

	/**
	 * HTTP status returned by the vendor (0 = transport error).
	 *
	 * @var int
	 */
	public $status = 0;

	/**
	 * Constructor.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 */
	public function __construct( $message, $status = 0 ) {
		parent::__construct( $message );
		$this->status = (int) $status;
	}
}
