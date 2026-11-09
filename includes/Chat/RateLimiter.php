<?php
/**
 * Per-visitor rate limiting.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window limiter keyed by a salted IP hash (no raw IPs stored).
 */
class RateLimiter {

	/**
	 * Visitor IP. Proxy headers are only read when the admin opted in, because
	 * clients can fake them.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$headers = array(
			'cf'      => 'HTTP_CF_CONNECTING_IP',
			'xff'     => 'HTTP_X_FORWARDED_FOR',
			'real_ip' => 'HTTP_X_REAL_IP',
		);
		$mode    = (string) terax_ai_setting( 'privacy.trusted_proxy_header', '' );

		if ( isset( $headers[ $mode ] ) && ! empty( $_SERVER[ $headers[ $mode ] ] ) ) {
			$raw       = sanitize_text_field( wp_unslash( $_SERVER[ $headers[ $mode ] ] ) );
			$candidate = trim( explode( ',', $raw )[0] ); // X-Forwarded-For: first IP is the client.
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate;
			}
		}

		/**
		 * Filter the detected visitor IP, for hosting setups with their own proxy header.
		 *
		 * @param string $ip Client IP.
		 */
		return (string) apply_filters( 'terax_ai_client_ip', $ip );
	}

	/**
	 * Salted hash of the visitor IP.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		return hash( 'sha256', self::client_ip() . wp_salt( 'nonce' ) );
	}

	/**
	 * Register a hit; returns false when over the limit.
	 *
	 * @param string   $ip_hash IP hash.
	 * @param string   $bucket  Counter name, so the lead form has its own limit next to the chat ("chat" keeps the old key).
	 * @param int|null $limit   Hits per hour (null = the chat setting).
	 * @return bool
	 */
	public static function hit( $ip_hash, $bucket = 'chat', $limit = null ) {
		$limit = null === $limit ? (int) terax_ai_setting( 'privacy.rate_limit_per_hour', 30 ) : (int) $limit;
		if ( $limit <= 0 ) {
			return true;
		}
		$key   = 'terax_ai_rl_' . ( 'chat' === $bucket ? '' : sanitize_key( $bucket ) . '_' ) . substr( $ip_hash, 0, 32 ) . '_' . gmdate( 'YmdH' );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
