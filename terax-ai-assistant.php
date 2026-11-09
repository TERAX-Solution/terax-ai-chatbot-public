<?php
/**
 * Plugin Name: TERAX AI Assistant
 * Description: AI assistant that answers visitor questions from your site content and hands off to appointment booking.
 * Author: TERAX
 * Author URI: https://terax.dev
 * Version: 1.1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: terax-ai-assistant
 * Domain Path: /languages
 *
 * @package TeraxAI
 */

use TeraxAI\Core\Install;

defined( 'ABSPATH' ) || exit;

// vendor/ is not committed; fall back to a built-in autoloader so a fresh clone activates.
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
} else {
	require_once plugin_dir_path( __FILE__ ) . 'autoload-fallback.php';
}
// The helper files are loaded here and not through composer.json "files": Composer's autoloader is also loaded by command-line
// tools (PHPUnit, PHPCS) outside WordPress, where their `defined( 'ABSPATH' ) || exit;` guard would silently end the tool.
require_once plugin_dir_path( __FILE__ ) . 'libs/assets.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require_once plugin_dir_path( __FILE__ ) . 'plugin.php';

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function terax_ai_assistant_init() {
	TeraxAIAssistant::get_instance()->init();
}
add_action( 'plugins_loaded', 'terax_ai_assistant_init' );

register_activation_hook( __FILE__, array( Install::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Install::class, 'deactivate' ) );
