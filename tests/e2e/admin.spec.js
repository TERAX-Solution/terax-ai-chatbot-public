import { expect, test } from "@playwright/test";

const ADMIN = "/wp-admin/admin.php?page=terax-ai-assistant";

const ROUTES = ["dashboard", "settings/ai", "settings/assistant", "settings/booking", "settings/privacy", "knowledge", "conversations", "requests", "insights", "license"];

/**
 * Collects JavaScript errors of the page. Console errors count when they come from the plugin (script or REST call)
 * or have no source at all; noise of other plugins and of WordPress itself is ignored.
 */
function watchErrors(page) {
  const errors = [];
  page.on("pageerror", (e) => errors.push(`pageerror: ${e.message}`));
  page.on("console", (m) => {
    if (m.type() !== "error") return;
    const url = m.location().url || "";
    if (url && !/terax/i.test(url)) return;
    errors.push(m.text());
  });
  return errors;
}

for (const route of ROUTES) {
  test(`admin page "${route}" loads without console errors`, async ({ page }) => {
    const errors = watchErrors(page);
    await page.goto(`${ADMIN}#/${route}`);
    await expect(page.locator("main h1").first()).toBeVisible();
    await page.waitForLoadState("networkidle");
    expect(errors, errors.join("\n")).toEqual([]);
  });
}

// Sites with plain permalinks reach the API as /?rest_route=/terax-ai/v1/... : routes with a query string used to 404 there.
// The page config is rewritten before the admin app starts, so this also runs on a site with pretty permalinks.
for (const route of ["conversations", "requests", "insights"]) {
  test(`admin page "${route}" works with plain permalinks`, async ({ page }) => {
    await page.addInitScript(() => {
      let config;
      Object.defineProperty(window, "teraxAiAdmin", {
        configurable: true,
        get: () => config,
        set: (value) => {
          config = { ...value, apiUrl: `${location.origin}/?rest_route=/terax-ai/v1/` };
        },
      });
    });
    const errors = watchErrors(page);
    await page.goto(`${ADMIN}#/${route}`);
    await expect(page.locator("main h1").first()).toBeVisible();
    await page.waitForLoadState("networkidle");
    expect(errors, errors.join("\n")).toEqual([]);
  });
}

test("the error watcher notices a console error, so the page checks above can fail", async ({ page }) => {
  const errors = watchErrors(page);
  await page.goto(`${ADMIN}#/dashboard`);
  await expect(page.locator("main h1").first()).toBeVisible();
  await page.evaluate(() => console.error("simulated plugin error"));
  await expect.poll(() => errors.length).toBeGreaterThan(0);
});

test("assistant name is saved and survives a reload", async ({ page }) => {
  await page.goto(`${ADMIN}#/settings/assistant`);
  await page.locator("#name").fill("E2E Umbenannt");
  const saved = page.waitForResponse((r) => r.url().includes("settings") && r.request().method() === "POST");
  await page.getByRole("button", { name: /Save changes|Änderungen speichern/ }).click();
  expect((await saved).ok()).toBe(true);

  await page.reload();
  await expect(page.locator("#name")).toHaveValue("E2E Umbenannt");
});

test("knowledge search finds the fixture page", async ({ page }) => {
  await page.goto(`${ADMIN}#/knowledge`);
  await page.locator("main form input").first().fill("Öffnungszeiten");
  await page.locator("main form button[type=submit]").click();
  await expect(page.locator("main ol")).toContainText("E2E Öffnungszeiten");
});
