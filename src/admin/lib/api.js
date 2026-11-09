import { apiRoute } from "./apiUrl";

const cfg = window.teraxAiAdmin || {};

/**
 * Call the plugin REST API (authenticated with the WP REST nonce).
 */
export async function api(path, { method = "GET", body } = {}) {
  const res = await fetch(apiRoute(cfg.apiUrl, path), {
    method,
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": cfg.nonce,
    },
    credentials: "same-origin",
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error((data && data.message) || `Request failed (${res.status})`);
  }
  return data;
}

export const adminConfig = cfg;
