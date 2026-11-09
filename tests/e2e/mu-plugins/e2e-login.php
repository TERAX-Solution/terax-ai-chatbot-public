<?php
/**
 * Plugin Name: TERAX E2E login (tests only)
 * Description: Lets the Playwright suite log in without a password, switch the mock LLM on and off, read back what the mock received, force the monthly quota, and save/restore two plugin options.
 *
 * Does nothing unless wp-config.php defines TERAX_E2E_TOKEN. Never ship or install this file on a real site.
 * The request must send the token in the X-Terax-E2E header, so it never appears in a URL or a log.
 *
 * Query switches (all need the header):
 *  - ?terax_e2e_login=1[&role=subscriber]  log in as the first user with that role (default administrator); returns a REST nonce;
 *  - ?terax_e2e_mock=on|fail|off           mock LLM answers / fails with HTTP 500 / is off;
 *  - ?terax_e2e_captured=get|clear         requests the mock LLM received;
 *  - ?terax_e2e_quota=on|off               on: the plan's monthly message limit is 0 (quota reached);
 *  - ?terax_e2e_option=NAME&op=get|set|delete  one of the whitelisted options (set: JSON body {"value": ...}).
 *
 * @package TeraxAI
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'TERAX_E2E_TOKEN' ) || '' === (string) TERAX_E2E_TOKEN ) {
	return;
}

// Quota test: the plan's monthly limit becomes 0, so the next chat is refused like a used-up plan.
add_filter(
	'terax_ai_limit',
	function ( $value, $name ) {
		return ( 'messages_per_month' === $name && get_transient( 'terax_e2e_quota' ) ) ? 0 : $value;
	},
	10,
	2
);

add_action(
	'init',
	function () {
		$get      = function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		$login    = ! empty( $_GET['terax_e2e_login'] );
		$mock     = $get( 'terax_e2e_mock' );
		$captured = $get( 'terax_e2e_captured' );
		$quota    = $get( 'terax_e2e_quota' );
		$option   = $get( 'terax_e2e_option' );
		if ( empty( $_SERVER['HTTP_X_TERAX_E2E'] ) || ( ! $login && '' === $mock . $captured . $quota . $option ) ) {
			return;
		}
		if ( ! hash_equals( (string) TERAX_E2E_TOKEN, (string) wp_unslash( $_SERVER['HTTP_X_TERAX_E2E'] ) ) ) {
			status_header( 403 );
			exit;
		}

		// The mock LLM (mock-llm.php) answers only while this flag is on. It expires by itself after an hour.
		if ( '' !== $mock ) {
			if ( in_array( $mock, array( 'on', 'fail' ), true ) ) {
				set_transient( 'terax_e2e_mock', 'on' === $mock ? 1 : 'fail', HOUR_IN_SECONDS );
			} else {
				delete_transient( 'terax_e2e_mock' );
			}
			wp_send_json( array( 'mock' => $mock ) );
		}

		if ( '' !== $captured ) {
			$requests = get_transient( 'terax_e2e_captured' );
			$requests = is_array( $requests ) ? array_values( $requests ) : array();
			if ( 'clear' === $captured ) {
				delete_transient( 'terax_e2e_captured' );
			}
			wp_send_json( array( 'requests' => $requests ) );
		}

		if ( '' !== $quota ) {
			if ( 'on' === $quota ) {
				set_transient( 'terax_e2e_quota', 1, HOUR_IN_SECONDS );
			} else {
				delete_transient( 'terax_e2e_quota' );
			}
			wp_send_json( array( 'quota' => $quota ) );
		}

		if ( '' !== $option ) {
			if ( ! in_array( $option, array( 'terax_ai_last_error', 'terax_ai_provider_health' ), true ) ) {
				status_header( 400 );
				exit;
			}
			$op     = $get( 'op' );
			$exists = false !== get_option( $option, false );
			$value  = get_option( $option, null );
			if ( 'set' === $op ) {
				$data = json_decode( (string) file_get_contents( 'php://input' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				update_option( $option, is_array( $data ) ? ( $data['value'] ?? null ) : null, false );
			} elseif ( 'delete' === $op ) {
				delete_option( $option );
			}
			wp_send_json(
				array(
					'exists' => $exists,
					'value'  => $value,
				)
			);
		}

		$role   = $get( 'role' );
		$admins = get_users(
			array(
				'role'    => '' !== $role ? $role : 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		if ( ! $admins ) {
			status_header( 500 );
			exit;
		}
		wp_set_current_user( $admins[0]->ID );
		// The nonce is tied to the session token in the login cookie, which only exists in the next request: use the new cookie now.
		add_action(
			'set_logged_in_cookie',
			function ( $cookie ) {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
			}
		);
		wp_set_auth_cookie( $admins[0]->ID );
		wp_send_json(
			array(
				'ok'    => true,
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
);
