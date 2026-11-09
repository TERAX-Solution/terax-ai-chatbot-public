<?php
/**
 * Standalone test for TX-460 (grouping of unanswered questions), no WordPress needed.
 *
 *   php tests/insights-test.php
 *
 * @package TeraxAI
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/Knowledge/RetrieverInterface.php';
require dirname( __DIR__ ) . '/includes/Knowledge/KeywordRetriever.php';
require dirname( __DIR__ ) . '/includes/Chat/Insights.php';

use TeraxAI\Chat\Insights;

$fail = 0;
function check( $name, $ok ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}

// Newest first, like the repository returns them.
$rows = array(
	array( 'question' => 'Übernehmen Sie Kosten für Zahnreinigung bei Versicherung?', 'created_at' => '2026-11-05 10:00:00' ),
	array( 'question' => 'Gibt es Parkplätze vor der Praxis?', 'created_at' => '2026-11-05 09:00:00' ),
	array( 'question' => 'Zahnreinigung Kosten Versicherung übernehmen?', 'created_at' => '2026-11-04 12:00:00' ),
	array( 'question' => '   ', 'created_at' => '2026-11-04 11:00:00' ),
	array( 'question' => null, 'created_at' => '2026-11-04 10:30:00' ),
	array( 'question' => 'Parkplätze Praxis vorhanden', 'created_at' => '2026-11-03 08:00:00' ),
	array( 'question' => 'Do you offer Invisalign?', 'created_at' => '2026-11-02 08:00:00' ),
	array( 'question' => 'Zahnreinigung Kosten Versicherung übernehmen?', 'created_at' => '2026-11-01 12:00:00' ),
);

$g = Insights::group( $rows );
check( 'three topics (cleaning cost, parking, invisalign)', 3 === count( $g ) );
check( 'newest wording is the headline', 'Übernehmen Sie Kosten für Zahnreinigung bei Versicherung?' === $g[0]['question'] );
check( 'cleaning topic counts 3', 3 === $g[0]['count'] );
check( 'topics keep newest-first order', 'Gibt es Parkplätze vor der Praxis?' === $g[1]['question'] && 'Do you offer Invisalign?' === $g[2]['question'] );
check( 'parking topic counts 2', 2 === $g[1]['count'] );
check( 'last_at is the newest time', '2026-11-05 10:00:00' === $g[0]['last_at'] );
check( 'other wordings are listed once', array( 'Zahnreinigung Kosten Versicherung übernehmen?' ) === $g[0]['variants'] );
check( 'tokens are not leaked', ! isset( $g[0]['tokens'] ) );
check( 'empty input gives no groups', array() === Insights::group( array() ) );
check( 'blank questions are skipped', 0 === count( Insights::group( array( array( 'question' => '  ' ), array( 'question' => null ) ) ) ) );
check( 'similarity of identical sets is 1', 1.0 === Insights::similarity( array( 'a', 'b' ), array( 'b', 'a' ) ) );
check( 'similarity of disjoint sets is 0', 0.0 === Insights::similarity( array( 'a' ), array( 'b' ) ) );
check( 'similarity of empty sets is 0', 0.0 === Insights::similarity( array(), array() ) );

// Questions with no usable keyword never merge into each other by accident.
$short = Insights::group( array( array( 'question' => 'Ja?' ), array( 'question' => 'Ok' ) ) );
check( 'keyword-less questions stay separate', 2 === count( $short ) );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
