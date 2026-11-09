<?php
/**
 * Admin notices.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Admin;

use TeraxAI\AI\ProviderHealth;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notices
 */
class Notices {

	use Base;

	const META = 'terax_ai_dismissed_failure';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'provider_failing' ) );
		add_action( 'wp_ajax_terax_ai_dismiss_notice', array( $this, 'dismiss' ) );
	}

	/**
	 * Warn that the AI keeps failing. Dismissed per user for the current failure streak.
	 *
	 * @return void
	 */
	public function provider_failing() {
		$health = ProviderHealth::get_instance();
		if ( ! current_user_can( 'manage_options' ) || ! $health->is_failing() ) {
			return;
		}

		$state = $health->state();
		if ( (int) get_user_meta( get_current_user_id(), self::META, true ) === (int) $state['since'] ) {
			return;
		}

		$url = admin_url( 'admin.php?page=' . Menu::SLUG . '#/settings/ai' );
		?>
		<div class="notice notice-error is-dismissible terax-ai-failing">
			<p>
				<strong><?php esc_html_e( 'AI Assistant:', 'terax-ai-assistant' ); ?></strong>
				<?php
				/* translators: %s: reason, e.g. "the API key is invalid, expired or missing." */
				echo esc_html( sprintf( __( 'answers are failing – %s', 'terax-ai-assistant' ), ProviderHealth::reason( $state['status'] ) ) );
				?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Check settings →', 'terax-ai-assistant' ); ?></a>
			</p>
		</div>
		<script>
			jQuery( function ( $ ) {
				$( document ).on( 'click', '.terax-ai-failing .notice-dismiss', function () {
					$.post( ajaxurl, { action: 'terax_ai_dismiss_notice', _wpnonce: '<?php echo esc_js( wp_create_nonce( 'terax_ai_dismiss' ) ); ?>' } );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * AJAX: remember that this user dismissed the current failure streak.
	 *
	 * @return void
	 */
	public function dismiss() {
		check_ajax_referer( 'terax_ai_dismiss' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		// The streak id comes from the server, not from the request.
		update_user_meta( get_current_user_id(), self::META, (int) ProviderHealth::get_instance()->state()['since'] );
		wp_send_json_success();
	}
}
