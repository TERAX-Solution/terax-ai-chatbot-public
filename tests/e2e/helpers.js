import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

// Local overrides (not committed). Loaded here too: spec files may import this module before the config has run.
try {
  process.loadEnvFile(join(here, ".env.local"));
} catch (e) {
  // No file: defaults for wp-env.
}

export const AUTH_DIR = join(here, ".auth");
export const STATE_FILE = join(AUTH_DIR, "state.json");

export const TOKEN = process.env.E2E_TOKEN || "e2e-wp-env-token";
export const BASE_URL = process.env.E2E_BASE_URL || "http://localhost:8889";

/** What global-setup remembered: REST nonce, the fixture page id, the settings to restore. */
export function readState() {
  return JSON.parse(readFileSync(STATE_FILE, "utf8"));
}

/**
 * REST call as the logged-in administrator. Uses ?rest_route= so it works with or without pretty permalinks.
 *
 * @param {import("@playwright/test").APIRequestContext} request Request context holding the admin cookie.
 * @param {string} nonce REST nonce.
 * @param {string} route e.g. "/terax-ai/v1/settings".
 * @param {{method?: string, data?: object}} [opts] Options.
 */
export async function wp(request, nonce, route, { method = "GET", data } = {}) {
  const [path, query] = route.split("?");
  const res = await request.fetch(`${BASE_URL}/?rest_route=${encodeURIComponent(path)}${query ? `&${query}` : ""}`, {
    method,
    data,
    headers: { "X-WP-Nonce": nonce },
  });
  if (!res.ok()) throw new Error(`${method} ${route} -> ${res.status()} ${await res.text()}`);
  return res.json();
}
