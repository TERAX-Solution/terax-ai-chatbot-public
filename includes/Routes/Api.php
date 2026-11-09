<?php
/**
 * REST routes  →  /wp-json/terax-ai/v1/…
 *
 * @package TeraxAI
 */

namespace TeraxAI\Routes;

use TeraxAI\Libs\API\Route;

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Public routes (chat widget). No auth; protected by rate limiting.
|--------------------------------------------------------------------------
*/
Route::prefix(
	TERAX_AI_ROUTE_PREFIX,
	function ( Route $route ) {
		$route->post( '/chat', '\TeraxAI\Controllers\ChatController@send' );
		$route->post( '/booking-click', '\TeraxAI\Controllers\ChatController@booking_click' );
		$route->post( '/lead', '\TeraxAI\Controllers\ChatController@lead' );
		$route->post( '/callback', '\TeraxAI\Controllers\ChatController@callback' );
	}
);

/*
|--------------------------------------------------------------------------
| Admin routes. Require manage_options + wp_rest nonce.
|--------------------------------------------------------------------------
*/
Route::prefix(
	TERAX_AI_ROUTE_PREFIX,
	function ( Route $route ) {
		$route->get( '/dashboard', '\TeraxAI\Controllers\DashboardController@index' );

		$route->get( '/settings', '\TeraxAI\Controllers\SettingsController@get' );
		$route->post( '/settings', '\TeraxAI\Controllers\SettingsController@update' );

		$route->get( '/providers', '\TeraxAI\Controllers\ProvidersController@index' );
		$route->post( '/providers/models', '\TeraxAI\Controllers\ProvidersController@models' );
		$route->post( '/providers/test', '\TeraxAI\Controllers\ProvidersController@test' );

		$route->get( '/knowledge/status', '\TeraxAI\Controllers\KnowledgeController@status' );
		$route->post( '/knowledge/rebuild', '\TeraxAI\Controllers\KnowledgeController@rebuild' );
		$route->get( '/knowledge/sources', '\TeraxAI\Controllers\KnowledgeController@sources' );
		$route->post( '/knowledge/search', '\TeraxAI\Controllers\KnowledgeController@search' );

		$route->get( '/conversations', '\TeraxAI\Controllers\ConversationsController@index' );
		$route->get( '/conversations/export', '\TeraxAI\Controllers\ConversationsController@export' );
		$route->post( '/conversations/delete-all', '\TeraxAI\Controllers\ConversationsController@delete_all' );
		$route->get( '/conversations/{id}', '\TeraxAI\Controllers\ConversationsController@show' );
		$route->post( '/conversations/{id}/delete', '\TeraxAI\Controllers\ConversationsController@delete' );

		$route->get( '/insights', '\TeraxAI\Controllers\InsightsController@index' );
		$route->get( '/requests', '\TeraxAI\Controllers\RequestsController@index' );
		$route->post( '/requests/{id}/status', '\TeraxAI\Controllers\RequestsController@status' );
		$route->post( '/requests/{id}/delete', '\TeraxAI\Controllers\RequestsController@delete' );

		/**
		 * Add-ons register more admin routes here (the Pro add-on adds the license endpoints).
		 *
		 * @param Route $route Router.
		 */
		do_action( 'terax_ai_admin_routes', $route );
	}
)->auth(
	function () {
		return current_user_can( 'manage_options' );
	}
);
