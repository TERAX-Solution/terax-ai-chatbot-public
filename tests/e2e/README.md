# End-to-end tests (Playwright, TX-446)

Real browser, real WordPress, fake AI. Covers the widget (ask, source link, booking button, reload, new chat, phone
screen), the admin (every page loads without console errors, a setting is saved, Knowledge search), the knowledge index
(`indexer.spec.js`, TX-432) and the chat pipeline over REST (`chat.spec.js`, TX-430): source links, booking token and
keyword fallback, 400 validation, rate limit, monthly quota, vendor failure (no vendor text reaches the visitor), logging
off, history trimming, the request sent to the vendor, and the 401/403 checks of the admin routes.
What each provider class sends to its vendor is checked without WordPress in `tests/providers-test.php`.

```bash
npm run test:e2e
```

About a minute. Needs Chromium: `npx playwright install chromium` (CI also passes `--with-deps`).

## How it works

* `mu-plugins/e2e-login.php` logs in as the first administrator when the request carries the secret in the
  `X-Terax-E2E` header (no password anywhere). `mu-plugins/mock-llm.php` answers the Anthropic API calls locally
  (appointment → `[[BOOKING]]`, "ufo" → `[[NO_SOURCES]]`, anything else quotes the first source). Both do nothing unless
  `wp-config.php` defines `TERAX_E2E_TOKEN`; the mock additionally answers only while `global-setup.js` has it switched on.
  Extra switches for the chat tests (all need the header): `?terax_e2e_mock=fail` (the fake vendor answers HTTP 500),
  `?terax_e2e_captured=get|clear` (the requests the fake vendor received), `?terax_e2e_quota=on|off` (monthly limit 0),
  `?terax_e2e_login=1&role=subscriber` (log in as a subscriber, returns a REST nonce) and `?terax_e2e_option=NAME&op=get|set|delete`
  (only `terax_ai_last_error` and `terax_ai_provider_health`, so the suite can put them back).
  They are test files: never install them on a real site. They are not part of the release ZIP.
* `global-setup.js` (REST API only, no WP-CLI): logs in, remembers the settings it changes, sets a fake API key, a
  booking link and a known assistant name, publishes one fixture page, rebuilds the index.
  `global-teardown.js` puts all of it back and deletes the conversations the run created.
* An admin page fails the run when it logs a JavaScript error from the plugin (or an uncaught exception).
  Console noise from other plugins and WordPress itself is ignored.

## Where it runs

**CI** (`.github/workflows/ci.yml`, job `e2e`): wp-env in Docker. Uses `.wp-env.json` (tests site on port 8889, token
`e2e-wp-env-token`, mu-plugins mapped from `tests/e2e/mu-plugins`). Report and traces are uploaded when it fails.

**Locally with wp-env** (Docker Desktop must run): `npm run build && npx @wordpress/env start && npm run test:e2e`

**Locally on a Local (localwp) site:**

1. Copy `tests/e2e/mu-plugins/*.php` into the site's `wp-content/mu-plugins/`.
2. Add `define( 'TERAX_E2E_TOKEN', '<random string>' );` to its `wp-config.php`.
3. Create `tests/e2e/.env.local` (git-ignored):
   ```
   E2E_BASE_URL=http://localhost:10017
   E2E_TOKEN=<the same random string>
   E2E_CHROMIUM=<optional: path to a chrome.exe if the Playwright download does not match>
   ```
4. The chat must go through your own key, so the site needs a plan with BYOK (`free` or `basis`). A managed-only plan
   (for example `TERAX_AI_DEV_PLAN` = `praxis`) sends chats to the TERAX proxy and the mock never sees them.
   Remove the `define` and the mu-plugins when you do not need the suite any more.
