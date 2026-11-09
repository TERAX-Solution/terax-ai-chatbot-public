<?php
/**
 * Industry preset: general small business (neutral, no medical wording).
 *
 * Placeholders in rule lines: {contact} = the phone / e-mail text built by PromptBuilder.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

return array(
	'label'           => 'General business',
	'business_type'   => 'small business',
	'assistant_name'  => 'Assistent',

	'safety_rules'    => array(
		'Do not give legal, medical or financial advice. For such questions refer to the right professional or to us ({contact}).',
		'Never ask for passwords, payment details, ID numbers or other sensitive data in the chat.',
	),
	'extra_rules'     => array(),

	'greeting'        => "Hallo! 👋 Ich beantworte Ihre Fragen zu unserem Unternehmen und helfe Ihnen bei der Terminvereinbarung.\n\nHello! I can also answer in English.",
	'suggestions'     => array( 'Öffnungszeiten?', 'Welche Leistungen bieten Sie an?', 'Termin vereinbaren' ),

	'booking_presets' => array( 'calendly', 'custom' ),
	'booking_preset'  => 'calendly',
	'booking_label'   => 'Termin buchen',
	'booking_pattern' => '/\b(termin\w*|appointment\w*|buchen|book(ing)?|vereinbar\w*|reserv\w*|anmeld\w*|melde\w*\s[^.?!]{0,30}?\ban|randevu|cita)\b/iu',

	'sales_allowed'   => true,
	'cta_label'       => 'Anfrage senden',

	'consent_text'    => 'Mit der Nutzung des Chats stimmen Sie der Verarbeitung Ihrer Eingaben gemäß unserer Datenschutzerklärung zu. Bitte geben Sie keine sensiblen Daten ein.',
);
