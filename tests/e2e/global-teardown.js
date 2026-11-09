import { request } from "@playwright/test";
import { join } from "node:path";
import { AUTH_DIR, BASE_URL, TOKEN, readState, wp } from "./helpers.js";

/** Puts the site back: original settings, no fake key (if there was none), fixture page and test conversations gone. */
export default async function globalTeardown() {
  let state;
  try {
    state = readState();
  } catch (e) {
    return; // Setup never got that far.
  }
  const api = await request.newContext({ baseURL: BASE_URL, storageState: join(AUTH_DIR, "admin.json"), extraHTTPHeaders: { "X-Terax-E2E": TOKEN } });
  await api.get("/?terax_e2e_mock=off");
  const ns = "/terax-ai/v1";
  const { nonce } = state;

  const ai = { ...state.original.ai };
  if (!state.hadKey) ai.api_keys = { anthropic: "__delete__" };
  await wp(api, nonce, `${ns}/settings`, { method: "POST", data: { ...state.original, ai } });

  await wp(api, nonce, `/wp/v2/pages/${state.pageId}?force=true`, { method: "DELETE" });
  await wp(api, nonce, `${ns}/knowledge/rebuild`, { method: "POST" });

  // Remove only the conversations the suite created (higher id than at setup); a shared dev site keeps its own.
  const list = await wp(api, nonce, `${ns}/conversations?per_page=100`);
  for (const c of list.items || []) {
    if (Number(c.id) > state.lastConversationId) {
      await wp(api, nonce, `${ns}/conversations/${c.id}/delete`, { method: "POST" });
    }
  }
  await api.dispose();
}
