<?php
/**
 * Activation / upgrade / deactivation.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Core;

use TeraxAI\Database\Migrations\Schema;
use TeraxAI\Knowledge\Indexer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Install
 */
class Install {

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		Schema::up();
		update_option( 'terax_ai_db_version', defined( 'TERAX_AI_DB_VERSION' ) ? TERAX_AI_DB_VERSION : '1.0.0', false );
		Cron::schedule();
		// Build the knowledge index in the background.
		Indexer::queue_full_rebuild();
		// Send the admin to the setup wizard once (see Admin\Menu::maybe_redirect_to_setup()).
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! get_option( 'terax_ai_settings' ) ) {
			set_transient( 'terax_ai_redirect_to_setup', 1, 60 );
		}
	}

	/**
	 * Run migrations when the DB version changes after a plugin update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'terax_ai_db_version' ) !== TERAX_AI_DB_VERSION ) {
			Schema::up();
			update_option( 'terax_ai_db_version', TERAX_AI_DB_VERSION, false );
			Cron::schedule();
		}
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cron::unschedule();
	}
}
