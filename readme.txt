=== TERAX AI Assistant ===
Contributors: terax
Tags: ai, chatbot, assistant, appointment, booking
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI assistant that answers visitor questions from your website content and helps them book an appointment.

== Description ==

* Answers only from your own pages, posts and extra FAQ text – no invented facts.
* Works with Claude, GPT, Gemini, Mistral or any OpenAI-compatible model.
* "Book appointment" button that opens your Dr. Flex, Doctolib, Calendly or other booking page.
* Built for medical and dental practices: no diagnoses, urgent cases are directed to the phone / 112.
* GDPR: consent notice, no stored IP addresses, automatic deletion after N days, logging can be disabled.
* Lightweight widget (~10 KB) that does not conflict with your theme.

== Installation ==

1. Upload the ZIP under Plugins → Add New → Upload, then activate.
2. Go to AI Assistant → Settings → AI model and add your API key.
3. Add your booking link under Settings → Booking.
4. Check AI Assistant → Knowledge – your pages are indexed automatically.

Shortcode for an inline chat: [terax_ai_chat height="560"]

== External services ==

The plugin works with the content of your own website. It connects to other services only when you set it up to do so, and it sends nothing to a TERAX server.

= AI provider (required to get answers) =

Each time a visitor sends a message in the chat, your server sends it to the AI provider you selected under AI Assistant > Settings > AI model, so the provider can write the answer. The chat only starts after the visitor has accepted your privacy notice.

Data sent: the visitor's question, up to ten earlier messages of the same chat, text excerpts from your own pages that match the question, the assistant's instructions, and your API key (in the request header). The visitor's IP address is not sent.

You choose one provider:

* Anthropic (Claude), https://api.anthropic.com: [Terms](https://www.anthropic.com/legal/commercial-terms), [Privacy policy](https://www.anthropic.com/legal/privacy)
* OpenAI (GPT), https://api.openai.com: [Terms](https://openai.com/policies/terms-of-use), [Privacy policy](https://openai.com/policies/privacy-policy)
* Google (Gemini), https://generativelanguage.googleapis.com: [Terms](https://ai.google.dev/gemini-api/terms), [Privacy policy](https://policies.google.com/privacy)
* Mistral AI, https://api.mistral.ai: [Terms and privacy policy](https://legal.mistral.ai/terms)
* Any OpenAI-compatible service at an address you enter (for example Groq, or a server of your own): the terms of that service apply.

= Appointment booking =

The "Book appointment" button is an ordinary link to the booking page you enter (for example Doctolib, Dr. Flex or Calendly). Visitors only leave your site if they click it. The plugin sends nothing to those services.

== Source code ==

The admin screens and the chat widget are built with Vite and React (admin) and Preact (chat widget). The compressed files are in `assets/admin/dist` and `assets/frontend/dist`; the readable source is in the public repository https://github.com/TERAX-Solution/terax-ai-chatbot-public (folder `src/`). To build them yourself: `npm ci` and `npm run build`.

== Changelog ==

= 1.0.0 =
* Initial release.
