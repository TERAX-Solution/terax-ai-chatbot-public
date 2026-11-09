<?php
/**
 * LLM provider endpoints.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\Message;
use TeraxAI\AI\ProviderException;
use TeraxAI\AI\ProviderRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProvidersController
 */
class ProvidersController {

	/**
	 * GET /providers
	 *
	 * @return \WP_REST_Response
	 */
	public function index() {
		return rest_ensure_response( ProviderRegistry::get_instance()->describe() );
	}

	/**
	 * POST /providers/models  { provider, mode, api_key? }
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function models( \WP_REST_Request $request ) {
		try {
			$provider = $this->make( $request );
			return rest_ensure_response( array( 'models' => $provider->list_models() ) );
		} catch ( ProviderException $e ) {
			return new \WP_Error( 'terax_ai_provider', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * POST /providers/test  { provider, mode, model, api_key? }
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test( \WP_REST_Request $request ) {
		try {
			$provider = $this->make( $request );
			$start    = microtime( true );
			$response = $provider->chat(
				new ChatRequest(
					'You are a connection test. Reply with exactly: OK',
					array( new Message( 'user', 'Ping' ) ),
					sanitize_text_field( (string) $request->get_param( 'model' ) ),
					20
				)
			);
			return rest_ensure_response(
				array(
					'ok'         => true,
					'reply'      => $response->text,
					'model'      => $response->model,
					'latency_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
				)
			);
		} catch ( ProviderException $e ) {
			return new \WP_Error( 'terax_ai_provider', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Build provider from request (allows testing a key before saving).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \TeraxAI\AI\Contracts\ProviderInterface
	 */
	private function make( \WP_REST_Request $request ) {
		return ProviderRegistry::get_instance()->make(
			sanitize_key( (string) $request->get_param( 'provider' ) ),
			sanitize_key( (string) $request->get_param( 'mode' ) ),
			trim( (string) $request->get_param( 'api_key' ) ),
			array( 'base_url' => esc_url_raw( (string) $request->get_param( 'base_url' ) ) )
		);
	}
}
