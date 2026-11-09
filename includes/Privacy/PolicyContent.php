<?php
/**
 * Suggested text for Settings → Privacy → Policy Guide.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Privacy;

use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class PolicyContent
 *
 * The text is a suggestion only and must be reviewed by the site owner / a lawyer.
 * Why no WP personal-data exporter/eraser: conversations are anonymous (no e-mail or name),
 * so WordPress' e-mail based Export/Erase tools cannot find them. Use "Delete all conversations"
 * on the Conversations page instead.
 */
class PolicyContent {

	use Base;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the suggested policy text.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			terax_ai_config( 'plugin' )['menu_title'],
			wp_kses_post( wpautop( $this->text() ) )
		);
	}

	/**
	 * Build the German + English suggestion from the current settings.
	 *
	 * @return string
	 */
	private function text() {
		$settings  = terax_ai_setting( 'privacy' );
		$log       = ! empty( $settings['log_conversations'] );
		$days      = max( 1, (int) $settings['retention_days'] );
		$vendor    = $this->vendor_label( 'en' );
		$vendor_de = $this->vendor_label( 'de' );
		// Health wording only for the dental preset; other industries say "sensible Daten" / "sensitive data".
		$dental = 'dental' === \TeraxAI\Support\Industry::current_id();

		$de  = '<strong class="privacy-policy-tutorial">Hinweis: Dies ist ein Textvorschlag und keine Rechtsberatung. Bitte prüfen Sie ihn und passen Sie ihn an ' . ( $dental ? 'Ihre Praxis' : 'Ihr Unternehmen' ) . ' an.</strong>' . "\n\n";
		$de .= '<h2>Chat-Assistent (KI)</h2>' . "\n\n";
		$de .= 'Auf unserer Website können Sie einen KI-gestützten Chat-Assistenten nutzen. Die Nutzung beginnt erst, nachdem Sie dem Hinweis zugestimmt und den Chat gestartet haben. ';
		$de .= 'Ihre Nachricht sowie die letzten Nachrichten des Gesprächs werden an ' . esc_html( $vendor_de ) . ' übermittelt, damit eine Antwort erzeugt werden kann. Dazu werden passende Auszüge aus den Inhalten unserer Website mitgesendet. ';
		$de .= '[Mit dem Anbieter besteht ein Auftragsverarbeitungsvertrag (AVV). Eine Übermittlung in Drittländer, z. B. die USA, erfolgt auf Grundlage von ... – bitte ergänzen.]' . "\n\n";
		if ( $log ) {
			$de .= 'Wir speichern Ihre Nachrichten und die Antworten des Assistenten, die Adresse der Seite, auf der das Gespräch begonnen wurde, sowie einen gesalzenen Hash Ihrer IP-Adresse (die IP-Adresse selbst wird nicht gespeichert). ';
			$de .= 'Das Gespräch ist keiner Person zugeordnet. Die Daten werden nach ' . $days . ' Tagen automatisch gelöscht. ';
		} else {
			$de .= 'Der Inhalt der Gespräche wird bei uns nicht gespeichert; es werden nur anonyme Zähler geführt. ';
		}
		$de .= 'Bitte geben Sie keine sensiblen ' . ( $dental ? 'Gesundheitsdaten' : 'Daten' ) . ', Telefonnummern oder E-Mail-Adressen in den Chat ein.' . "\n\n";
		$de .= 'Rechtsgrundlage: [Art. 6 Abs. 1 lit. a DSGVO (Einwilligung) – bitte prüfen und ergänzen]. Die Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen, indem Sie den Chat nicht weiter nutzen und die Daten Ihres Browsers löschen.';

		$en  = '<h2>Chat assistant (AI)</h2>' . "\n\n";
		$en .= 'Our website offers an AI-powered chat assistant. It is only used after you have accepted the notice and started the chat. ';
		$en .= 'Your message and the most recent messages of the conversation are sent to ' . esc_html( $vendor ) . ' to generate a reply, together with relevant excerpts of our website content. ';
		$en .= '[A data processing agreement (DPA) is in place with the provider. Transfers to third countries, e.g. the USA, are based on ... – please complete.]' . "\n\n";
		if ( $log ) {
			$en .= 'We store your messages and the assistant\'s replies, the address of the page where the conversation started, and a salted hash of your IP address (the IP address itself is not stored). ';
			$en .= 'The conversation is not linked to a person. The data is deleted automatically after ' . $days . ' days. ';
		} else {
			$en .= 'The content of conversations is not stored by us; only anonymous counters are kept. ';
		}
		$en .= 'Please do not enter sensitive ' . ( $dental ? 'health data' : 'data' ) . ', phone numbers or e-mail addresses in the chat.' . "\n\n";
		$en .= 'Legal basis: [Art. 6(1)(a) GDPR (consent) – please review and complete]. You can withdraw your consent at any time with effect for the future by no longer using the chat and clearing your browser data.';

		if ( \TeraxAI\Chat\SalesMode::enabled() ) {
			$de .= "\n\n" . 'Wenn Sie im Chat ein Kontaktformular absenden, speichern wir Ihren Namen, Ihre Telefonnummer oder E-Mail-Adresse und Ihre Nachricht und senden sie per E-Mail an uns, um Sie zu kontaktieren. Die Angaben sind freiwillig, die Einwilligung geben Sie im Formular. Wir löschen sie nach ' . $days . ' Tagen automatisch.';
			$en .= "\n\n" . 'If you submit a contact form in the chat, we store your name, your phone number or e-mail address and your message and e-mail it to us so that we can contact you. The details are voluntary and you give your consent in the form. We delete them automatically after ' . $days . ' days.';
		}

		if ( \TeraxAI\Chat\CallbackMode::enabled() ) {
			$de .= "\n\n" . 'Wenn Sie im Chat einen Rückruf anfordern, speichern wir Ihren Namen, Ihre Telefonnummer, die gewünschte Rückrufzeit und Ihre Nachricht und senden sie per E-Mail an uns, um Sie zurückzurufen. Die Angaben sind freiwillig, die Einwilligung geben Sie im Formular. Wir löschen sie nach ' . $days . ' Tagen automatisch.';
			$en .= "\n\n" . 'If you ask for a call back in the chat, we store your name, your phone number, your preferred time and your message and e-mail it to us so that we can call you. The details are voluntary and you give your consent in the form. We delete them automatically after ' . $days . ' days.';
		}

		return $de . "\n\n" . $en;
	}

	/**
	 * Name of the vendor that receives the messages.
	 *
	 * @param string $lang 'de' or 'en' (only used for the managed-AI wording).
	 * @return string
	 */
	private function vendor_label( $lang ) {
		if ( 'managed' === terax_ai_setting( 'ai.mode' ) ) {
			return 'de' === $lang
				? 'den von TERAX Solution betriebenen KI-Dienst (der die Anfrage an einen KI-Modellanbieter weiterleitet)'
				: 'the AI service operated by TERAX Solution (which forwards the request to an AI model provider)';
		}
		$providers = terax_ai_config( 'providers' );
		$id        = (string) terax_ai_setting( 'ai.provider' );
		return isset( $providers[ $id ]['label'] ) ? $providers[ $id ]['label'] : $id;
	}
}
