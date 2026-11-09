# Architecture

## Request flow – one chat message

```
Widget (Preact, Shadow DOM)
  │  POST /wp-json/terax-ai/v1/chat  { message, history[], session_id, page_url }
  ▼
ChatController → ChatService::handle()
  1. validate & trim input                      privacy.max_message_length
  2. RateLimiter::hit(ip_hash)                  privacy.rate_limit_per_hour
  3. quota check                                Features::limit('messages_per_month')
  4. Retriever->retrieve(question + prev turn)  KeywordRetriever (FULLTEXT + stems)  ← filter terax_ai_retriever
  5. PromptBuilder::system(chunks, can_book)    safety rules + context              ← filter terax_ai_system_prompt
  6. ProviderRegistry->make()->chat(request)    BYOK vendor or TeraxProxy           ← filter terax_ai_chat_request
  7. booking intent: [[BOOKING]] token or keywords → BookingRegistry->action()
  8. UsageMeter::record(), ConversationRepository (if logging on)
  ▼
{ reply, sources[], actions[] }                                                     ← filter terax_ai_chat_response
```

Vendor errors are never shown to visitors: they get a polite fallback with the practice phone number, and the admin dashboard shows the last error.

## Modules

| Module | Key classes | Extend by |
|---|---|---|
| AI | `ProviderInterface`, `AbstractProvider`, `ProviderRegistry` | `config/providers.php` or `terax_ai_config_providers` filter |
| Knowledge | `Indexer`, `ContentExtractor`, `Chunker`, `ChunkRepository`, `RetrieverInterface` | `terax_ai_retriever`, `terax_ai_post_content`, `terax_ai_is_indexable` |
| Chat | `ChatService`, `PromptBuilder`, `RateLimiter`, `UsageMeter` | `terax_ai_system_prompt`, `terax_ai_chat_request`, `terax_ai_chat_response` |
| Booking | `BookingProviderInterface`, `ExternalLink`, `BookingRegistry` | `terax_ai_booking_providers`, `terax_ai_booking_presets` |
| Features (free) | `Support\Features` | `terax_ai_can`, `terax_ai_limit`, `terax_ai_plan`, `terax_ai_managed_provider` |
| License (Pro add-on, `pro/`) | `LicenseManager`, `LicenseClientInterface`, `Updater` | `pro/config/plans.php`, `pro/config/license.php` |

## Adding a new LLM

**OpenAI-compatible vendor (most new vendors):**

```php
// includes/AI/Providers/Groq.php
namespace TeraxAI\AI\Providers;
class Groq extends OpenAICompatible {
    protected function base_url() { return 'https://api.groq.com/openai/v1'; }
    protected function requires_key() { return true; }
}
```

```php
// config/providers.php
'groq' => array( 'class' => Groq::class, 'label' => 'Groq', 'models' => array( 'llama-3.3-70b-versatile' ) ),
```

**Different API format:** extend `AbstractProvider`, implement `chat( ChatRequest )` → `ChatResponse` and `list_models()`. Use `$this->request()` for HTTP so timeouts, errors and the `terax_ai_provider_http_args` filter work the same everywhere. See `Anthropic.php` / `Gemini.php` (~80 lines each).

**New model of an existing vendor:** nothing to do – admins click "Refresh models" or type the model ID.

**From an add-on plugin:**

```php
add_filter( 'terax_ai_config_providers', function ( $providers ) {
    $providers['my_llm'] = array( 'class' => My_LLM_Provider::class, 'label' => 'My LLM', 'models' => array() );
    return $providers;
} );
```

## Adding live booking (e.g. Dr. Flex API later)

1. Implement `Booking\Contracts\BookingProviderInterface`. `action()` can return a new action type, e.g. `{ type: 'slots', slots: [...] }`.
2. Register: `add_filter( 'terax_ai_booking_providers', fn( $p ) => $p + array( 'drflex_api' => DrFlexApi::class ) );`
3. Render the new action type in `src/frontend/Widget.jsx` (where `a.type === "link"` is handled).
4. Gate it with a new feature in `Support/Features.php` (default) and `pro/config/plans.php` (plans) (e.g. `live_booking`).

## Industry presets

One setting (`assistant.industry`, default `dental`) switches the assistant's "personality". A preset is one PHP file in `config/industries/` (`dental.php`, `driving_school.php`, `general.php`) that returns:

| Key | Used for |
|---|---|
| `label`, `business_type`, `assistant_name` | Admin select, prompt line "(business type)", default name |
| `safety_rules`, `extra_rules` | Prompt sections "Safety (strict)" and "Industry rules". `{contact}` is replaced with the phone / e-mail text. Medical rules exist only in `dental`. |
| `greeting`, `suggestions`, `consent_text` | Recommended texts the admin can apply |
| `booking_presets`, `booking_preset`, `booking_label` | Booking systems offered in the admin (ids from `ExternalLink::presets()`), default and button label |
| `booking_pattern` | Regex for the keyword fallback that shows the booking button (`ChatService`) |

**Add a 4th industry:** drop `config/industries/my_industry.php` with the keys above (file name = id). Or from an add-on: `add_filter( 'terax_ai_industries', fn( $all ) => $all + array( 'gym' => array( ... ) ) );`. Missing keys are filled from the dental preset. Unknown ids in the setting fall back to `dental`, so installs from before presets existed behave exactly as before (`tests/industry-test.php` compares the dental prompt with `tests/snapshots/prompt-dental.txt`).

Switching the industry in the admin never overwrites texts silently: Settings → Assistant → Industry asks "Apply recommended texts?". The setup wizard fills them straight away because nothing was edited yet. Run the checks with `php tests/industry-test.php`.

## Sales mode and lead form

`assistant.mode` is `informative` (default, neutral) or `sales`. Sales mode points gently to the next step and offers a contact form. It is only active when **both** hold, checked on every use by `Chat\SalesMode::enabled()`: the plan has the `sales_mode` feature (`config/plans.php`) and the industry preset has `sales_allowed` (false for dental: medical advertising rules). Saving settings turns an unallowed `sales` back into `informative` (`SalesMode::enforce()`).

* **Prompt:** `PromptBuilder` adds "## Sales mode" (next-step nudge at most every 2-3 replies, not twice in a row, never invent discounts, prices or availability). The model ends a reply with `[[BOOKING]]` when the booking button exists, otherwise with `[[LEAD]]`. The token is stripped from the visible text.
* **Action:** `ChatService` returns `{ type: 'lead', label }` (label = `cta_label` of the industry preset). Without a booking button, the booking keywords of the preset also trigger it.
* **Widget:** the `lead` action opens an inline form (name, phone or e-mail, optional message, consent checkbox, invisible honeypot field) that posts to `POST /terax-ai/v1/lead`.
* **Endpoint:** `LeadService::submit()`: sales mode must be on, honeypot filled = silent success without storing, consent required, name and phone (6+ digits) or e-mail required, 5 requests per visitor and hour (own `RateLimiter` bucket, filter `terax_ai_lead_limit_per_hour`). Stored in `wp_terax_ai_requests` (`RequestRepository`), e-mailed to `assistant.email` (fallback: admin e-mail) with Reply-To.
* **Privacy:** requests are purged with the conversations (same retention), listed in the generated privacy text when sales mode is on, and dropped on uninstall.
* **Dashboard:** "Leads" and conversion = (leads + booking clicks) / conversations.

Tests: `php tests/sales-mode-test.php`.

## Call-back requests (TX-459)

"Please call me back" form, independent of the industry (it works for dental, unlike sales mode). `Chat\CallbackMode::enabled()` = plan feature `callback_requests` (Praxis Plus and up) AND `assistant.callback` (Settings → Assistant, default on).

* **Prompt:** when enabled, one rule asks the model to end with `[[CALLBACK]]` when it cannot answer from the context or the visitor asks for a call back (never together with `[[BOOKING]]`). Without the feature the prompt is unchanged (dental snapshot test).
* **Action:** `ChatService` returns `{ type: 'callback' }` for the token, and when the visitor shows booking intent while there is no booking button and no sales lead form. The widget also shows a phone button in the header (`config.callback`).
* **Form:** name, phone (required, 6+ digits), preferred time (any/morning/afternoon), optional reason, consent, honeypot. `POST /terax-ai/v1/callback` goes through `LeadService::submit( $input, 'callback' )`: same validation, same table, own rate-limit bucket (`callback`, 5 per hour, filter `terax_ai_lead_limit_per_hour`). The preferred time is stored in front of the reason ("Preferred time: morning"). E-mail to `assistant.email` (fallback: admin e-mail).
* **Admin:** page **Requests** (leads and call-backs, newest first, filter new/done, mark done, delete) on `GET /requests`, `POST /requests/{id}/status`, `POST /requests/{id}/delete`. Column `status` (`new`/`done`) was added to `wp_terax_ai_requests` (DB version 1.2.0).
* **Privacy:** same retention purge as conversations; the generated privacy text mentions it while the form is on.

Tests: `php tests/callback-test.php`.

## Unanswered questions / Insights (TX-460)

Shows a practice what visitors ask that the website does not answer.

* **Flag:** the existing `[[NO_SOURCES]]` token (model declines or the context has nothing relevant) and `[[CALLBACK]]` (model offers a call back) mean "not answered". `ChatService` stores that as `answered = 0` on the assistant row in `wp_terax_ai_messages` (column `answered`, default 1, DB version 1.3.0). The prompt is unchanged. Only stored while conversation logging is on.
* **Grouping:** `Chat\Insights::group()` merges questions whose keywords (`KeywordRetriever::tokens`, stemmed, 5+ letters) overlap by at least 50 % (Jaccard). Pure PHP, no LLM call. Headline = newest wording, plus up to 3 other wordings, count and last date.
* **API:** `GET /insights?days=` returns `logging`, `stats { replies, unanswered }` and `groups`. `GET /dashboard` adds `stats.answers` for the Answer rate card.
* **Admin:** page **Insights**. "Add answer" opens `#/knowledge?add=<question>`, which appends the question to Extra information (unsaved) and focuses the box.

Tests: `php -d extension=mbstring tests/insights-test.php`.

## Better retrieval (embeddings)

`KeywordRetriever` needs no extra API and works with every vendor. For larger sites, implement `Knowledge\RetrieverInterface` (embeddings stored in a new table or an external vector DB) and return it from the `terax_ai_retriever` filter – nothing else changes.

## Licensing & plans

- `pro/config/plans.php` (Pro add-on) defines plans → `features` (bool) and `limits` (int, -1 = unlimited).
- Code calls `Features::can( 'managed_ai' )` / `Features::limit( 'messages_per_month' )`; the Pro add-on's `LicenseManager` answers through the filters. Without the add-on every site is on the free plan (no limits).
- `config/license.php` selects the backend client: `terax` (your server, see LICENSE-API.md) or `lemonsqueezy`.
- The key is validated on activation and re-checked daily (`terax_ai_daily` cron). If the server is unreachable, the plan stays active for `grace_days`.
- `Updater` delivers new versions to licensed sites through the normal WordPress update screen.

## Data

| Table | Purpose |
|---|---|
| `wp_terax_ai_chunks` | Indexed text (post_id 0 = "extra information" text). FULLTEXT index on MySQL/MariaDB. |
| `wp_terax_ai_conversations` | One row per chat session: page, salted IP hash, counts, booking clicks. |
| `wp_terax_ai_messages` | Questions/answers (only if logging is on), sources, token usage. |

Options: `terax_ai_settings` (keys encrypted with the site's auth salt), `terax_ai_license`, `terax_ai_usage`, `terax_ai_index_state`. Everything is removed on uninstall.

## Security notes

- Public endpoints: `/chat` and `/booking-click` only. They take no cookies (work with page caching) and are rate limited per IP hash.
- Client-sent history is re-validated (roles, length, max 10 turns).
- Assistant output is rendered by a tiny Markdown renderer that creates DOM nodes only (no `innerHTML`); links are limited to http(s)/mailto/tel.
- API/license keys are encrypted at rest and only masked hints are sent to the admin UI. If the WordPress salts change, keys must be re-entered.
