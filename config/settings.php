<?php
/**
 * Default per-site settings (stored in the `terax_ai_settings` option).
 *
 * Adding a new setting = add it here with a default; the settings repository
 * merges stored values over these defaults, so old installs pick it up.
 *
 * @package TeraxAI
 */

defined( 'ABSPATH' ) || exit;

return array(
	'ai'        => array(
		'mode'         => 'byok',      // 'byok' (own key) | 'managed' (TERAX proxy).
		'provider'     => 'anthropic',
		'model'        => 'claude-haiku-4-5',
		'temperature'  => 0.3,
		'max_tokens'   => 700,
		'api_keys'     => array(),     // provider_id => encrypted key.
		'base_url'     => '',          // For openai_compatible.
		'notify_email' => true,       // E-mail the site admin when the AI keeps failing.
	),
	'assistant' => array(
		'enabled'        => true,
		'name'           => 'Praxis-Assistent',
		'greeting'       => "Hallo! 👋 Ich beantworte Ihre Fragen zu unserer Praxis und helfe Ihnen bei der Terminbuchung.\n\nHello! I can also answer in English.",
		'language'       => 'auto',   // 'auto' = reply in the visitor's language.
		'mode'           => 'informative', // 'informative' | 'sales' (sales: see Chat\SalesMode, not for dental).
		'callback'       => true,      // Offer the call-back form when the plan has `callback_requests` (Chat\CallbackMode, TX-459).
		'industry'       => 'dental',  // Preset id from config/industries/ (rules, texts, booking keywords).
		'business_type'  => 'dental practice',
		'phone'          => '',
		'email'          => '',
		'instructions'   => '',       // Extra instructions appended to the system prompt.
		'suggestions'    => array( 'Öffnungszeiten?', 'Welche Leistungen bieten Sie an?', 'Termin vereinbaren' ),
		'primary_color'  => '#0f766e',
		'text_on_brand'  => 'auto',   // Text color on the brand color: 'auto' (most readable) | 'light' | 'dark'.
		'position'       => 'right',  // 'right' | 'left'.
		'auto_open_secs' => 0,        // 0 = never auto-open.
		'show_branding'  => false,    // "Powered by TERAX" link in the widget footer. Off by default: WordPress.org only allows credit links the site owner turns on.
	),
	'knowledge' => array(
		'post_types'  => array( 'page', 'post' ),
		'exclude_ids' => array(),
		'custom_text' => '',          // Extra FAQ / facts not on the website.
		'max_chunks'  => 5,
	),
	'booking'   => array(
		'enabled'  => true,
		'provider' => 'external_link',
		'preset'   => 'drflex',       // drflex | doctolib | calendly | custom.
		'url'      => '',
		'label'    => 'Online-Termin buchen',
		'open_in'  => 'new_tab',      // 'new_tab' | 'modal'.
	),
	'setup'     => array(
		'completed' => false,         // True once the first-run wizard was finished.
	),
	'privacy'   => array(
		'log_conversations'    => true,
		'retention_days'       => 30,
		'rate_limit_per_hour'  => 30,
		'max_message_length'   => 1000,
		'consent_text'         => 'Mit der Nutzung des Chats stimmen Sie der Verarbeitung Ihrer Eingaben gemäß unserer Datenschutzerklärung zu. Bitte geben Sie keine sensiblen Gesundheitsdaten ein.',
		'privacy_url'          => '',
		'mask_pii'             => true,  // Replace phone numbers / e-mails in stored messages.
		'trusted_proxy_header' => '', // '' | 'cf' | 'xff' | 'real_ip'.
	),
);
