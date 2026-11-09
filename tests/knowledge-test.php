<?php
/**
 * Standalone test for TX-432 (Chunker, ContentExtractor::html_to_text, KeywordRetriever terms/stems), no WordPress needed.
 *
 *   php -d extension=mbstring tests/knowledge-test.php
 *
 * The parts that need a database (Indexer, retrieve) are covered by the browser suite: tests/e2e/indexer.spec.js.
 *
 * @package TeraxAI
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}

require dirname( __DIR__ ) . '/includes/Knowledge/RetrieverInterface.php';
require dirname( __DIR__ ) . '/includes/Knowledge/Chunker.php';
require dirname( __DIR__ ) . '/includes/Knowledge/ContentExtractor.php';
require dirname( __DIR__ ) . '/includes/Knowledge/KeywordRetriever.php';

use TeraxAI\Knowledge\Chunker;
use TeraxAI\Knowledge\ContentExtractor;
use TeraxAI\Knowledge\KeywordRetriever;

$fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $detail ? '' : "  ($detail)" ) . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}

// ---- Chunker::split -------------------------------------------------------------------------------------------
check( 'empty text gives no chunks', array() === Chunker::split( '' ) );
check( 'whitespace only gives no chunks', array() === Chunker::split( " \n\t \n\n " ) );
check( 'null gives no chunks', array() === Chunker::split( null ) );

$short = Chunker::split( 'Wir sind Montag bis Freitag von 8 bis 18 Uhr für Sie da.' );
check( 'short text is one chunk', 1 === count( $short ) && 'Wir sind Montag bis Freitag von 8 bis 18 Uhr für Sie da.' === $short[0] );

$sentence = 'Dies ist ein Satz mit etwa vierzig Zeichen.'; // 43 characters.
$long     = implode( ' ', array_fill( 0, 60, $sentence ) );
$chunks   = Chunker::split( $long, 200, 40 );
check( 'long text gives several chunks', count( $chunks ) > 5, 'got ' . count( $chunks ) );
$max = 0;
foreach ( $chunks as $c ) {
	$max = max( $max, mb_strlen( $c ) );
}
// A chunk may carry the overlap of the previous one on top of the size.
check( 'chunks stay within size + overlap', $max <= 200 + 40 + 1, 'longest ' . $max );
$overlap_ok = true;
for ( $i = 1; $i < count( $chunks ); $i++ ) {
	$tail = mb_substr( $chunks[ $i - 1 ], -40 );
	if ( 0 !== mb_strpos( $chunks[ $i ], $tail ) ) {
		$overlap_ok = false;
	}
}
check( 'each chunk starts with the tail of the previous one (overlap)', $overlap_ok );
check( 'no text is lost', mb_strlen( implode( ' ', $chunks ) ) >= mb_strlen( $long ) - 60 );

$none = Chunker::split( $long, 200, 0 );
$ok0  = true;
foreach ( $none as $c ) {
	if ( mb_strlen( $c ) > 200 ) {
		$ok0 = false;
	}
}
check( 'overlap 0: every chunk is at most size', $ok0 );

$blob   = str_repeat( 'a', 1000 ); // One long "sentence" without punctuation or spaces.
$hard   = Chunker::split( $blob, 300, 0 );
$lenmax = max( array_map( 'mb_strlen', $hard ) );
check( 'a sentence without punctuation is hard-split', count( $hard ) >= 4 && $lenmax <= 300, 'chunks ' . count( $hard ) . ', longest ' . $lenmax );
check( 'hard split keeps all characters', 1000 === array_sum( array_map( 'mb_strlen', $hard ) ) );

$paras = Chunker::split( "Erster Absatz.\n\nZweiter Absatz.", 1200, 150 );
check( 'paragraphs that fit stay in one chunk, separated by a line break', 1 === count( $paras ) && false !== strpos( $paras[0], "\n" ) );

$umlaut = Chunker::split( str_repeat( 'Zähne ', 100 ), 100, 10 );
check( 'multibyte text is never cut in the middle of a character', 0 === count( array_filter( $umlaut, function ( $c ) { return ! mb_check_encoding( $c, 'UTF-8' ); } ) ) );

// ---- ContentExtractor::html_to_text ---------------------------------------------------------------------------
$html = ContentExtractor::html_to_text( '<p>Hallo</p><script>alert(1)</script><style>p{color:red}</style><noscript>x</noscript><p>Welt</p>' );
check( 'script, style and noscript content is removed', false === strpos( $html, 'alert' ) && false === strpos( $html, 'color' ) && false === strpos( $html, 'x' ) );
check( 'paragraphs become line breaks', "Hallo\nWelt" === $html, json_encode( $html ) );
check( '<br> becomes a line break', "A\nB" === ContentExtractor::html_to_text( 'A<br>B' ) && "A\nB" === ContentExtractor::html_to_text( 'A<br/>B' ) );
check( 'list items become lines with a dash', "- Eins\n- Zwei" === ContentExtractor::html_to_text( '<ul><li>Eins</li><li>Zwei</li></ul>' ), json_encode( ContentExtractor::html_to_text( '<ul><li>Eins</li><li>Zwei</li></ul>' ) ) );
check( 'headings and divs end a line', "Titel\nText" === ContentExtractor::html_to_text( '<h2>Titel</h2><div>Text</div>' ) );
check( 'entities are decoded', 'Müller & Söhne <3' === ContentExtractor::html_to_text( 'M&uuml;ller &amp; S&ouml;hne &lt;3' ), json_encode( ContentExtractor::html_to_text( 'M&uuml;ller &amp; S&ouml;hne &lt;3' ) ) );
check( 'non-breaking spaces become normal spaces', 'a b' === ContentExtractor::html_to_text( "a&nbsp;&nbsp;b" ) && 'a b' === ContentExtractor::html_to_text( "a\u{00A0}b" ) );
check( 'three or more blank lines collapse to one', "A\n\nB" === ContentExtractor::html_to_text( "<p>A</p>\n\n\n\n<p>B</p>" ) );
check( 'multiline script blocks are removed', false === strpos( ContentExtractor::html_to_text( "<script type=\"text/javascript\">\nvar a = 1;\n</script>Text" ), 'var' ) );
check( 'empty input gives an empty string', '' === ContentExtractor::html_to_text( '' ) && '' === ContentExtractor::html_to_text( null ) );

// ---- KeywordRetriever::terms / stems / tokens -----------------------------------------------------------------
check( 'terms: German stop words are dropped', array( 'zahnreinigung', 'hoch', 'kosten' ) === KeywordRetriever::terms( 'Was ist die Zahnreinigung und wie hoch sind die Kosten?' ), json_encode( KeywordRetriever::terms( 'Was ist die Zahnreinigung und wie hoch sind die Kosten?' ) ) );
check( 'terms: English stop words are dropped', array( 'offer', 'opening', 'hours', 'clinic' ) === KeywordRetriever::terms( 'Do you offer an opening hours for the clinic?' ) );
check( 'terms: words under 3 letters are dropped', array() === KeywordRetriever::terms( 'ab zu im ok' ) );
check( 'terms: lower-cased and de-duplicated', array( 'zahnreinigung' ) === KeywordRetriever::terms( 'Zahnreinigung zahnreinigung ZAHNREINIGUNG' ) );
check( 'terms: at most 12 terms', 12 === count( KeywordRetriever::terms( implode( ' ', range( 100, 130 ) ) ) ) );
check( 'terms: punctuation only gives nothing', array() === KeywordRetriever::terms( '?!... --' ) && array() === KeywordRetriever::terms( '' ) );
check( 'terms: umlauts are kept', array( 'öffnungszeiten' ) === KeywordRetriever::terms( 'Öffnungszeiten?' ) );

$st = KeywordRetriever::stems( array( 'implantate', 'implantat', 'zahnreinigung', 'kosten', 'ärzte' ), false );
check( 'stems: "Implantate" becomes "implantat"', 'implantat' === $st[0] );
check( 'stems: plural and singular meet in one stem (de-duplicated)', 1 === count( array_keys( $st, 'implantat', true ) ) );
check( 'stems: "-ung" is cut from long words', in_array( 'zahnreinig', $st, true ) );
check( 'stems: words of 6 letters or fewer are left alone', in_array( 'kosten', $st, true ) && in_array( 'ärzte', $st, true ) );
check( 'stems: participle "geöffnet" gives "öffn"', array( 'öffn' ) === KeywordRetriever::stems( array( 'geöffnet' ), false ) );
check( 'stems: synonyms are added only on request', array( 'offen' ) === KeywordRetriever::stems( array( 'offen' ), false ) && in_array( 'öffn', KeywordRetriever::stems( array( 'offen' ), true ), true ) );

check( 'tokens: short words and the practice-wide filler stems are dropped', array( 'zahnreinig', 'unser', 'kostet', 'dauert' ) === KeywordRetriever::tokens( 'Die Zahnreinigung in unserer Praxis kostet 80 Euro und der Termin dauert' ) );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
