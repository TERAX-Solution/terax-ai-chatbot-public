<?php
/**
 * Tracks consecutive AI provider failures and warns the site admin.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProviderHealth
 *
 * Visitors only see a generic fallback when the AI fails, so an expired key or an empty
 * vendor account could go unnoticed for weeks. After N failures in a row (default 5) the
 * admin gets a notice (see Admin\Notices) and at most one e-mail per 24 hours.
 * One successful answer resets the counter.
 */
class ProviderHealth {

	use Base;

	const OPTION    = 'terax_ai_provider_health';
	const MAIL_LOCK = 'terax_ai_failure_mail';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'terax_ai_settings_updated', array( $this, 'on_settings_updated' ), 10, 2 );
	}

	/**
	 * Failures in a row before the admin is warned.
	 *
	 * @return int
	 */
	public function threshold() {
		return max( 1, (int) apply_filters( 'terax_ai_failure_threshold', 5 ) );
	}

	/**
	 * Current failure streak.
	 *
	 * @return array{count:int,since:int,status:int,detail:string}
	 */
	public function state() {
		$state = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'count'  => 0,
				'since'  => 0,
				'status' => 0,
				'detail' => '',
			)
		);
	}

	/**
	 * Whether the streak reached the threshold.
	 *
	 * @return bool
	 */
	public function is_failing() {
		return $this->state()['count'] >= $this->threshold();
	}

	/**
	 * Record a failed provider call.
	 *
	 * @param ProviderException $e Exception.
	 * @return void
	 */
	public function failure( ProviderException $e ) {
		$state           = $this->state();
		$state['count']  = (int) $state['count'] + 1;
		$state['since']  = $state['since'] ? $state['since'] : time();
		$state['status'] = $e->status;
		$state['detail'] = mb_substr( $e->getMessage(), 0, 300 );
		update_option( self::OPTION, $state, false );

		if ( $state['count'] >= $this->threshold() ) {
			$this->maybe_send_email( $state );
		}
	}

	/**
	 * Record a successful answer: the streak is over.
	 *
	 * @return void
	 */
	public function success() {
		if ( false !== get_option( self::OPTION, false ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * Forget the streak when the AI connection settings changed (e.g. a new key).
	 *
	 * @param array $merged  New settings.
	 * @param array $current Previous settings.
	 * @return void
	 */
	public function on_settings_updated( $merged, $current ) {
		$new = (array) ( $merged['ai'] ?? array() );
		$old = (array) ( $current['ai'] ?? array() );
		unset( $new['notify_email'], $old['notify_email'] );

		if ( $new !== $old ) {
			$this->success();
		}
	}

	/**
	 * Human-friendly reason for an HTTP status (0 = network error or timeout).
	 *
	 * @param int $status HTTP status.
	 * @return string
	 */
	public static function reason( $status ) {
		$status = (int) $status;

		if ( 401 === $status || 403 === $status ) {
			return __( 'the API key is invalid, expired or missing.', 'terax-ai-assistant' );
		}
		if ( 402 === $status ) {
			return __( 'the AI account has no credit left.', 'terax-ai-assistant' );
		}
		if ( 429 === $status ) {
			return __( 'the AI provider’s quota or rate limit is used up.', 'terax-ai-assistant' );
		}
		if ( 404 === $status ) {
			return __( 'the selected AI model was not found.', 'terax-ai-assistant' );
		}
		if ( $status >= 500 ) {
			return __( 'the AI provider is currently unavailable.', 'terax-ai-assistant' );
		}
		if ( 0 === $status ) {
			return __( 'the AI provider could not be reached (network error or timeout).', 'terax-ai-assistant' );
		}

		return __( 'the AI provider rejected the request.', 'terax-ai-assistant' );
	}

	/**
	 * Send the warning e-mail, at most once per 24 hours and only if enabled.
	 *
	 * @param array $state Failure streak.
	 * @return void
	 */
	private function maybe_send_email( array $state ) {
		if ( ! terax_ai_setting( 'ai.notify_email', true ) || get_transient( self::MAIL_LOCK ) ) {
			return;
		}

		$to = apply_filters( 'terax_ai_failure_email_to', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		// Lock first so concurrent failing requests cannot send the mail twice.
		set_transient( self::MAIL_LOCK, 1, DAY_IN_SECONDS );

		$site    = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] AI Assistant: answers are failing', 'terax-ai-assistant' ),
			$site
		);
		$body = sprintf(
			/* translators: 1: number of failures, 2: reason, 3: message from the AI provider, 4: settings URL. */
			__( "Your AI Assistant could not answer %1\$d visitor questions in a row: %2\$s\n\nMessage from the AI provider: %3\$s\n\nPlease check the settings: %4\$s\n\nThis e-mail is sent at most once every 24 hours. You can turn it off under AI Assistant → Settings → Privacy.", 'terax-ai-assistant' ),
			$state['count'],
			self::reason( $state['status'] ),
			'' !== $state['detail'] ? $state['detail'] : '—',
			admin_url( 'admin.php?page=terax-ai-assistant#/settings/ai' )
		);

		wp_mail( $to, $subject, $body );
	}
}
