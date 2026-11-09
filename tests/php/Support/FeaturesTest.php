<?php
/**
 * Tests for the feature gates of the free plugin (TX-525).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests\Support;

use TeraxAI\Support\Features;
use TeraxAI\Tests\TestCase;

/**
 * Class FeaturesTest
 */
class FeaturesTest extends TestCase {

	public function test_without_a_license_the_free_features_are_on_and_the_paid_ones_off() {
		foreach ( array( 'byok', 'booking', 'conversation_log' ) as $feature ) {
			$this->assertTrue( Features::can( $feature ), $feature );
		}
		foreach ( array( 'managed_ai', 'remove_branding', 'custom_prompt', 'insights_report', 'sales_mode', 'callback_requests' ) as $feature ) {
			$this->assertFalse( Features::can( $feature ), $feature );
		}
		$this->assertSame( 'free', Features::plan() );
	}

	public function test_the_free_plugin_has_no_artificial_limits() {
		foreach ( array( 'messages_per_month', 'indexed_items', 'log_retention_days' ) as $name ) {
			$this->assertSame( -1, Features::limit( $name ), $name );
		}
	}

	public function test_an_add_on_can_switch_a_feature_on_and_set_a_limit() {
		add_filter(
			'terax_ai_can',
			static function ( $can, $feature ) {
				return 'sales_mode' === $feature ? true : $can;
			},
			10,
			2
		);
		add_filter(
			'terax_ai_limit',
			static function ( $value, $name ) {
				return 'messages_per_month' === $name ? 5 : $value;
			},
			10,
			2
		);

		$this->assertTrue( Features::can( 'sales_mode' ) );
		$this->assertFalse( Features::can( 'callback_requests' ), 'other gates stay as they were' );
		$this->assertSame( 5, Features::limit( 'messages_per_month' ) );
		$this->assertSame( -1, Features::limit( 'indexed_items' ) );
	}

	public function test_the_credit_link_is_off_until_the_site_owner_turns_it_on() {
		$this->assertFalse( terax_ai_setting( 'assistant.show_branding' ) );
		$this->assertNull( \TeraxAI\Assets\Widget::get_instance()->config()['branding'] );

		\TeraxAI\Support\Settings::get_instance()->update( array( 'assistant' => array( 'show_branding' => true ) ) );

		$this->assertSame( 'Powered by TERAX', \TeraxAI\Assets\Widget::get_instance()->config()['branding']['text'] );
	}

	public function test_without_an_add_on_managed_ai_cannot_be_chosen() {
		$this->expectException( \TeraxAI\AI\ProviderException::class );

		\TeraxAI\AI\ProviderRegistry::get_instance()->make( 'anthropic', 'managed' );
	}
}
