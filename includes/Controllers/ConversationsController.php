<?php
/**
 * Conversation log endpoints.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Controllers;

use TeraxAI\Chat\ConversationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConversationsController
 */
class ConversationsController {

	/**
	 * GET /conversations?page=&per_page=&search=
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = min( 100, max( 5, 0 !== $per_page ? $per_page : 20 ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$result   = ConversationRepository::get_instance()->paginate( $page, $per_page, $search );
		return rest_ensure_response(
			$result + array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * GET /conversations/export?from=YYYY-MM-DD&to=YYYY-MM-DD
	 *
	 * Streams a CSV download: UTF-8 with BOM. Separator is ";" (German Excel) or "," (?delimiter=comma, US Excel and Google Sheets).
	 * The dates are days in the site's time zone (both included). A browser download cannot send the
	 * X-WP-Nonce header, so the admin UI passes the nonce as the "_wpnonce" query parameter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return void
	 */
	public function export( \WP_REST_Request $request ) {
		$from = $this->day_start_utc( $request->get_param( 'from' ), 0 );
		$to   = $this->day_start_utc( $request->get_param( 'to' ), 1 );

		// Excel splits columns on the list separator of the computer (";" in Germany, "," in the US), not on the file.
		$sep = 'comma' === $request->get_param( 'delimiter' ) ? ',' : ';';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="terax-conversations-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->csv_row( $out, array( 'date', 'conversation_id', 'page_url', 'role', 'message', 'sources' ), $sep );

		$repo     = ConversationRepository::get_instance();
		$after_id = 0;
		do {
			$rows = $repo->export_batch( $from, $to, $after_id );
			foreach ( $rows as $row ) {
				$after_id = (int) $row['id'];
				$urls     = array_filter( array_map( 'strval', wp_list_pluck( (array) $row['sources'], 'url' ) ) );
				$this->csv_row( $out, array( get_date_from_gmt( $row['created_at'], 'Y-m-d H:i:s' ), $row['conversation_id'], $row['page_url'], $row['role'], $row['content'], implode( ' ', $urls ) ), $sep );
			}
			flush();
		} while ( count( $rows ) >= 500 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * UTC datetime of the start of a site-time day (+ $plus_days), or null when the date is missing or invalid.
	 *
	 * @param mixed $date      YYYY-MM-DD.
	 * @param int   $plus_days Days to add.
	 * @return string|null
	 */
	private function day_start_utc( $date, $plus_days ) {
		$date = (string) $date;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}
		try {
			$day = new \DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
		return $day->modify( '+' . (int) $plus_days . ' day' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Write one CSV row. Cells that start with = + - @ or a control character get a leading quote, so
	 * Excel never runs a visitor's message as a formula (CSV injection).
	 *
	 * @param resource $out  Output stream.
	 * @param array    $cols Cells.
	 * @param string   $sep  Column separator, ";" or ",".
	 * @return void
	 */
	private function csv_row( $out, array $cols, $sep = ';' ) {
		$cols = array_map(
			function ( $cell ) {
				$cell = (string) $cell;
				return '' !== $cell && false !== strpos( "=+-@\t\r", $cell[0] ) ? "'" . $cell : $cell;
			},
			$cols
		);
		fputcsv( $out, $cols, $sep, '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * GET /conversations/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$conv = ConversationRepository::get_instance()->find( (int) $request->get_param( 'id' ) );
		if ( ! $conv ) {
			return new \WP_Error( 'not_found', __( 'Conversation not found.', 'terax-ai-assistant' ), array( 'status' => 404 ) );
		}
		unset( $conv['ip_hash'] );
		return rest_ensure_response( $conv );
	}

	/**
	 * POST /conversations/delete-all (GDPR: conversations are anonymous, so WP's e-mail based erase tool cannot find them).
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_all() {
		return rest_ensure_response( array( 'deleted' => ConversationRepository::get_instance()->delete_all() ) );
	}

	/**
	 * POST /conversations/{id}/delete
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete( \WP_REST_Request $request ) {
		ConversationRepository::get_instance()->delete( (int) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
