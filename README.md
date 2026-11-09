# TERAX AI Assistant

[![CI](https://github.com/TERAX-Solution/terax-ai-assistant/actions/workflows/ci.yml/badge.svg)](https://github.com/TERAX-Solution/terax-ai-assistant/actions/workflows/ci.yml)

A WordPress plugin that adds an AI chat assistant to a website. It answers visitor questions **only from the site's own pages and posts** and hands visitors off to the practice's appointment booking (Dr. Flex, Doctolib, Calendly …).

It is built on [prappo/wordpress-plugin-boilerplate](https://github.com/prappo/wordpress-plugin-boilerplate) (React + Vite admin, PHP REST backend) and designed to be sold as a subscription.

| | |
|---|---|
| **LLMs** | Anthropic Claude, OpenAI GPT, Google Gemini, Mistral (EU), any OpenAI-compatible API (Groq, OpenRouter, Ollama …), or **TERAX Managed AI** (proxy, no client key) |
| **Knowledge** | Pages/posts auto-indexed on save; Gutenberg, classic & Elementor; extra FAQ text; MySQL FULLTEXT + stem matching |
| **Booking** | Booking button / popup for any external booking URL; pluggable provider interface for live booking later |
| **Licensing** | Plans & feature gates in one config file; Lemon Squeezy or your own license server; private auto-updates |
| **GDPR** | Consent notice, no raw IPs (salted hash), configurable retention with daily purge, logging can be turned off, keys encrypted at rest |
| **Widget** | Preact + Shadow DOM (~10 KB gzip): theme CSS can't break it, it can't break the theme; works with full-page caching |

## Quick start (development)

Requirements: PHP 8.1+ (`mysqli`, `mbstring`, `openssl`, `intl`), Node.js 20+ with npm, Composer 2, and a local WordPress ([LocalWP](https://localwp.com) or wp-env).

```bash
npm install
composer dump-autoload -o     # creates vendor/autoload.php; no runtime composer dependencies
npm run build                 # production assets → assets/*/dist
npm run dev                   # Vite dev servers (HMR) for admin + widget
npm run release               # → release/terax-ai-assistant-<version>.zip (builds assets; needs no Composer)
```

1. Link or copy the folder into `wp-content/plugins/terax-ai-assistant` (see the OS notes below).
2. Add the `wp-config.php` constants below, **before** the `WP_DEBUG` block that LocalWP generates, otherwise `WP_DEBUG` stays `false`.
3. Activate the plugin, then open **AI Assistant → Settings → AI model**, enter a **development** API key, click **Test connection** and then **Save**. Test connection works with an unsaved key; the chat only works after saving. Never commit keys.
4. Create a few demo pages and click **Knowledge → Rebuild index**. With a handful of pages the rebuild finishes almost instantly, so there is little visual feedback.
5. For the **Book appointment** button, set a booking URL in **Settings → Booking**. Without a URL the assistant answers that online booking is unavailable.

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );                    // wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );
define( 'TERAX_AI_DEV_PLAN', 'pro' );              // Force a plan without a license server
define( 'TERAX_AI_LICENSE_SERVER', 'https://…' );  // Override license server
define( 'TERAX_AI_PROXY_URL', 'https://…' );       // Override managed-AI proxy
```

### Hot reload

`npm run dev` starts the widget (`:5173`) and admin (`:5174`) dev servers. While it runs, the plugin loads assets from them. Edit `src/frontend/widget.css` and reload the front-end to see the change without a build.

- **LocalWP: set Router mode to `localhost`** (☰ → Preferences → Advanced → Router mode, then restart the site). With the default `*.local` domain, Vite blocks the requests with a CORS error and the admin pages stay blank.
- After stopping `npm run dev`, run `npm run build` again. A stale `assets/*/dist/vite-dev-server.json` makes the plugin keep looking for the dev server.

### Windows notes

- **Symlink:** `ln -s` / `mklink /D` needs admin rights. A junction does not: `mklink /J "<site>\app\public\wp-content\plugins\terax-ai-assistant" "<path to repo>"`.
- **Composer with LocalWP's PHP:** LocalWP's `php.exe` has no `php.ini` outside a site, so Composer fails with "openssl extension is required". Pass the extensions explicitly:
  `php.exe -d extension_dir=<php dir>\ext -d extension=openssl -d extension=mbstring composer.phar dump-autoload -o`.
  PHP is in `%APPDATA%\Local\lightning-services\php-<version>\bin\win32` (or `win64`, depending on the PHP version).
- **WP-CLI:** use LocalWP's **Open site shell**; it has `wp` configured. `wp db query` does not work from a normal terminal because LocalWP does not use port 3306. Multi-line or non-ASCII (`ö`) content is easier through `wp eval-file script.php` than through the Windows command line.

### Troubleshooting

- Chat answers "Sorry, I can't answer right now": check `wp-content/debug.log`. `No API key configured for this provider` means the key was not saved for the **selected** provider.
- Admin page is blank while `npm run dev` runs: see the Router mode note above and the browser console.

## Project layout

```
terax-ai-assistant.php     Plugin header, activation hooks
plugin.php                 Main class – wires modules together
config/
  plugin.php               Distribution settings (proxy URL, branding)
  providers.php            ← LLM providers registry (add new models here)
  plans.php                ← Subscription plans, features & limits
  license.php              ← License backend (TERAX server / Lemon Squeezy)
  settings.php             Default per-site settings
includes/
  AI/                      Provider interface, DTOs, Anthropic/OpenAI/Gemini/Mistral/Compatible/TeraxProxy
  Knowledge/               Extractor → Chunker → ChunkRepository, Indexer (hooks + batches), Retriever
  Chat/                    ChatService pipeline, PromptBuilder, RateLimiter, UsageMeter, ConversationRepository
  Booking/                 BookingProviderInterface, ExternalLink, registry
  Cli/                     WP-CLI commands (loaded only under WP-CLI)
  Support/Features.php     feature gates and limits (free defaults; the Pro add-on changes them by filters)
  (pro/ = separate plugin "TERAX AI Assistant Pro": LicenseManager, clients, Updater, Managed AI)
  Controllers/ Routes/     REST API  →  /wp-json/terax-ai/v1/…
  Assets/ Admin/ Core/     Enqueueing, menu, install/cron
database/Migrations/       dbDelta schema (chunks, conversations, messages)
src/admin/                 React admin app (Dashboard, Settings, Knowledge, Conversations, License)
src/frontend/              Preact chat widget (Shadow DOM)
docs/                      Architecture, extension guide, proxy & license API contracts
```

See **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** for the request flow and how to extend each module, and **[docs/LICENSE-API.md](docs/LICENSE-API.md)** / **[docs/PROXY-API.md](docs/PROXY-API.md)** for the server contracts you will implement on the TERAX side.

## Development

### Demo content (WP-CLI)

The `terax-ai` WP-CLI command is only registered when WP-CLI is running, never on normal web requests.

```bash
wp terax-ai seed-demo          # demo pages + settings, then rebuilds the index
wp terax-ai seed-demo --reset  # delete the previously seeded pages first
```

`seed-demo` creates six German pages for a fictional practice ("Zahnzentrum Demo": PZR with a price of 90–120 €, Implantate, Kontakt & Öffnungszeiten, Angstpatienten, Team, Notfall), fills **Settings → Knowledge → Extra information**, sets the demo booking URL `https://example.com/booking`, rebuilds the knowledge index and prints how many pages and chunks it created.

- The pages carry the post meta `_terax_ai_demo = 1`. `--reset` deletes only those pages, never your own content.
- Without `--reset`, the command stops with an error if demo pages already exist, so you never get duplicates.
- It overwrites the **Extra information** text and the booking URL in the settings.
- With LocalWP, run it from the site's **Open site shell**.

### Coding standards (PHPCS)

PHP follows the WordPress Coding Standards (`phpcs.xml.dist`, PHP 7.4+ compatibility, prefixes `terax_ai` / `TeraxAI` / `TERAX_AI`). CI fails on errors.

```bash
composer install       # also registers the standards with PHPCS
composer lint          # check
composer lint:fix      # fix what can be fixed automatically
```

On Windows with `core.autocrlf=true` the files are CRLF in your working copy and PHPCS reports the line endings of every file. The repository itself is LF, so ignore that check locally: `composer lint -- --exclude=Generic.Files.LineEndings`.

Do not rename public hooks or REST routes to satisfy a sniff: add a targeted `// phpcs:ignore Sniff.Name -- reason` instead.

### Page builders

The knowledge index reads Gutenberg, classic content and Elementor (rendered by Elementor). Builders that keep their layout as shortcodes (Divi, WPBakery, Avada/Fusion and similar) are read by removing the shortcode tags and keeping the text inside them plus the text attributes `title`, `heading`, `sub_heading`, `subheading`, `subtitle` and `label` (an accordion item's title is the FAQ question). Shortcodes are never run while indexing. Add attributes with the filter `terax_ai_shortcode_text_attributes`; for any other builder, return its text from the filter `terax_ai_post_content`.

Status: covered by tests with sample content written by hand in the syntax of Divi, WPBakery and Avada (`tests/php/fixtures/`). Not yet tried with the real builders installed, and Elementor's rendering inside cron / REST requests is not verified.

### Tests

| Kind | Command | What it needs |
| -- | -- | -- |
| Standalone PHP / Node scripts (no WordPress) | `php tests/<name>-test.php`, `node tests/<name>-test.mjs` | PHP, Node |
| PHPUnit with the WordPress test suite (`tests/php/`, TX-428) | `npm run test:php` | Docker Desktop running and `composer install` once |
| Browser tests (`tests/e2e/`) | `npm run test:e2e` | see `tests/e2e/README.md` |

PHPUnit runs inside wp-env: `composer install && npx @wordpress/env start && npm run test:php`. Tests extend `TeraxAI\Tests\TestCase` (fresh default settings and the HTTP mock for every test) and mirror the folders of `includes/` (`tests/php/Support/SettingsTest.php` tests `includes/Support/Settings.php`). `TeraxAI\Tests\Mocks\HttpMock` answers calls to any host (`HttpMock::respond( 'api.anthropic.com', array( ... ), 200 )`), records the requests (`HttpMock::requests()`), and fails calls to hosts without a mock: no test reaches the network. CI runs PHPUnit and the browser tests on PHP 8.1 with WordPress 6.2 and the latest version, and on PHP 8.3 with the latest version. wp-env cannot build a PHP 7.4 container (its Debian base is end of life): PHP 7.4 is covered by the standalone tests (they run on 7.4 and 8.3) and by the PHPCompatibility check in `composer lint`.

### Releases

Version, changelog and the release ZIP: see [docs/RELEASE.md](docs/RELEASE.md) (`npm run version:bump -- 1.1.0`, then tag `v1.1.0`).

### Translations (PHP strings)

Text domain `terax-ai-assistant`, files in `languages/`. German is formal ("Sie"). This covers PHP-generated texts only; the React admin and the widget are separate tasks.

1. Wrap every new user-visible PHP string in `__( '…', 'terax-ai-assistant' )` (or `esc_html__`).
2. Regenerate the template: `npm run i18n` → `languages/terax-ai-assistant.pot`. It needs WP-CLI; with LocalWP use the site shell, or the `wp-cli.phar` that ships with Local (the PHP flags are in the Windows notes above).
3. Add the new `msgid`s to `languages/terax-ai-assistant-de_DE.po`, keeping the glossary: Assistent, Wissensdatenbank, Terminbuchung, Lizenz, Unterhaltung.
4. Compile: `wp i18n make-mo languages`. Commit the `.pot`, `.po` and `.mo`; the release ZIP ships `languages/**`.
5. To check: set **Settings → General → Site Language** to Deutsch and open AI Assistant.

## Deviations from the boilerplate (and why)

- **`$wpdb` instead of `prappo/wp-eloquent`.** The ORM opens its own PDO connection from `DB_HOST`, which breaks on common hosting formats (`localhost:/path/mysql.sock`, `host:port`) and on SQLite. For a product installed on many client hosts, plain `$wpdb` + `dbDelta` is the safer choice. No runtime Composer packages remain.
- **Widget uses Preact, not React,** to keep visitor page weight ~10 KB gzip.
- **Tailwind is scoped** to `#terax-ai-admin` with preflight disabled, so it doesn't restyle the WP admin.
- **Config is passed with `wp_add_inline_script` + JSON** (not `wp_localize_script`) so numbers/booleans/null keep their types; `libs/assets.php` now only marks the bundle itself as `type="module"`.
- Demo code (accounts, inbox, blocks, storybook, marketing site) removed; `npm run rename` removed (already renamed). The boilerplate's scaffolding CLI (`npm/packages/`), unused Radix UI packages, ESLint setup and the unreferenced Inter font files were removed too.
