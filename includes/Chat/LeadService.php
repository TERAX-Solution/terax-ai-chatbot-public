<?php
/**
 * Lead form submitted from the chat (sales mode).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class LeadService
 *
 * Validate → rate limit → store → e-mail the practice. Spam protection: honeypot field, a per-visitor limit
 * (5 per hour) and required consent. Nothing is stored without consent.
 */
class LeadService {

	use Base;

	/**
	 * Validate and clean the raw form values. Pure function, no WordPress state.
	 *
	 * @param array  $in   Raw input.
	 * @param string $type 'lead' (sales mode: phone OR e-mail) or 'callback' (phone is required, TX-459).
	 * @return array|string Clean row, or an error code:
	 *                      "spam" (honeypot filled, answer as if it worked), "consent", "name", "contact", "phone", "email".
	 */
	public static function validate( array $in, $type = 'lead' ) {
		// Bots fill every field; real visitors never see this one.
		if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) ) {
			return 'spam';
		}

		foreach ( array( 'name', 'phone', 'email', 'message', 'session_id', 'page_url' ) as $field ) {
			if ( isset( $in[ $field ] ) && ! is_scalar( $in[ $field ] ) ) {
				return 'name';
			}
		}

		if ( ! filter_var( $in['consent'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
			return 'consent';
		}

		$name  = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ) );
		$phone = trim( preg_replace( '/[^0-9+()\/\-. ]/', '', (string) ( $in['phone'] ?? '' ) ) );
		$email = trim( sanitize_email( (string) ( $in['email'] ?? '' ) ) );
		$raw_e = trim( (string) ( $in['email'] ?? '' ) );

		if ( '' === $name ) {
			return 'name';
		}
		if ( '' !== $raw_e && ( '' === $email || ! is_email( $email ) ) ) {
			return 'email';
		}
		// Phone OR e-mail is enough; a "phone" needs at least 6 digits.
		$has_phone = strlen( preg_replace( '/\D/', '', $phone ) ) >= 6;
		if ( 'callback' === $type ) {
			if ( ! $has_phone ) {
				return 'phone';
			}
		} elseif ( ! $has_phone && '' === $email ) {
			return 'contact';
		}

		$message = mb_substr( trim( sanitize_textarea_field( (string) ( $in['message'] ?? '' ) ) ), 0, 1000 );
		if ( 'callback' === $type ) {
			// No extra column: the preferred time travels in front of the reason.
			$times   = array( 'morning', 'afternoon' );
			$time    = in_array( ( $in['preferred_time'] ?? '' ), $times, true ) ? $in['preferred_time'] : 'any';
			$message = 'Preferred time: ' . $time . ( '' !== $message ? "\n" . $message : '' );
		}

		return array(
			'type'       => 'callback' === $type ? 'callback' : 'lead',
			'name'       => mb_substr( $name, 0, 120 ),
			'phone'      => $has_phone ? mb_substr( $phone, 0, 50 ) : '',
			'email'      => mb_substr( $email, 0, 190 ),
			'message'    => $message,
			'session_id' => mb_substr( preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) ( $in['session_id'] ?? '' ) ), 0, 64 ),
			'page_url'   => esc_url_raw( (string) ( $in['page_url'] ?? '' ) ),
		);
	}

	/**
	 * Handle a submitted form.
	 *
	 * @param array $input Raw input from the REST request.
	 * @return array|\WP_Error { ok: true }
	 */
	public function submit( array $input, $type = 'lead' ) {
		if ( 'callback' === $type ? ! CallbackMode::enabled() : ! SalesMode::enabled() ) {
			return new \WP_Error( 'terax_ai_lead_off', __( 'This form is not available.', 'terax-ai-assistant' ), array( 'status' => 403 ) );
		}

		$row = self::validate( $input, $type );

		if ( 'spam' === $row ) {
			return array( 'ok' => true ); // Looks like success to the bot, nothing is stored or sent.
		}

		$messages = array(
			'consent' => __( 'Please agree to the processing of your details.', 'terax-ai-assistant' ),
			'name'    => __( 'Please enter your name.', 'terax-ai-assistant' ),
			'email'   => __( 'Please enter a valid e-mail address.', 'terax-ai-assistant' ),
			'contact' => __( 'Please enter a phone number or an e-mail address.', 'terax-ai-assistant' ),
			'phone'   => __( 'Please enter a phone number so that we can call you back.', 'terax-ai-assistant' ),
		);
		if ( is_string( $row ) ) {
			return new \WP_Error( 'terax_ai_lead_invalid', $messages[ $row ] ?? $messages['name'], array( 'status' => 400 ) );
		}

		$ip_hash = RateLimiter::ip_hash();
		if ( ! RateLimiter::hit( $ip_hash, $row['type'], (int) apply_filters( 'terax_ai_lead_limit_per_hour', 5 ) ) ) {
			return new \WP_Error( 'terax_ai_rate_limited', __( 'Too many requests. Please try again later.', 'terax-ai-assistant' ), array( 'status' => 429 ) );
		}

		$row['ip_hash'] = $ip_hash;
		if ( ! RequestRepository::get_instance()->add( $row ) ) {
			return new \WP_Error( 'terax_ai_lead_failed', __( 'Your request could not be saved. Please try again.', 'terax-ai-assistant' ), array( 'status' => 500 ) );
		}

		$this->notify( $row );

		return array( 'ok' => true );
	}

	/**
	 * E-mail the new lead to the assistant e-mail (fallback: site admin).
	 *
	 * @param array $row Clean lead.
	 * @return void
	 */
	private function notify( array $row ) {
		$to = (string) terax_ai_setting( 'assistant.email', '' );
		if ( ! is_email( $to ) ) {
			$to = (string) get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}

		$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$callback = 'callback' === $row['type'];
		$subject  = $callback
			? sprintf(
				/* translators: %s: site name. */
				__( '[%s] Call-back request from the chat assistant', 'terax-ai-assistant' ),
				$site
			)
			: sprintf(
				/* translators: %s: site name. */
				__( '[%s] New request from the chat assistant', 'terax-ai-assistant' ),
				$site
			);
		$lines = array(
			$callback ? __( 'A visitor asked for a call back:', 'terax-ai-assistant' ) : __( 'A visitor left their details in the chat:', 'terax-ai-assistant' ),
			'',
			__( 'Name', 'terax-ai-assistant' ) . ': ' . $row['name'],
			__( 'Phone', 'terax-ai-assistant' ) . ': ' . ( '' !== $row['phone'] ? $row['phone'] : '-' ),
			__( 'E-mail', 'terax-ai-assistant' ) . ': ' . ( '' !== $row['email'] ? $row['email'] : '-' ),
			__( 'Message', 'terax-ai-assistant' ) . ': ' . ( '' !== $row['message'] ? $row['message'] : '-' ),
			__( 'Page', 'terax-ai-assistant' ) . ': ' . ( '' !== $row['page_url'] ? $row['page_url'] : '-' ),
			'',
			__( 'The visitor agreed to be contacted. The request is deleted automatically after the retention period set in the plugin.', 'terax-ai-assistant' ),
		);

		$headers = array();
		if ( '' !== $row['email'] ) {
			$headers[] = 'Reply-To: ' . $row['email'];
		}

		wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}
}
