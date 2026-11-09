<?php
/**
 * Turns a post into clean plain text.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentExtractor
 *
 * Handles Gutenberg blocks, classic content, Elementor and the page builders that keep their layout as shortcodes
 * (Divi, WPBakery, Avada/Fusion and similar): the shortcode tags are removed, the text inside them is kept.
 * Other page builders can supply text through the `terax_ai_post_content` filter.
 */
class ContentExtractor {

	/**
	 * Extract plain text.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function extract( \WP_Post $post ) {
		$html = '';

		// Elementor stores layout in post meta; render it if available.
		if ( did_action( 'elementor/loaded' ) && get_post_meta( $post->ID, '_elementor_edit_mode', true ) && class_exists( '\Elementor\Plugin' ) ) {
			try {
				$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID );
			} catch ( \Throwable $e ) {
				$html = '';
			}
		}

		if ( '' === trim( (string) $html ) ) {
			$html = do_blocks( $post->post_content );
			$html = self::shortcodes_to_text( $html );
		}

		$text = self::html_to_text( $html );

		/**
		 * Filter the text that gets indexed for a post.
		 *
		 * @param string   $text Plain text.
		 * @param \WP_Post $post Post.
		 */
		return (string) apply_filters( 'terax_ai_post_content', $text, $post );
	}

	/**
	 * Remove shortcode tags but keep what is inside them.
	 *
	 * WordPress' strip_shortcodes() deletes the text between [tag] and [/tag], which is where Divi, WPBakery and Avada keep
	 * all of their content. Shortcodes are not run (no plugin code, no output that depends on a visitor or a page): only
	 * the tags go. A few attributes hold visible text (an accordion item's title is the FAQ question) and are kept too.
	 *
	 * Tags that are not registered (the builder is switched off, but the old content is still full of its tags) are
	 * removed as well when their name starts with a known builder prefix.
	 *
	 * @param string $content Post content.
	 * @param int    $depth   Nesting level (internal).
	 * @return string
	 */
	public static function shortcodes_to_text( $content, $depth = 0 ) {
		global $shortcode_tags;

		$content = (string) $content;
		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}

		$tags = array_keys( (array) $shortcode_tags );
		if ( preg_match_all( '/\[\/?((?:et_pb|vc|fusion|av|dt|ult)_[a-z0-9_]+)/i', $content, $found ) ) {
			$tags = array_merge( $tags, $found[1] );
		}
		$tags = array_values( array_unique( $tags ) );
		if ( ! $tags ) {
			return $content;
		}

		/**
		 * Shortcode attributes whose value is visible text and is indexed (for example the question of an accordion item).
		 *
		 * @param string[] $names Attribute names.
		 */
		$text_atts = (array) apply_filters( 'terax_ai_shortcode_text_attributes', array( 'title', 'heading', 'sub_heading', 'subheading', 'subtitle', 'label' ) );

		$regex = get_shortcode_regex( $tags );

		$result = preg_replace_callback(
			"/$regex/",
			static function ( $m ) use ( $depth, $text_atts ) {
				// [[tag]] is the way to show a shortcode as text: keep it as typed.
				if ( '[' === $m[1] && ']' === $m[6] ) {
					return substr( $m[0], 1, -1 );
				}

				$attributes = '';
				$parsed     = shortcode_parse_atts( $m[3] );
				if ( is_array( $parsed ) ) {
					foreach ( $text_atts as $name ) {
						if ( isset( $parsed[ $name ] ) && is_string( $parsed[ $name ] ) && '' !== trim( $parsed[ $name ] ) ) {
							$attributes .= "\n" . trim( $parsed[ $name ] ) . "\n";
						}
					}
				}

				$inner = isset( $m[5] ) ? (string) $m[5] : '';
				if ( '' !== $inner && $depth < 20 ) {
					$inner = self::shortcodes_to_text( $inner, $depth + 1 );
				}

				// A line break on both sides, so the text of neighbouring elements does not run together.
				return $m[1] . "\n" . $attributes . $inner . "\n" . $m[6];
			},
			$content
		);

		return null === $result ? $content : $result;
	}

	/**
	 * Convert HTML to readable text, keeping paragraph breaks.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		$html = preg_replace( '#<(script|style|noscript|svg|iframe)[^>]*>.*?</\1>#is', ' ', (string) $html );
		// <li> starts its own line, so </li> must not add a second one (that left a blank line between list items).
		$html = preg_replace( '#<(br|/p|/div|/ul|/ol|/h[1-6]|/tr|/section|/article)[^>]*>#i', "\n", $html );
		$html = preg_replace( '#<li[^>]*>#i', "\n- ", $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = preg_replace( "/ *\n */u", "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		return trim( $text );
	}
}
