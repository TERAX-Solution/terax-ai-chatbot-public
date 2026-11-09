<?php
/**
 * Orchestrates one chat turn.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Chat;

use TeraxAI\AI\ChatRequest;
use TeraxAI\AI\Message;
use TeraxAI\AI\ProviderException;
use TeraxAI\AI\ProviderHealth;
use TeraxAI\AI\ProviderRegistry;
use TeraxAI\Booking\BookingRegistry;
use TeraxAI\Knowledge\KeywordRetriever;
use TeraxAI\Knowledge\RetrieverInterface;
use TeraxAI\Support\Features;
use TeraxAI\Privacy\PiiMasker;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatService
 *
 * Pipeline: validate → rate-limit / quota → retrieve context → build prompt
 * → call provider → detect booking intent → log → respond.
 */
class ChatService {

	use Base;

	const MAX_HISTORY = 10;

	/**
	 * Words a page must share with the answer to be listed as a source.
	 */
	const SOURCE_MIN_OVERLAP = 3;

	/**
	 * Words that signal booking intent (fallback if the model forgets the token).
	 *
	 * @var string
	 */
	const BOOKING_PATTERN = '/\b(termin\w*|appointment\w*|buchen|book(ing)?|vereinbar\w*|reserv\w*|randevu|cita)\b/iu';

	/**
	 * Handle a chat message.
	 *
	 * @param array $input { message, history[], session_id, page_url }.
	 * @return array|\WP_Error
	 */
	public function handle( array $input ) {
		if ( ! terax_ai_setting( 'assistant.enabled' ) ) {
			return new \WP_Error( 'terax_ai_disabled', __( 'The assistant is currently disabled.', 'terax-ai-assistant' ), array( 'status' => 503 ) );
		}

		// Reject non-string input (e.g. message[]=x) before it is cast to "Array" and sent to a paid API.
		foreach ( array( 'message', 'session_id', 'page_url' ) as $field ) {
			if ( isset( $input[ $field ] ) && ! is_scalar( $input[ $field ] ) ) {
				return new \WP_Error( 'terax_ai_invalid', __( 'Message and session are required.', 'terax-ai-assistant' ), array( 'status' => 400 ) );
			}
		}

		$max_len = max( 100, (int) terax_ai_setting( 'privacy.max_message_length', 1000 ) );
		$message = trim( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ) );
		$session = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) ( $input['session_id'] ?? '' ) );
		$page    = esc_url_raw( (string) ( $input['page_url'] ?? '' ) );

		if ( '' === $message || '' === $session ) {
			return new \WP_Error( 'terax_ai_invalid', __( 'Message and session are required.', 'terax-ai-assistant' ), array( 'status' => 400 ) );
		}
		$message = mb_substr( $message, 0, $max_len );
		$session = substr( $session, 0, 64 );

		$ip_hash = RateLimiter::ip_hash();
		if ( ! RateLimiter::hit( $ip_hash ) ) {
			return new \WP_Error( 'terax_ai_rate_limited', __( 'Too many messages. Please try again later.', 'terax-ai-assistant' ), array( 'status' => 429 ) );
		}

		$quota = Features::limit( 'messages_per_month' );
		if ( $quota >= 0 && UsageMeter::current()['messages'] >= $quota ) {
			return new \WP_Error( 'terax_ai_quota', $this->fallback_text(), array( 'status' => 429 ) );
		}

		$history = $this->sanitize_history( (array) ( $input['history'] ?? array() ), $max_len );
		// Sales mode: pause the nudge when a button was just offered (reported by the widget) or the last replies closed with a question.
		$pause    = SalesMode::enabled() && ( filter_var( $input['recent_cta'] ?? false, FILTER_VALIDATE_BOOLEAN ) || self::recent_nudge( $history ) );
		$booking  = BookingRegistry::get_instance();
		$can_book = null !== $booking->active();

		// Retrieval uses the new question plus the previous user turn (for follow-ups like "and the price?").
		$query      = $message;
		$last_users = array_values(
			array_filter(
				$history,
				function ( $m ) {
					return 'user' === $m->role;
				}
			)
		);
		if ( $last_users ) {
			$query .= ' ' . end( $last_users )->content;
		}
		$chunks = $this->retriever()->retrieve( $query, max( 1, (int) terax_ai_setting( 'knowledge.max_chunks', 5 ) ) );

		$messages   = $history;
		$messages[] = new Message( 'user', $message );

		$request = new ChatRequest(
			PromptBuilder::system( $chunks, $can_book, array( 'recent_nudge' => $pause ) ),
			$messages,
			(string) terax_ai_setting( 'ai.model' ),
			(int) terax_ai_setting( 'ai.max_tokens', 700 ),
			terax_ai_setting( 'ai.temperature', 0.3 )
		);

		/**
		 * Filter the request right before it is sent to the provider.
		 */
		$request = apply_filters( 'terax_ai_chat_request', $request, $input );

		try {
			$provider = ProviderRegistry::get_instance()->make();
			$response = $provider->chat( $request );
		} catch ( ProviderException $e ) {
			// Log for admins, never leak vendor errors to visitors.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TERAX AI] ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			update_option(
				'terax_ai_last_error',
				array(
					'message' => $e->getMessage(),
					'time'    => time(),
				),
				false
			);
			ProviderHealth::get_instance()->failure( $e );
			return new \WP_Error( 'terax_ai_provider', $this->fallback_text(), array( 'status' => 502 ) );
		}

		ProviderHealth::get_instance()->success();

		// Booking intent: explicit token from the model, or keywords in the visitor message.
		$text       = $response->text;
		$intent     = preg_match( \TeraxAI\Support\Industry::current()['booking_pattern'], $message );
		$wants_book = false !== strpos( $text, PromptBuilder::BOOKING_TOKEN ) || $intent;
		// Sales mode: the lead form is the action when the model asks for it, or when the visitor wants to sign up and there is no booking button.
		$wants_lead = SalesMode::enabled() && ( false !== strpos( $text, PromptBuilder::LEAD_TOKEN ) || ( ! $can_book && $intent ) );
		// Do not nag: while the last two replies already closed with a next step, a button only follows an explicit request.
		if ( $pause && ! $intent ) {
			$wants_lead = false;
			$wants_book = false;
		}
		// Call-back form (TX-459): when the model cannot answer, or the visitor wants to book but there is no booking button and no sales form.
		$wants_callback = CallbackMode::enabled() && ! $wants_lead && ( false !== strpos( $text, PromptBuilder::CALLBACK_TOKEN ) || ( ! $can_book && $intent ) );
		$no_sources     = false !== strpos( $text, PromptBuilder::NO_SOURCES_TOKEN );
		// TX-460: the model could not answer from the context (it declined, or it offers a call back instead). Feeds the Insights page.
		$unanswered = $no_sources || false !== strpos( $text, PromptBuilder::CALLBACK_TOKEN );
		$text       = trim( str_replace( array( PromptBuilder::BOOKING_TOKEN, PromptBuilder::NO_SOURCES_TOKEN, PromptBuilder::LEAD_TOKEN, PromptBuilder::CALLBACK_TOKEN ), '', $text ) );
		$text       = $this->normalize_links( $text, $chunks );
		$actions    = array();
		if ( $wants_book && $can_book ) {
			$actions[] = $booking->action( array( 'message' => $message ) );
		}
		if ( $wants_lead ) {
			$actions[] = array(
				'type'  => 'lead',
				'label' => SalesMode::cta_label(),
			);
		}
		if ( $wants_callback ) {
			$actions[] = array( 'type' => 'callback' ); // The widget has the button text in the visitor's language.
		}

		// A booking reply doesn't need "sources" noise under it.
		// The model flags declines and "I don't know" answers; those never get sources.
		$sources = ( $actions || $no_sources ) ? array() : $this->sources( $chunks, $text );

		UsageMeter::record( $response->input_tokens, $response->output_tokens );
		$this->log( $session, $page, $ip_hash, $message, $text, $sources, $response, $provider->id(), ! $unanswered );

		$result = array(
			'reply'   => $text,
			'sources' => $sources,
			'actions' => array_values( array_filter( $actions ) ),
		);

		/**
		 * Filter the response sent to the widget.
		 */
		return apply_filters( 'terax_ai_chat_response', $result, $input, $response );
	}

	/**
	 * Whether one of the last two assistant replies already closed with a question or next step
	 * (its last line ends with "?"). Used by the sales mode so the assistant does not nudge in every reply.
	 *
	 * @param Message[] $history Sanitized history, oldest first.
	 * @return bool
	 */
	public static function recent_nudge( array $history ) {
		$replies = array();
		foreach ( $history as $m ) {
			if ( 'assistant' === $m->role ) {
				$replies[] = $m->content;
			}
		}
		foreach ( array_slice( $replies, -2 ) as $reply ) {
			if ( preg_match( '/\?\s*$/u', trim( (string) $reply ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Active retriever (swappable).
	 *
	 * @return RetrieverInterface
	 */
	private function retriever() {
		$retriever = apply_filters( 'terax_ai_retriever', null );
		return $retriever instanceof RetrieverInterface ? $retriever : new KeywordRetriever();
	}

	/**
	 * Validate client-provided history.
	 *
	 * @param array $history Raw.
	 * @param int   $max_len Max length per message.
	 * @return Message[]
	 */
	private function sanitize_history( array $history, $max_len ) {
		$out = array();
		foreach ( array_slice( $history, -self::MAX_HISTORY ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['content'] ) || ! is_scalar( $item['content'] ) ) {
				continue;
			}
			$role  = 'assistant' === ( $item['role'] ?? '' ) ? 'assistant' : 'user';
			$out[] = new Message( $role, mb_substr( sanitize_textarea_field( (string) $item['content'] ), 0, $max_len * 2 ) );
		}
		// Providers require the conversation to start with a user turn.
		while ( $out && 'user' !== $out[0]->role ) {
			array_shift( $out );
		}
		return $out;
	}

	/**
	 * Source links to show under the answer (only real pages, max 3).
	 *
	 * @param array  $chunks Chunks.
	 * @param string $text   Answer.
	 * @return array
	 */
	private function sources( array $chunks, $text ) {
		$out    = array();
		$answer = KeywordRetriever::tokens( $text );
		foreach ( $chunks as $c ) {
			if ( (int) $c['post_id'] <= 0 || isset( $out[ $c['url'] ] ) ) {
				continue;
			}
			// Retrieval returns the best matches, not the pages the answer used: a refusal or an
			// unrelated page would otherwise get "sources". Require real word overlap with the answer.
			$shared = array_intersect( $answer, KeywordRetriever::tokens( $c['title'] . ' ' . $c['content'] ) );
			if ( count( $shared ) < self::SOURCE_MIN_OVERLAP ) {
				continue;
			}
			$out[ $c['url'] ] = array(
				'title' => $c['title'],
				'url'   => $c['url'],
			);
		}
		return array_slice( array_values( $out ), 0, 3 );
	}

	/**
	 * Store the exchange.
	 *
	 * @param string                   $session  Session.
	 * @param string                   $page     Page URL.
	 * @param string                   $ip_hash  IP hash.
	 * @param string                   $question Question.
	 * @param string                   $answer   Answer.
	 * @param array                    $sources  Sources.
	 * @param \TeraxAI\AI\ChatResponse $response Response.
	 * @param string                   $provider Provider id.
	 * @return void
	 */
	private function log( $session, $page, $ip_hash, $question, $answer, $sources, $response, $provider, $answered = true ) {
		$repo  = ConversationRepository::get_instance();
		$store = terax_ai_setting( 'privacy.log_conversations' ) && Features::can( 'conversation_log' );
		$id    = $repo->find_or_create( $session, $page, $store ? $ip_hash : '' );

		if ( ! $store ) {
			$repo->bump( $id );
			return;
		}

		// Only the stored copy is masked; the AI already received the original question.
		$mask = (bool) terax_ai_setting( 'privacy.mask_pii' );

		$repo->add_message( $id, 'user', $mask ? PiiMasker::mask( $question ) : $question );
		$repo->add_message(
			$id,
			'assistant',
			$mask ? PiiMasker::mask( $answer ) : $answer,
			array(
				'sources'       => $sources,
				'answered'      => $answered,
				'input_tokens'  => $response->input_tokens,
				'output_tokens' => $response->output_tokens,
				'provider'      => $provider,
				'model'         => $response->model,
			)
		);
	}

	/**
	 * Give links to known pages their real title as label.
	 *
	 * The model sometimes labels a source URL with unrelated words (e.g. "our services" on a blog
	 * post). For URLs that are one of the retrieved pages the label is replaced by that page's title.
	 *
	 * @param string $text   Answer.
	 * @param array  $chunks Retrieved chunks.
	 * @return string
	 */
	private function normalize_links( $text, array $chunks ) {
		$titles = array();
		foreach ( $chunks as $c ) {
			if ( (int) $c['post_id'] > 0 && '' !== (string) $c['title'] ) {
				$titles[ untrailingslashit( $c['url'] ) ] = str_replace( array( '[', ']' ), '', $c['title'] );
			}
		}
		if ( ! $titles ) {
			return $text;
		}

		// A link that continues the sentence ("please [contact us](url) directly") keeps its own label:
		// swapping in a page title there breaks the grammar. Only stand-alone links get the real title.
		$result = preg_replace_callback(
			'/\[[^\]]*\]\((https?:\/\/[^)\s]+)\)(?!\s+\p{Ll})/u',
			function ( $m ) use ( $titles ) {
				$key = untrailingslashit( $m[1] );
				return isset( $titles[ $key ] ) ? '[' . $titles[ $key ] . '](' . $m[1] . ')' : $m[0];
			},
			$text
		);
		return null === $result ? $text : $result;
	}


	/**
	 * Polite fallback when the AI is unavailable.
	 *
	 * @return string
	 */
	private function fallback_text() {
		$phone = (string) terax_ai_setting( 'assistant.phone', '' );
		$text  = __( 'Sorry, I can\'t answer right now. Please contact us directly.', 'terax-ai-assistant' );
		return $phone ? $text . ' ☎ ' . $phone : $text;
	}
}
