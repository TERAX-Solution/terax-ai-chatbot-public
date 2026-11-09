<?php
/**
 * WP-CLI commands.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Cli;

use TeraxAI\Knowledge\ChunkRepository;
use TeraxAI\Knowledge\Indexer;
use TeraxAI\Support\Settings;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Commands
 *
 * Developer tooling, only loaded when WP-CLI is running (see plugin.php).
 */
class Commands {

	/**
	 * Post meta that marks pages created by the seed command.
	 */
	const DEMO_META = '_terax_ai_demo';

	/**
	 * Create demo content for a fictional dental practice and rebuild the index.
	 *
	 * Creates German demo pages, sets the "Extra information" text and a demo
	 * booking URL, then rebuilds the knowledge index synchronously.
	 *
	 * ## OPTIONS
	 *
	 * [--reset]
	 * : Delete previously seeded demo pages before creating them again. Only pages created by this command are removed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp terax-ai seed-demo
	 *     wp terax-ai seed-demo --reset
	 *
	 * @subcommand seed-demo
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	public function seed_demo( $args, $assoc_args ) {
		$existing = $this->demo_page_ids();

		if ( ! empty( $assoc_args['reset'] ) ) {
			foreach ( $existing as $id ) {
				wp_delete_post( $id, true ); // Force delete; also drops the page's chunks.
			}
			WP_CLI::log( sprintf( 'Deleted %d demo page(s).', count( $existing ) ) );
			$existing = array();
		}

		if ( ! empty( $existing ) ) {
			WP_CLI::error( 'Demo pages already exist. Run with --reset to recreate them.' );
		}

		$created = 0;
		foreach ( $this->demo_pages() as $title => $paragraphs ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $this->to_blocks( $paragraphs ),
					'meta_input'   => array( self::DEMO_META => 1 ),
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				WP_CLI::error( sprintf( 'Could not create "%s": %s', $title, $id->get_error_message() ) );
			}
			++$created;
		}

		Settings::get_instance()->update(
			array(
				'knowledge' => array( 'custom_text' => $this->extra_information() ),
				'booking'   => array( 'url' => 'https://example.com/booking' ),
			)
		);

		$this->rebuild_index();

		$stats = ChunkRepository::get_instance()->stats();
		WP_CLI::success( sprintf( 'Created %d page(s); index has %d chunk(s) from %d source(s).', $created, $stats['chunks'], $stats['sources'] ) );
	}

	/**
	 * Queue a full rebuild and process every batch right away.
	 *
	 * @return void
	 */
	private function rebuild_index() {
		$indexer = Indexer::get_instance();

		delete_transient( 'terax_ai_index_lock' ); // A lock left over from an aborted run would stall the loop.
		Indexer::queue_full_rebuild();

		$guard = 100; // Safety net; one batch is 15 pages.
		while ( 'running' === $indexer->state()['status'] && $guard-- > 0 ) {
			$indexer->run_batch();
		}

		wp_clear_scheduled_hook( Indexer::BATCH_HOOK ); // Nothing left for WP-Cron to do.

		if ( 'running' === $indexer->state()['status'] ) {
			WP_CLI::warning( 'Index rebuild did not finish. Run it again from AI Assistant → Knowledge.' );
		}
	}

	/**
	 * IDs of pages created by the seed command.
	 *
	 * @return int[]
	 */
	private function demo_page_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => self::DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
	}

	/**
	 * Wrap paragraphs in Gutenberg paragraph markup.
	 *
	 * @param string[] $paragraphs Paragraph text (HTML-escaped).
	 * @return string
	 */
	private function to_blocks( array $paragraphs ) {
		$out = '';
		foreach ( $paragraphs as $p ) {
			$out .= "<!-- wp:paragraph -->\n<p>" . $p . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
		return trim( $out );
	}

	/**
	 * Text for Settings → Knowledge → "Extra information".
	 *
	 * @return string
	 */
	private function extra_information() {
		return "Wir behandeln gesetzlich und privat Versicherte. Auch die meisten Zusatzversicherungen werden akzeptiert.\n"
			. 'Parkplätze: Direkt vor der Praxis gibt es drei Kundenparkplätze, weitere im Parkhaus Mitte (2 Gehminuten).';
	}

	/**
	 * Demo pages of the fictional practice "Zahnzentrum Demo": title => paragraphs.
	 *
	 * @return array
	 */
	private function demo_pages() {
		return array(
			'Professionelle Zahnreinigung (PZR)' => array(
				'Die professionelle Zahnreinigung (PZR) im Zahnzentrum Demo entfernt weiche und harte Beläge, auch dort, wo die Zahnbürste nicht hinkommt. Sie schützt vor Karies und Zahnfleischentzündungen.',
				'Die Kosten für eine PZR liegen je nach Aufwand zwischen 90 und 120 Euro pro Sitzung. Die Behandlung dauert etwa 60 Minuten. Einige gesetzliche Krankenkassen bezuschussen die PZR, fragen Sie bitte bei Ihrer Kasse nach.',
				'Wir empfehlen die PZR ein- bis zweimal pro Jahr.',
			),
			'Implantate'                         => array(
				'Ein Zahnimplantat ersetzt die Zahnwurzel und trägt eine Krone, eine Brücke oder eine Prothese. Es fühlt sich an wie ein eigener Zahn und kann bei guter Pflege viele Jahre halten.',
				'Vor der Behandlung besprechen wir in einem ausführlichen Beratungsgespräch Ihre Wünsche, den Ablauf und die Kosten. Der Eingriff erfolgt in örtlicher Betäubung. Der Heilungsprozess dauert in der Regel drei bis sechs Monate.',
			),
			'Kontakt &amp; Öffnungszeiten'       => array(
				'Zahnzentrum Demo, Musterstraße 1, 12345 Musterstadt. Telefon: 01234 567890. E-Mail: praxis@example.com.',
				'Öffnungszeiten: Montag bis Donnerstag 8:00 bis 18:00 Uhr, Freitag 8:00 bis 14:00 Uhr. Am Wochenende ist die Praxis geschlossen.',
				'Termine vereinbaren Sie bequem online oder telefonisch während der Öffnungszeiten.',
			),
			'Angstpatienten'                     => array(
				'Viele Menschen haben Angst vor dem Zahnarzt, und das ist völlig in Ordnung. Bei uns nehmen Sie sich so viel Zeit, wie Sie brauchen. Wir erklären jeden Schritt, bevor wir ihn durchführen.',
				'Für besonders ängstliche Patientinnen und Patienten bieten wir ruhige Termine ohne Wartezeit, ein vorheriges Kennenlerngespräch und auf Wunsch eine Behandlung unter Lachgas oder Sedierung an.',
			),
			'Team'                               => array(
				'Unser Team besteht aus Dr. Anna Beispiel (Zahnärztin, Schwerpunkt Prophylaxe), Dr. Max Muster (Oralchirurg, Schwerpunkt Implantologie) und fünf zahnmedizinischen Fachangestellten.',
				'Wir bilden regelmäßig aus und besuchen mehrmals im Jahr Fortbildungen, damit Sie nach dem aktuellen Stand der Zahnmedizin behandelt werden.',
			),
			'Notfall'                            => array(
				'Bei akuten Zahnschmerzen oder nach einem Unfall melden Sie sich bitte so früh wie möglich telefonisch unter 01234 567890. Wir schieben Notfälle innerhalb unserer Öffnungszeiten noch am selben Tag ein.',
				'Außerhalb der Öffnungszeiten erreichen Sie den zahnärztlichen Notdienst unter der bundesweiten Nummer 116 117.',
			),
		);
	}
}
