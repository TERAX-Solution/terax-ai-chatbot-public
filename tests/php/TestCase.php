<?php
/**
 * Base class of the plugin's PHPUnit tests.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests;

use TeraxAI\Support\Settings;
use TeraxAI\Tests\Mocks\HttpMock;

/**
 * Class TestCase
 *
 * Every test starts with default settings and with the HTTP mock installed (no network).
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Fresh settings and a clean HTTP mock.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		HttpMock::reset();
		HttpMock::install();
		$this->reset_settings();
	}

	/**
	 * Remove mocks.
	 *
	 * @return void
	 */
	public function tear_down() {
		HttpMock::reset();
		$this->reset_settings();
		parent::tear_down();
	}

	/**
	 * Back to the default settings (stored option and the in-memory cache of the singleton).
	 *
	 * @return void
	 */
	protected function reset_settings() {
		delete_option( 'terax_ai_settings' );
		$cache = new \ReflectionProperty( Settings::class, 'cache' );
		$cache->setAccessible( true );
		$cache->setValue( Settings::get_instance(), null );
	}
}
