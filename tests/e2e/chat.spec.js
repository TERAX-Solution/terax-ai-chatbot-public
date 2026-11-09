import { expect, test } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { join } from "node:path";
import { AUTH_DIR, BASE_URL, TOKEN, readState, wp } from "./helpers.js";

/**
 * TX-430: the chat pipeline (ChatService behind POST /chat) and the permission checks of the admin routes.
 * The fake model (mu-plugins/mock-llm.php) answers, fails on demand and records every request it receives.
 * Every chat uses its own fake visitor IP (X-Forwarded-For, the plugin is set to trust it while this file runs),
 * so the hourly rate limit of one test never counts against another. Everything the tests change is put back at the end.
 */

const NS = "/terax-ai/v1";
const OPTIONS = ["terax_ai_last_error", "terax_ai_provider_health"];

let admin; // Administrator (cookie + nonce).
let visitor; // Nobody logged in.
let nonce;
let before; // Settings and options to restore.
const created = { users: [], pages: [] };
let fixture; // Page with words that exist nowhere else on the site, so it is always the best match.
const QUESTION = "Wo findet die Zebrafinkenstation Quellenprüfung statt?";

test.describe.configure({ mode: "serial" });

const randomIp = () => `10.${randomBytes(1)[0]}.${randomBytes(1)[0]}.${1 + (randomBytes(1)[0] % 250)}`;
const session = () => `e2e${randomBytes(6).toString("hex")}`;
const control = async (query) => (await admin.get(`/?${query}`)).json();

async function chat(data, { ip = randomIp() } = {}) {
  const res = await visitor.post(`${BASE_URL}/?rest_route=${NS}/chat`, { data, headers: { "X-Forwarded-For": ip } });
  return { status: res.status(), body: await res.json(), ip };
}
const ask = (message, extra = {}, opts) => chat({ message, session_id: session(), ...extra }, opts);

const saveSettings = (data) => wp(admin, nonce, `${NS}/settings`, { method: "POST", data });
const lastRequest = async () => (await control("terax_e2e_captured=get")).requests.at(-1);

test.beforeAll(async ({ playwright }) => {
  nonce = readState().nonce;
  admin = await playwright.request.newContext({ baseURL: BASE_URL, storageState: join(AUTH_DIR, "admin.json"), extraHTTPHeaders: { "X-Terax-E2E": TOKEN } });
  visitor = await playwright.request.newContext({ baseURL: BASE_URL });

  const settings = (await wp(admin, nonce, `${NS}/settings`)).settings;
  before = { privacy: { trusted_proxy_header: settings.privacy.trusted_proxy_header, log_conversations: settings.privacy.log_conversations, rate_limit_per_hour: settings.privacy.rate_limit_per_hour }, booking: { enabled: settings.booking.enabled }, assistant: { phone: settings.assistant.phone } };
  before.options = {};
  for (const name of OPTIONS) before.options[name] = await control(`terax_e2e_option=${name}&op=get`);

  await saveSettings({ privacy: { trusted_proxy_header: "xff", log_conversations: true, rate_limit_per_hour: 1000 }, booking: { enabled: true } });

  // Saving a published page indexes it at once.
  fixture = await wp(admin, nonce, "/wp/v2/pages", {
    method: "POST",
    data: { title: "E2E Zebrafinkenstation", status: "publish", content: "<p>Die Zebrafinkenstation Quellenprüfung findet im Walnussweg statt. Die Anmeldung für die Zebrafinkenstation erfolgt telefonisch.</p>" },
  });
  created.pages.push(fixture.id);
});

test.afterAll(async () => {
  await control("terax_e2e_mock=on");
  await control("terax_e2e_quota=off");
  await control("terax_e2e_captured=clear");
  await saveSettings(before).catch(() => {});
  for (const name of OPTIONS) {
    const o = before.options[name];
    if (o.exists) await admin.post(`/?terax_e2e_option=${name}&op=set`, { data: { value: o.value } });
    else await control(`terax_e2e_option=${name}&op=delete`);
  }
  for (const id of created.pages) await wp(admin, nonce, `/wp/v2/pages/${id}?force=true`, { method: "DELETE" }).catch(() => {});
  for (const id of created.users) await wp(admin, nonce, `/wp/v2/users/${id}?force=true&reassign=1`, { method: "DELETE" }).catch(() => {});
  await admin.dispose();
  await visitor.dispose();
});

test("1. a question is answered from the site content, with the right source link", async () => {
  const { status, body } = await ask(QUESTION);
  expect(status).toBe(200);
  expect(body.reply).toContain("Auf unserer Seite");
  expect(body.reply).not.toContain("[[");
  expect(body.sources.map((s) => s.url)).toContain(fixture.link);
  expect(body.actions).toEqual([]);
});

test("2. [[BOOKING]] is removed from the reply and becomes the booking button", async () => {
  const { status, body } = await ask("Ich brauche einen Termin");
  expect(status).toBe(200);
  expect(body.reply).not.toContain("[[");
  expect(body.reply).toContain("Termin");
  expect(body.actions[0]).toMatchObject({ type: "link", url: "https://example.com/e2e-termin", label: "Termin buchen" });
  expect(body.sources).toEqual([]); // A booking reply needs no sources underneath.
});

test("3. the model forgets the token, the word 'Termin' still gives the booking button", async () => {
  const { body } = await ask("Termin bitte, notoken");
  expect(body.reply).not.toContain("[[");
  expect(body.actions[0]).toMatchObject({ type: "link", url: "https://example.com/e2e-termin" });
});

test("4. booking switched off: no booking button", async () => {
  await saveSettings({ booking: { enabled: false } });
  try {
    const { status, body } = await ask("Ich brauche einen Termin");
    expect(status).toBe(200);
    expect(body.actions).toEqual([]);
  } finally {
    await saveSettings({ booking: { enabled: true } });
  }
});

test("5. an empty message, a missing session or a non-text message is rejected with 400", async () => {
  for (const data of [{ message: "", session_id: "abc" }, { message: "   ", session_id: "abc" }, { message: "Hallo" }, { message: "Hallo", session_id: "" }, {}, { message: ["x"], session_id: "abc" }]) {
    const { status, body } = await chat(data);
    expect(status, JSON.stringify(data)).toBe(400);
    expect(body.code).toBe("terax_ai_invalid");
  }
});

test("6. with a limit of 2 per hour the third message of the same visitor gets 429", async () => {
  await saveSettings({ privacy: { rate_limit_per_hour: 2 } });
  try {
    const ip = randomIp();
    expect((await ask("Öffnungszeiten?", {}, { ip })).status).toBe(200);
    expect((await ask("Öffnungszeiten?", {}, { ip })).status).toBe(200);
    const third = await ask("Öffnungszeiten?", {}, { ip });
    expect(third.status).toBe(429);
    expect(third.body.code).toBe("terax_ai_rate_limited");
    // Another visitor is not affected.
    expect((await ask("Öffnungszeiten?")).status).toBe(200);
  } finally {
    await saveSettings({ privacy: { rate_limit_per_hour: 1000 } });
  }
});

test("7. monthly quota used up: 429 with the polite fallback text and the phone number", async () => {
  await saveSettings({ assistant: { phone: "+49 30 1234567" } });
  await control("terax_e2e_quota=on");
  try {
    const { status, body } = await ask("Öffnungszeiten?");
    expect(status).toBe(429);
    expect(body.code).toBe("terax_ai_quota");
    expect(body.message).toContain("contact us directly");
    expect(body.message).toContain("+49 30 1234567");
  } finally {
    await control("terax_e2e_quota=off");
    await saveSettings({ assistant: { phone: before.assistant.phone } });
  }
  expect((await ask("Öffnungszeiten?")).status).toBe(200); // Back to normal.
});

test("8. the vendor fails with HTTP 500: visitor gets 502 and a fallback text, nothing of the vendor error, the admin sees it", async () => {
  await control("terax_e2e_mock=fail");
  try {
    const { status, body } = await ask("Öffnungszeiten?");
    expect(status).toBe(502);
    expect(body.code).toBe("terax_ai_provider");
    expect(body.message).toContain("can't answer right now");
    const raw = JSON.stringify(body);
    expect(raw).not.toContain("SECRET-VENDOR-ERROR");
    expect(raw).not.toContain("sk-e2e-leak");
    expect(raw).not.toContain("HTTP 500");

    const dashboard = await wp(admin, nonce, `${NS}/dashboard`);
    expect(dashboard.last_error.message).toContain("SECRET-VENDOR-ERROR");
    expect(dashboard.last_error.time).toBeGreaterThan(0);
  } finally {
    await control("terax_e2e_mock=on");
  }
});

test("9. conversation logging off: the conversation is counted, no message is stored", async () => {
  const newestId = async () => Math.max(0, ...((await wp(admin, nonce, `${NS}/conversations?per_page=100`)).items || []).map((c) => Number(c.id)));

  // Logging on: user message and reply are stored.
  let last = await newestId();
  await ask("Öffnungszeiten?");
  let id = await newestId();
  expect(id).toBeGreaterThan(last);
  expect((await wp(admin, nonce, `${NS}/conversations/${id}`)).messages.map((m) => m.role)).toEqual(["user", "assistant"]);

  // Logging off.
  await saveSettings({ privacy: { log_conversations: false } });
  try {
    last = await newestId();
    const { status } = await ask("Öffnungszeiten?");
    expect(status).toBe(200);
    id = await newestId();
    expect(id).toBeGreaterThan(last);
    const conversation = await wp(admin, nonce, `${NS}/conversations/${id}`);
    expect(conversation.messages).toEqual([]);
    expect(Number(conversation.message_count)).toBeGreaterThanOrEqual(1);
  } finally {
    await saveSettings({ privacy: { log_conversations: true } });
  }
});

test("10. a client history that starts with the assistant and has 20 turns: only the last 10 are used and the first is a user turn", async () => {
  await control("terax_e2e_captured=clear");
  const history = Array.from({ length: 20 }, (_, i) => ({ role: i % 2 === 0 ? "assistant" : "user", content: `turn-${i}` }));
  const { status } = await ask("Wie geht es Ihnen?", { history });
  expect(status).toBe(200);

  const { messages } = (await lastRequest()).body;
  // Last 10 turns are turn-10..turn-19. turn-10 is an assistant turn and goes, so 9 history turns plus the new question.
  expect(messages).toHaveLength(10);
  expect(messages[0]).toEqual({ role: "user", content: "turn-11" });
  expect(messages.at(-1)).toEqual({ role: "user", content: "Wie geht es Ihnen?" });
  expect(JSON.stringify(messages)).not.toContain("turn-9\"");
  expect(JSON.stringify(messages)).not.toContain("turn-10\"");
});

test("the request to the vendor carries the system prompt with the page text, the model and the token limit", async () => {
  await control("terax_e2e_captured=clear");
  await ask(QUESTION);
  const request = await lastRequest();
  expect(request.url).toBe("https://api.anthropic.com/v1/messages");
  expect(request.body.system).toContain("Zebrafinkenstation Quellenprüfung findet im Walnussweg statt");
  expect(request.body.messages.at(-1)).toEqual({ role: "user", content: QUESTION });
  expect(request.body.max_tokens).toBeGreaterThan(0);
  expect(request.body.model).toBeTruthy();
});

// ---- Admin routes need the manage_options capability. ----

const ADMIN_ROUTES = [
  ["GET", "/dashboard"],
  ["GET", "/settings"],
  ["POST", "/settings"],
  ["GET", "/providers"],
  ["GET", "/knowledge/status"],
  ["GET", "/knowledge/sources"],
  ["POST", "/knowledge/rebuild"],
  ["GET", "/conversations"],
  ["GET", "/conversations/export"],
  ["GET", "/insights"],
  ["GET", "/requests"],
  ["GET", "/license"],
];

test("admin routes refuse a visitor who is not logged in", async () => {
  for (const [method, route] of ADMIN_ROUTES) {
    const res = await visitor.fetch(`${BASE_URL}/?rest_route=${NS}${route}`, { method, data: method === "POST" ? {} : undefined });
    expect([401, 403], `${method} ${route} -> ${res.status()}`).toContain(res.status());
  }
});

test("admin routes refuse a logged-in subscriber", async ({ playwright }) => {
  const email = "e2e-subscriber@example.invalid";
  let id;
  try {
    // The password is random and never used: the suite logs in through the e2e-login mu-plugin.
    id = (await wp(admin, nonce, "/wp/v2/users", { method: "POST", data: { username: "e2e_subscriber", email, password: randomBytes(24).toString("hex"), roles: ["subscriber"] } })).id;
  } catch (e) {
    id = (await wp(admin, nonce, "/wp/v2/users?search=e2e_subscriber&context=edit"))[0].id; // Left over from an aborted run.
  }
  created.users.push(id);

  const sub = await playwright.request.newContext({ baseURL: BASE_URL, extraHTTPHeaders: { "X-Terax-E2E": TOKEN } });
  try {
    const login = await (await sub.get("/?terax_e2e_login=1&role=subscriber")).json();
    expect(login.nonce).toBeTruthy();
    for (const [method, route] of ADMIN_ROUTES) {
      const res = await sub.fetch(`${BASE_URL}/?rest_route=${NS}${route}`, { method, data: method === "POST" ? {} : undefined, headers: { "X-WP-Nonce": login.nonce } });
      expect(res.status(), `${method} ${route}`).toBe(403);
    }
  } finally {
    await sub.dispose();
  }
});
