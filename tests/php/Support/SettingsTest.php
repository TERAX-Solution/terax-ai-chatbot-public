<?php
/**
 * Tests for TeraxAI\Support\Settings (TX-428).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests\Support;

use TeraxAI\Support\Settings;
use TeraxAI\Tests\TestCase;

/**
 * Class SettingsTest
 */
class SettingsTest extends TestCase {

	/**
	 * Settings repository.
	 *
	 * @return Settings
	 */
	private function settings() {
		return Settings::get_instance();
	}

	/**
	 * Stored (not merged) settings, as they are in the database.
	 *
	 * @return array
	 */
	private function stored() {
		return (array) get_option( 'terax_ai_settings', array() );
	}

	public function test_defaults_are_returned_when_nothing_is_saved() {
		$this->assertFalse( get_option( 'terax_ai_settings' ) );
		$this->assertSame( 'Praxis-Assistent', $this->settings()->get( 'assistant.name' ) );
		$this->assertSame( 700, $this->settings()->get( 'ai.max_tokens' ) );
		$this->assertTrue( $this->settings()->get( 'privacy.mask_pii' ) );
		$this->assertSame( terax_ai_config( 'settings' ), $this->settings()->all() );
	}

	public function test_unknown_path_returns_the_fallback() {
		$this->assertNull( $this->settings()->get( 'does.not.exist' ) );
		$this->assertSame( 'x', $this->settings()->get( 'assistant.nope', 'x' ) );
		$this->assertSame( 'x', terax_ai_setting( 'assistant.nope.deeper', 'x' ) );
	}

	public function test_update_keeps_what_was_not_sent() {
		$this->settings()->update( array( 'assistant' => array( 'name' => 'Praxis Mustermann' ) ) );

		$this->assertSame( 'Praxis Mustermann', $this->settings()->get( 'assistant.name' ) );
		$this->assertSame( 700, $this->settings()->get( 'ai.max_tokens' ), 'other sections stay at their defaults' );
		$this->assertSame( 'informative', $this->settings()->get( 'assistant.mode' ), 'other keys of the same section stay' );
	}

	public function test_unknown_keys_are_dropped() {
		$this->settings()->update(
			array(
				'assistant' => array(
					'name'  => 'Dr. Muster',
					'bogus' => 'ignored',
				),
				'nonsense'  => array( 'a' => 1 ),
			)
		);

		$this->assertSame( 'Dr. Muster', $this->settings()->get( 'assistant.name' ) );
		$this->assertArrayNotHasKey( 'nonsense', $this->settings()->all() );
		$this->assertArrayNotHasKey( 'bogus', $this->settings()->all()['assistant'] );
		$this->assertArrayNotHasKey( 'nonsense', $this->stored() );
	}

	public function test_html_is_stripped_from_text_fields() {
		$this->settings()->update(
			array(
				'assistant' => array(
					'name'     => '<b>Dr. <script>alert(1)</script>Müller</b>',
					'greeting' => "Hallo <i>Welt</i>\nZweite Zeile <img src=x onerror=alert(1)>",
				),
			)
		);

		$name = $this->settings()->get( 'assistant.name' );
		$this->assertStringNotContainsString( '<', $name );
		$this->assertStringContainsString( 'Müller', $name );
		$this->assertStringNotContainsString( 'alert', $name );

		$greeting = $this->settings()->get( 'assistant.greeting' );
		$this->assertStringNotContainsString( '<', $greeting );
		$this->assertStringContainsString( "\n", $greeting, 'a textarea field keeps its line breaks' );
		$this->assertStringContainsString( 'Zweite Zeile', $greeting );
	}

	public function test_invalid_color_falls_back_to_the_default() {
		$this->settings()->update( array( 'assistant' => array( 'primary_color' => 'red; background:url(x)' ) ) );
		$this->assertSame( '#0f766e', $this->settings()->get( 'assistant.primary_color' ) );

		$this->settings()->update( array( 'assistant' => array( 'primary_color' => '#ABCDEF' ) ) );
		$this->assertSame( '#ABCDEF', $this->settings()->get( 'assistant.primary_color' ) );
	}

	public function test_numbers_and_booleans_are_cast() {
		$this->settings()->update(
			array(
				'ai'        => array(
					'max_tokens'  => '500abc',
					'temperature' => '0.7',
				),
				'privacy'   => array( 'retention_days' => '15' ),
				'assistant' => array( 'enabled' => 'false' ),
			)
		);

		$this->assertSame( 500, $this->settings()->get( 'ai.max_tokens' ) );
		$this->assertSame( 0.7, $this->settings()->get( 'ai.temperature' ) );
		$this->assertSame( 15, $this->settings()->get( 'privacy.retention_days' ) );
		$this->assertFalse( $this->settings()->get( 'assistant.enabled' ) );
	}

	public function test_lists_are_replaced_and_cleaned() {
		$this->settings()->update( array( 'assistant' => array( 'suggestions' => array( 'Öffnungszeiten?', '', '<b>Preise</b>', array( 'nested' ) ) ) ) );

		$this->assertSame( array( 'Öffnungszeiten?', 'Preise' ), $this->settings()->get( 'assistant.suggestions' ) );
	}

	public function test_api_keys_are_stored_encrypted() {
		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => 'sk-ant-secret-1234' ) ) ) );

		$raw = $this->stored()['ai']['api_keys']['anthropic'];
		$this->assertNotSame( 'sk-ant-secret-1234', $raw, 'the stored value is not the plain key' );
		$this->assertStringNotContainsString( 'sk-ant-secret', $raw );
		$this->assertSame( 'sk-ant-secret-1234', $this->settings()->api_key( 'anthropic' ), 'it decrypts to the original' );
		$this->assertSame( '', $this->settings()->key_problem( 'anthropic' ) );
	}

	public function test_for_admin_only_returns_a_masked_hint() {
		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => 'sk-ant-secret-1234' ) ) ) );

		$view = $this->settings()->for_admin();
		$this->assertSame( '••••1234', $view['ai']['api_keys']['anthropic'] );
		$this->assertStringNotContainsString( 'sk-ant', wp_json_encode( $view ) );
	}

	public function test_update_returns_the_admin_view() {
		$result = $this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'openai' => 'sk-openai-9876' ) ) ) );

		$this->assertSame( '••••9876', $result['ai']['api_keys']['openai'] );
	}

	public function test_an_empty_key_keeps_the_stored_key_and_delete_removes_it() {
		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => 'sk-ant-secret-1234' ) ) ) );

		// Saving the form without touching the key field sends an empty value: the key must survive.
		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => '' ) ) ) );
		$this->assertSame( 'sk-ant-secret-1234', $this->settings()->api_key( 'anthropic' ) );

		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => '__delete__' ) ) ) );
		$this->assertSame( '', $this->settings()->api_key( 'anthropic' ) );
		$this->assertArrayNotHasKey( 'anthropic', $this->settings()->for_admin()['ai']['api_keys'] );
		$this->assertArrayNotHasKey( 'anthropic', $this->stored()['ai']['api_keys'] );
	}

	public function test_updating_other_settings_does_not_touch_the_keys() {
		$this->settings()->update( array( 'ai' => array( 'api_keys' => array( 'anthropic' => 'sk-ant-secret-1234' ) ) ) );
		$this->settings()->update( array( 'ai' => array( 'model' => 'claude-sonnet-4-5' ) ) );

		$this->assertSame( 'claude-sonnet-4-5', $this->settings()->get( 'ai.model' ) );
		$this->assertSame( 'sk-ant-secret-1234', $this->settings()->api_key( 'anthropic' ) );
	}

	public function test_settings_updated_action_gets_the_new_and_the_previous_settings() {
		$seen = array();
		add_action(
			'terax_ai_settings_updated',
			static function ( $new, $old ) use ( &$seen ) {
				$seen = array( $new['assistant']['name'], $old['assistant']['name'] );
			},
			10,
			2
		);

		$this->settings()->update( array( 'assistant' => array( 'name' => 'Neuer Name' ) ) );

		$this->assertSame( array( 'Neuer Name', 'Praxis-Assistent' ), $seen );
	}

	public function test_saved_settings_survive_a_new_request() {
		$this->settings()->update( array( 'assistant' => array( 'name' => 'Dauerhaft' ) ) );

		// A new request starts without the in-memory cache.
		$cache = new \ReflectionProperty( Settings::class, 'cache' );
		$cache->setAccessible( true );
		$cache->setValue( $this->settings(), null );

		$this->assertSame( 'Dauerhaft', terax_ai_setting( 'assistant.name' ) );
	}
}
