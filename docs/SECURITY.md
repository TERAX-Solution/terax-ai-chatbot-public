# Security notes

## Stored secrets and `Encryption`

API keys (`terax_ai_settings` → `ai.api_keys.<provider>`) and the license key (`terax_ai_license`) are encrypted at rest by `TeraxAI\Libs\Utils\Encryption`.

| | |
| -- | -- |
| Cipher | `aes-256-cbc`, random IV stored in front of the ciphertext, base64-encoded |
| Key | `sha256( wp_salt( 'auth' ) )` — derived from the site's WordPress salts |
| Integrity | None (no MAC). Encryption protects against a leaked database dump or backup, not against someone who can read `wp-config.php` or run code on the site |
| Admin UI | Keys are never sent back; the UI only receives a masked hint (`••••abcd`) |
| Uninstall | Options, tables, transients and cron events are removed (`uninstall.php`) |

### Behaviour when OpenSSL is missing

If the PHP `openssl` extension is not available, `encrypt()` returns the value **unchanged** and `decrypt()` returns the stored value unchanged. The plugin keeps working, but keys are then stored in plain text in the database. The admin shows a dismissible notice (Dashboard and Settings → AI) while a key is stored this way; you can also check `php -m | grep openssl` on hosts you do not know.

### Behaviour when the salts change

Changing the salts in `wp-config.php`, or copying the database to another site, makes stored keys undecryptable. `decrypt()` then returns an empty string (the key counts as missing, so no request with a garbage key is sent), and the admin shows "Please re-enter the API key" on the Dashboard and in Settings → AI. Re-enter the API keys and re-activate the license in the admin.

Tests: `tests/encryption-test.php`.

## Public endpoints

- `POST /terax-ai/v1/chat` and `POST /terax-ai/v1/booking-click` are public. `/chat` validates input types, enforces `privacy.max_message_length`, rate-limits per visitor IP (see `privacy.trusted_proxy_header` when behind Cloudflare or another proxy) and enforces the monthly plan quota before calling a provider. Provider errors are never returned to visitors.
- All other routes require `manage_options` and a valid `wp_rest` nonce (WordPress core drops the user when the nonce is missing, so the permission callback fails with 401).
