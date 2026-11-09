<?php
/**
 * Front-end chat widget.
 *
 * @package TeraxAI
 */

declare(strict_types=1);

namespace TeraxAI\Assets;

use TeraxAI\Booking\BookingRegistry;
use TeraxAI\Libs\Assets;
use TeraxAI\Libs\Utils\Shortcode;
use TeraxAI\Support\Features;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Widget
 *
 * Renders a floating launcher on every page (if enabled) and supports an
 * inline embed via [terax_ai_chat]. The UI lives in a Shadow DOM so theme CSS
 * cannot break it, and is built with Preact (~5 KB) to keep pages fast.
 */
class Widget {

	use Base;

	const HANDLE     = 'terax-ai-widget';
	const OBJ_NAME   = 'teraxAiWidget';
	const DEV_SCRIPT = 'src/frontend/main.jsx';

	/**
	 * Whether the inline shortcode was used on this page.
	 *
	 * @var bool
	 */
	private $inline = false;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function bootstrap() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_launcher' ) );

		Shortcode::add()->tag( 'terax_ai_chat' )->attrs( array( 'height' ) )->render( array( $this, 'shortcode' ) );
	}

	/**
	 * Whether the widget should load on this request.
	 *
	 * @return bool
	 */
	private function should_load() {
		$load = terax_ai_setting( 'assistant.enabled' ) && ! is_admin() && ! is_feed() && ! is_robots();
		/**
		 * Control where the widget loads (e.g. hide on specific pages).
		 *
		 * @param bool $load Load.
		 */
		return (bool) apply_filters( 'terax_ai_widget_enabled', $load );
	}

	/**
	 * Enqueue the bundle.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		Assets\enqueue_asset(
			TERAX_AI_DIR . 'assets/frontend/dist',
			self::DEV_SCRIPT,
			array(
				'handle'    => self::HANDLE,
				'in-footer' => true,
			)
		);

		// JSON (not wp_localize_script) so numbers, booleans and null keep their types.
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJ_NAME . ' = ' . wp_json_encode( $this->config() ) . ';', 'before' );
	}

	/**
	 * Public config (no secrets!).
	 *
	 * @return array
	 */
	public function config() {
		$a       = terax_ai_setting( 'assistant' );
		$p       = terax_ai_setting( 'privacy' );
		$plugin  = terax_ai_config( 'plugin' );
		return array(
			'endpoint'     => esc_url_raw( rest_url( TERAX_AI_ROUTE_PREFIX ) ),
			'name'         => $a['name'],
			'greeting'     => $a['greeting'],
			'suggestions'  => array_values( (array) $a['suggestions'] ),
			'color'        => $a['primary_color'],
			'textOnBrand'  => in_array( $a['text_on_brand'], array( 'light', 'dark' ), true ) ? $a['text_on_brand'] : 'auto',
			'position'     => 'left' === $a['position'] ? 'left' : 'right',
			'autoOpenSecs' => (int) $a['auto_open_secs'],
			'phone'        => $a['phone'],
			'consent'      => $p['consent_text'],
			'privacyUrl'   => $p['privacy_url'],
			'maxLength'    => (int) $p['max_message_length'],
			'booking'      => BookingRegistry::get_instance()->action(),
			'linkHosts'    => $this->link_hosts( $p ),
			'callback'     => \TeraxAI\Chat\CallbackMode::enabled(),
			'branding'     => ( empty( $a['show_branding'] ) || Features::can( 'remove_branding' ) ) ? null : array(
				'text' => $plugin['branding_text'],
				'url'  => $plugin['branding_url'],
			),
			'lang'         => substr( get_locale(), 0, 2 ),
		);
	}

	/**
	 * Hosts the widget may turn into clickable links in assistant replies (TX-500).
	 * The model could be steered by page content or a visitor into pasting a phishing link,
	 * so only the site itself, the booking tool and the privacy page are trusted by default.
	 *
	 * @param array $privacy Privacy settings.
	 * @return string[] Lower-case hosts; a host also covers its subdomains.
	 */
	private function link_hosts( array $privacy ) {
		$booking = BookingRegistry::get_instance()->action();
		$urls    = array( home_url(), $privacy['privacy_url'] ?? '', is_array( $booking ) ? ( $booking['url'] ?? '' ) : '' );
		$hosts   = array();
		foreach ( $urls as $url ) {
			$host = wp_parse_url( (string) $url, PHP_URL_HOST );
			if ( $host ) {
				$hosts[] = strtolower( $host );
			}
		}

		/**
		 * Filter the hosts allowed as links in replies, e.g. to add a shop or a partner site.
		 *
		 * @param string[] $hosts Allowed hosts.
		 */
		return array_values( array_unique( array_filter( array_map( 'strtolower', (array) apply_filters( 'terax_ai_allowed_link_hosts', $hosts ) ) ) ) );
	}

	/**
	 * Floating launcher mount point.
	 *
	 * @return void
	 */
	public function render_launcher() {
		if ( $this->should_load() && ! $this->inline ) {
			// The config travels in a data attribute: optimizer plugins that delay or move inline scripts
			// (LiteSpeed, WP Rocket, Autoptimize …) cannot break it, unlike window.teraxAiWidget.
			echo '<div id="terax-ai-widget" data-mode="floating" data-config="' . esc_attr( wp_json_encode( $this->config() ) ) . '"></div>';
		}
	}

	/**
	 * [terax_ai_chat height="560"] inline embed.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		if ( ! $this->should_load() ) {
			return '';
		}
		$this->inline = true;
		return '<div class="terax-ai-inline" data-mode="inline" data-config="' . esc_attr( wp_json_encode( $this->config() ) ) . '" style="height:' . ( absint( $atts['height'] ) ? absint( $atts['height'] ) : 560 ) . 'px"></div>';
	}
}
