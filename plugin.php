<?php
/**
 * Main plugin class.
 *
 * @package TeraxAI
 */

use TeraxAI\Admin\Menu;
use TeraxAI\Admin\Notices;
use TeraxAI\AI\ProviderHealth;
use TeraxAI\Assets\Admin;
use TeraxAI\Assets\Widget;
use TeraxAI\Cli\Commands;
use TeraxAI\Core\Api;
use TeraxAI\Core\Cron;
use TeraxAI\Core\Install;
use TeraxAI\Knowledge\Indexer;
use TeraxAI\Privacy\PolicyContent;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class TeraxAIAssistant
 *
 * Wires all modules together. Each module is self-contained:
 *
 * - AI\        LLM providers (Claude, GPT, Gemini, Mistral, TERAX proxy …)
 * - Knowledge\ Indexing and retrieval of site content
 * - Chat\      Orchestrates a chat turn (retrieve → prompt → LLM → actions)
 * - Booking\   Appointment hand-off providers (Dr. Flex, Doctolib, Calendly …)
 * - Support\   Settings and Features (feature gates and limits; the Pro add-on changes them through filters)
 */
final class TeraxAIAssistant {

	use Base;

	/**
	 * Define plugin constants.
	 */
	public function __construct() {
		define( 'TERAX_AI_VERSION', '1.1.0' );
		define( 'TERAX_AI_DB_VERSION', '1.3.0' );
		define( 'TERAX_AI_PLUGIN_FILE', __DIR__ . '/terax-ai-assistant.php' );
		define( 'TERAX_AI_DIR', plugin_dir_path( __FILE__ ) );
		define( 'TERAX_AI_URL', plugin_dir_url( __FILE__ ) );
		define( 'TERAX_AI_ROUTE_PREFIX', 'terax-ai/v1' );
	}

	/**
	 * Start the plugin.
	 *
	 * @return void
	 */
	public function init() {
		Install::maybe_upgrade();

		if ( is_admin() ) {
			Menu::get_instance()->init();
			Notices::get_instance()->init();
			Admin::get_instance()->bootstrap();
			PolicyContent::get_instance()->init();
		}

		Widget::get_instance()->bootstrap();
		ProviderHealth::get_instance()->init();
		Api::get_instance()->init();
		Cron::get_instance()->init();
		Indexer::get_instance()->init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'terax-ai', Commands::class );
		}

		add_action( 'init', array( $this, 'i18n' ) );

		/**
		 * Fires after TERAX AI Assistant has booted. Add-ons hook here.
		 */
		do_action( 'terax_ai_loaded' );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'terax-ai-assistant', false, dirname( plugin_basename( TERAX_AI_PLUGIN_FILE ) ) . '/languages/' );
	}
}
