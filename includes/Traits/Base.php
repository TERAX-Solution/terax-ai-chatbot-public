<?php
/**
 * Singleton trait.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Traits;

/**
 * Trait Base
 *
 * Provides a singleton accessor.
 */
trait Base {

	/**
	 * The singleton instance.
	 *
	 * @var static|null
	 */
	private static $instance;

	/**
	 * Get (or create) the singleton instance.
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
