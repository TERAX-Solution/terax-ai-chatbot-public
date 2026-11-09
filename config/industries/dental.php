<?php
/**
 * Industry preset: dental / medical practice. This is the default and must keep the behaviour
 * of every install made before presets existed (see tests/industry-test.php, prompt snapshot).
 *
 * Placeholders in rule lines: {contact} = the practice phone / e-mail text built by PromptBuilder.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

return array(
	'label'           => 'Dental practice',
	'business_type'   => 'dental practice',
	'assistant_name'  => 'Praxis-Assistent',

	// Prompt: "## Safety (strict)" lines.
	'safety_rules'    => array(
		'You are not a doctor. Never diagnose, never recommend medication or treatment, never assess symptoms.',
		'For pain, swelling, accidents or other urgent problems: advise calling the practice ({contact}). For life-threatening emergencies: call 112.',
		'Do not ask for health details, insurance numbers or other sensitive data.',
	),
	// Prompt: optional "## Industry rules" lines (none for dental, keeps the prompt unchanged).
	'extra_rules'     => array(),

	'greeting'        => "Hallo! 👋 Ich beantworte Ihre Fragen zu unserer Praxis und helfe Ihnen bei der Terminbuchung.\n\nHello! I can also answer in English.",
	'suggestions'     => array( 'Öffnungszeiten?', 'Welche Leistungen bieten Sie an?', 'Termin vereinbaren' ),

	// Booking systems offered in the admin (ids from ExternalLink::presets()); the first fits the industry best.
	'booking_presets' => array( 'drflex', 'doctolib', 'calendly', 'custom' ),
	'booking_preset'  => 'drflex',
	'booking_label'   => 'Online-Termin buchen',
	// Words that show the booking button even when the AI forgot the token.
	'booking_pattern' => '/\b(termin\w*|appointment\w*|buchen|book(ing)?|vereinbar\w*|reserv\w*|randevu|cita)\b/iu',

	// Sales mode (TX-470) is not offered to medical practices (medical advertising rules, no pushy selling).
	'sales_allowed'   => false,
	// Text of the lead form button in sales mode.
	'cta_label'       => 'Anfrage senden',

	'consent_text'    => 'Mit der Nutzung des Chats stimmen Sie der Verarbeitung Ihrer Eingaben gemäß unserer Datenschutzerklärung zu. Bitte geben Sie keine sensiblen Gesundheitsdaten ein.',
);
