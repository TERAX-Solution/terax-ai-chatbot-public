import { expect, test } from "@playwright/test";
import { join } from "node:path";
import { AUTH_DIR, BASE_URL, readState, wp } from "./helpers.js";

/**
 * TX-432: the parts of the knowledge index that need WordPress and a database (Indexer, ChunkRepository, retrieval).
 * The pure logic (Chunker, html_to_text, terms, stems) is tested without WordPress in tests/knowledge-test.php.
 * Everything goes through the REST API; the pages created here are removed again at the end.
 */

const NS = "/terax-ai/v1";
let api;
let nonce;
const created = [];
let originalKnowledge;

const search = async (query) => (await wp(api, nonce, `${NS}/knowledge/search`, { method: "POST", data: { query } })) || [];
const titles = (results) => results.map((r) => r.title);

async function newPage(data) {
  const page = await wp(api, nonce, "/wp/v2/pages", { method: "POST", data });
  created.push(page.id);
  return page;
}

async function rebuild() {
  await wp(api, nonce, `${NS}/knowledge/rebuild`, { method: "POST" });
  for (let i = 0; i < 60; i++) {
    const st = await wp(api, nonce, `${NS}/knowledge/status`); // Polling also processes the next batch.
    if (st.status !== "running") return;
    await new Promise((r) => setTimeout(r, 1000));
  }
  throw new Error("The index rebuild did not finish in 60 seconds.");
}

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ playwright }) => {
  nonce = readState().nonce;
  api = await playwright.request.newContext({ baseURL: BASE_URL, storageState: join(AUTH_DIR, "admin.json") });
  originalKnowledge = (await wp(api, nonce, `${NS}/settings`)).settings.knowledge;
});

test.afterAll(async () => {
  for (const id of created) {
    await wp(api, nonce, `/wp/v2/pages/${id}?force=true`, { method: "DELETE" }).catch(() => {});
  }
  await wp(api, nonce, `${NS}/settings`, { method: "POST", data: { knowledge: { exclude_ids: originalKnowledge.exclude_ids, custom_text: originalKnowledge.custom_text } } }).catch(() => {});
  await rebuild().catch(() => {});
  await api.dispose();
});

let pzr;
let implants;

test("saving a published page indexes it, and retrieval ranks the matching page first", async () => {
  pzr = await newPage({
    title: "E2E Sonderprophylaxe",
    status: "publish",
    content: "<p>Die Sonderprophylaxe kostet bei uns 89 Euro pro Sitzung. Sie entfernt Belag und Zahnstein.</p>",
  });
  implants = await newPage({
    title: "E2E Implantate",
    status: "publish",
    content: "<p>Zahnimplantate ersetzen fehlende Zähne dauerhaft. Die Beratung zu Implantaten ist kostenlos.</p>",
  });
  await newPage({ title: "E2E Anfahrt", status: "publish", content: "<p>So finden Sie uns: Musterstraße 12, Parkplätze im Hof.</p>" });

  // Only the distinctive word: common words ("Kosten") also match the demo content a real site has, and full-text ranking depends on it.
  const results = await search("Sonderprophylaxe");
  expect(titles(results)[0]).toBe("E2E Sonderprophylaxe");

  // A plural in the question finds the singular on the page (stemming).
  expect(titles(await search("Implantat"))).toContain("E2E Implantate");
});

test("drafts and password-protected pages are not indexed", async () => {
  await newPage({ title: "E2E Entwurf", status: "draft", content: "<p>Xylophonquartett im Entwurf.</p>" });
  await newPage({ title: "E2E Geschützt", status: "publish", password: "e2e-pass", content: "<p>Zeppelinhangar hinter dem Passwort.</p>" });

  expect(titles(await search("Xylophonquartett"))).not.toContain("E2E Entwurf");
  expect(titles(await search("Zeppelinhangar"))).not.toContain("E2E Geschützt");
});

test("moving a page to the trash removes it from the index", async () => {
  expect(titles(await search("Implantate"))).toContain("E2E Implantate");
  await wp(api, nonce, `/wp/v2/pages/${implants.id}`, { method: "DELETE" }); // Without force: trash.
  expect(titles(await search("Implantate"))).not.toContain("E2E Implantate");
});

test("a page in exclude_ids leaves the index after the rebuild that the setting triggers", async () => {
  expect(titles(await search("Sonderprophylaxe"))).toContain("E2E Sonderprophylaxe");
  await wp(api, nonce, `${NS}/settings`, { method: "POST", data: { knowledge: { exclude_ids: [pzr.id] } } });
  await rebuild();
  expect(titles(await search("Sonderprophylaxe"))).not.toContain("E2E Sonderprophylaxe");
});

test("the extra information chunk survives a full rebuild", async () => {
  await wp(api, nonce, `${NS}/settings`, { method: "POST", data: { knowledge: { custom_text: "Unsere Hausnummer lautet Vierhundertzwölf und der Hund heißt Bello." } } });
  expect((await search("Hausnummer Vierhundertzwölf")).length).toBeGreaterThan(0);

  await rebuild();
  expect((await search("Hausnummer Vierhundertzwölf")).length).toBeGreaterThan(0);
});
