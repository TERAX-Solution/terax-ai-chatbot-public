<?php
/**
 * Admin endpoints for visitor requests (leads and call-back requests).
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Chat\RequestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class RequestsController
 */
class RequestsController {

	/**
	 * GET /requests?page=&status=
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ) {
		$repo   = RequestRepository::get_instance();
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$result = $repo->paginate( (int) $request->get_param( 'page' ), 20, $status );

		return rest_ensure_response(
			$result + array( 'new' => $repo->count_new() )
		);
	}

	/**
	 * POST /requests/{id}/status  { status: new|done }
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! RequestRepository::get_instance()->set_status( (int) $request->get_param( 'id' ), $status ) ) {
			return new \WP_Error( 'terax_ai_bad_status', __( 'Invalid status.', 'terax-ai-assistant' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * POST /requests/{id}/delete
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete( \WP_REST_Request $request ) {
		RequestRepository::get_instance()->delete( (int) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
