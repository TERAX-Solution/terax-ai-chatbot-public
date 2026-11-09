import { chromium, request } from "@playwright/test";
import { mkdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { AUTH_DIR, BASE_URL, STATE_FILE, TOKEN, wp } from "./helpers.js";

/**
 * Prepares the site for the suite (no WP-CLI needed, everything goes through the REST API):
 * log in via the e2e-login mu-plugin, remember the settings we are about to change, set a fake API key,
 * a booking link and a known assistant name, publish one fixture page and wait until it is indexed.
 * The mock-llm mu-plugin answers the AI calls. global-teardown puts everything back.
 */
export default async function globalSetup() {
  mkdirSync(AUTH_DIR, { recursive: true });

  const api = await request.newContext({ baseURL: BASE_URL, extraHTTPHeaders: { "X-Terax-E2E": TOKEN } });
  const login = await api.get("/?terax_e2e_login=1");
  if (!login.ok()) {
    throw new Error(`E2E login failed (${login.status()}). Are the mu-plugins in tests/e2e/mu-plugins installed and TERAX_E2E_TOKEN defined in wp-config.php?`);
  }
  await api.storageState({ path: join(AUTH_DIR, "admin.json") });
  await api.get("/?terax_e2e_mock=on"); // The mock LLM answers only while this flag is on.

  // The REST nonce is printed into the plugin's admin page.
  const browser = await chromium.launch({ executablePath: process.env.E2E_CHROMIUM || undefined });
  const context = await browser.newContext({ baseURL: BASE_URL, storageState: join(AUTH_DIR, "admin.json") });
  const page = await context.newPage();
  await page.goto("/wp-admin/admin.php?page=terax-ai-assistant");
  const nonce = await page.evaluate(() => window.teraxAiAdmin && window.teraxAiAdmin.nonce);
  await browser.close();
  if (!nonce) throw new Error("Could not read the REST nonce: the Terax AI admin page did not load (plugin active?).");

  const ns = "/terax-ai/v1";
  const before = (await wp(api, nonce, `${ns}/settings`)).settings;
  const hadKey = Boolean(before.ai.api_keys && before.ai.api_keys.anthropic);
  // Conversations created by the suite have a higher id than anything that exists now.
  const existing = await wp(api, nonce, `${ns}/conversations?per_page=100`);
  const lastConversationId = Math.max(0, ...(existing.items || []).map((c) => Number(c.id)));

  await wp(api, nonce, `${ns}/settings`, {
    method: "POST",
    data: {
      ai: { mode: "byok", provider: "anthropic", api_keys: { anthropic: "e2e-fake-key" } },
      assistant: { enabled: true, name: "E2E Assistent", suggestions: ["Öffnungszeiten?"] },
      booking: { enabled: true, provider: "external_link", preset: "custom", url: "https://example.com/e2e-termin", label: "Termin buchen", open_in: "new_tab" },
      privacy: { log_conversations: true, rate_limit_per_hour: 1000 },
    },
  });

  const page2 = await wp(api, nonce, "/wp/v2/pages", {
    method: "POST",
    data: {
      title: "E2E Öffnungszeiten",
      status: "publish",
      content: "<p>Unsere Öffnungszeiten: Montag bis Freitag von 8 bis 18 Uhr, Samstag nach Vereinbarung.</p>",
    },
  });

  await wp(api, nonce, `${ns}/knowledge/rebuild`, { method: "POST" });
  for (let i = 0; i < 60; i++) {
    const st = await wp(api, nonce, `${ns}/knowledge/status`); // Polling also processes the next batch.
    if (st.status !== "running") break;
    await new Promise((r) => setTimeout(r, 1000));
  }

  const keep = { assistant: { name: before.assistant.name, suggestions: before.assistant.suggestions }, booking: before.booking, ai: { mode: before.ai.mode, provider: before.ai.provider }, privacy: { log_conversations: before.privacy.log_conversations, rate_limit_per_hour: before.privacy.rate_limit_per_hour } };
  writeFileSync(STATE_FILE, JSON.stringify({ nonce, pageId: page2.id, hadKey, lastConversationId, original: keep }, null, 2));
  await api.dispose();
}
