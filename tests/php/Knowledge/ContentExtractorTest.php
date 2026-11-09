<?php
/**
 * Tests for the text of page-builder content (TX-439).
 *
 * The fixtures in tests/php/fixtures/ are written by hand in the shortcode syntax that Divi, WPBakery and Avada save in
 * post_content. They are not copies from sites built with the real builders: the builders themselves are not installed.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Tests\Knowledge;

use TeraxAI\Knowledge\ContentExtractor;
use TeraxAI\Knowledge\KeywordRetriever;
use TeraxAI\Tests\TestCase;

/**
 * Class ContentExtractorTest
 */
class ContentExtractorTest extends TestCase {

	/**
	 * Builder fixture => [ file, a question that is only an accordion title, its answer, words that must not leak ].
	 *
	 * @return array
	 */
	public function builders() {
		return array(
			'Divi'     => array( 'divi', 'Übernimmt die Kasse die Zahnreinigungskosten?', 'Die gesetzliche Kasse übernimmt die Kosten nur teilweise.', array( 'et_pb', 'fb_built', '_builder_version', 'global_colors_info', 'example.test' ) ),
			'WPBakery' => array( 'wpbakery', 'Wie vereinbare ich einen Termin?', 'Rufen Sie uns an oder buchen Sie online über den Button.', array( 'vc_', 'tab_id', 'active_section', 'img_size' ) ),
			'Avada'    => array( 'avada', 'Tut ein Implantat weh?', 'Die Behandlung erfolgt unter örtlicher Betäubung.', array( 'fusion_', 'hundred_percent', 'hide_on_mobile', 'boxed_mode' ) ),
		);
	}

	/**
	 * Fixture text.
	 *
	 * @param string $name Fixture name.
	 * @return string
	 */
	private function fixture( $name ) {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $name . '.txt' );
	}

	/**
	 * A published page with the given content (saving it indexes it).
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @return \WP_Post
	 */
	private function page( $title, $content ) {
		return self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * @dataProvider builders
	 */
	public function test_the_text_inside_builder_shortcodes_is_kept( $name, $question, $answer, $leaks ) {
		$text = ContentExtractor::extract( $this->page( 'Seite ' . $name, $this->fixture( $name ) ) );

		$this->assertStringContainsString( $question, $text, 'the accordion title (the question) is indexed' );
		$this->assertStringContainsString( $answer, $text, 'the text inside the accordion is indexed' );
		foreach ( $leaks as $word ) {
			$this->assertStringNotContainsString( $word, $text, "no layout data ($word) in the indexed text" );
		}
		$this->assertStringNotContainsString( '[', $text );
	}

	public function test_the_text_of_the_divi_page_comes_in_reading_order_with_line_breaks() {
		$text = ContentExtractor::extract( $this->page( 'Seite Divi', $this->fixture( 'divi' ) ) );

		$this->assertLessThan( strpos( $text, 'Übernimmt die Kasse' ), strpos( $text, 'Zahnreinigung für 89 Euro' ) );
		$this->assertStringContainsString( "Wie lange dauert die Behandlung?\nEtwa fünfundvierzig Minuten.", $text, 'question and answer are separate lines' );
		$this->assertStringNotContainsString( 'ZahnreinigungskostenDie', $text, 'neighbouring elements do not run together' );
	}

	/**
	 * @dataProvider builders
	 */
	public function test_the_retriever_finds_the_faq_answer_of_a_builder_page( $name, $question, $answer ) {
		$page = $this->page( 'Seite ' . $name, $this->fixture( $name ) );

		$ids = array_map(
			static function ( $row ) {
				return (int) $row['post_id'];
			},
			( new KeywordRetriever() )->retrieve( $question, 5 )
		);

		$this->assertContains( $page->ID, $ids );
	}

	public function test_registered_shortcodes_are_not_run_but_their_text_is_kept() {
		$ran = false;
		add_shortcode(
			'terax_test_box',
			static function () use ( &$ran ) {
				$ran = true;
				return 'OUTPUT-OF-THE-SHORTCODE';
			}
		);

		$text = ContentExtractor::extract( $this->page( 'Box', '<p>Davor</p>[terax_test_box title="Hinweis"]Wichtiger Text[/terax_test_box]<p>Danach</p>' ) );
		remove_shortcode( 'terax_test_box' );

		$this->assertFalse( $ran, 'no plugin code runs while indexing' );
		$this->assertStringNotContainsString( 'OUTPUT-OF-THE-SHORTCODE', $text );
		$this->assertStringContainsString( 'Hinweis', $text );
		$this->assertStringContainsString( 'Wichtiger Text', $text );
		$this->assertStringContainsString( 'Davor', $text );
		$this->assertStringContainsString( 'Danach', $text );
	}

	public function test_an_escaped_shortcode_is_shown_as_typed_and_other_brackets_stay() {
		$text = ContentExtractor::shortcodes_to_text( 'Tippen Sie [[gallery]] ein. Siehe [1] und [sic]. Preis [vc_empty_space height="20px"] 89 Euro.' );

		$this->assertStringContainsString( '[gallery]', $text );
		$this->assertStringContainsString( '[1]', $text );
		$this->assertStringContainsString( '[sic]', $text );
		$this->assertStringNotContainsString( 'vc_empty_space', $text );
		$this->assertStringContainsString( '89 Euro', $text );
	}

	public function test_content_without_shortcodes_is_unchanged() {
		$html = '<p>Normaler Text ohne Klammern.</p>';

		$this->assertSame( $html, ContentExtractor::shortcodes_to_text( $html ) );
		$this->assertSame( '', ContentExtractor::shortcodes_to_text( '' ) );
	}

	public function test_the_attributes_that_count_as_text_can_be_changed() {
		add_filter(
			'terax_ai_shortcode_text_attributes',
			static function ( $names ) {
				$names[] = 'question';
				return $names;
			}
		);

		$text = ContentExtractor::shortcodes_to_text( '[vc_toggle question="Gibt es Parkplätze?" color="blue"]Ja, im Hof.[/vc_toggle]' );

		$this->assertStringContainsString( 'Gibt es Parkplätze?', $text );
		$this->assertStringNotContainsString( 'blue', $text );
		$this->assertStringContainsString( 'Ja, im Hof.', $text );
	}

	public function test_deeply_nested_shortcodes_do_not_run_away() {
		$content = str_repeat( '[vc_row]', 60 ) . 'Tief verschachtelt' . str_repeat( '[/vc_row]', 60 );

		$this->assertStringContainsString( 'Tief verschachtelt', ContentExtractor::shortcodes_to_text( $content ) );
	}
}
