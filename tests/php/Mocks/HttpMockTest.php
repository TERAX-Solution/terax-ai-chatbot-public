<?php
/**
 * Tests that the HTTP mock itself behaves (TX-428), so the tests that rely on it can be trusted.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests\Mocks;

use TeraxAI\Tests\TestCase;

/**
 * Class HttpMockTest
 */
class HttpMockTest extends TestCase {

	public function test_a_mocked_host_answers_with_the_json_body_and_status() {
		HttpMock::respond( 'api.example.test', array( 'ok' => true ), 201 );

		$response = wp_remote_post( 'https://api.example.test/v1/chat', array( 'body' => wp_json_encode( array( 'q' => 'Hallo' ) ) ) );

		$this->assertSame( 201, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( array( 'ok' => true ), json_decode( wp_remote_retrieve_body( $response ), true ) );
	}

	public function test_requests_are_captured_with_their_decoded_body() {
		HttpMock::respond( 'api.example.test', array() );
		HttpMock::respond( 'other.example.test', array() );

		wp_remote_post( 'https://api.example.test/a', array( 'body' => wp_json_encode( array( 'q' => 'Hallo' ) ), 'headers' => array( 'X-Test' => '1' ) ) );
		wp_remote_get( 'https://other.example.test/b' );

		$this->assertCount( 2, HttpMock::requests() );
		$this->assertCount( 1, HttpMock::requests( 'api.example.test' ) );

		$last = HttpMock::last( 'api.example.test' );
		$this->assertSame( 'POST', $last['method'] );
		$this->assertSame( array( 'q' => 'Hallo' ), $last['json'] );
		$this->assertSame( '1', $last['headers']['X-Test'] );
		$this->assertSame( 'GET', HttpMock::last( 'other.example.test' )['method'] );
	}

	public function test_a_host_without_a_mock_fails_instead_of_using_the_network() {
		$response = wp_remote_get( 'https://no-mock.example.test/' );

		$this->assertWPError( $response );
		$this->assertStringContainsString( 'no mock for host no-mock.example.test', $response->get_error_message() );
	}

	public function test_a_callable_decides_per_request_and_a_string_body_is_sent_as_is() {
		HttpMock::respond(
			'api.example.test',
			static function ( $args ) {
				return 'echo:' . $args['body'];
			}
		);

		$response = wp_remote_post( 'https://api.example.test/', array( 'body' => 'ping' ) );

		$this->assertSame( 'echo:ping', wp_remote_retrieve_body( $response ) );
	}
}
