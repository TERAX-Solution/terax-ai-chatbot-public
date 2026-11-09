import { expect, test } from "@playwright/test";

test.use({ storageState: { cookies: [], origins: [] } });

test("on a phone the chat opens full screen and a message can be sent", async ({ page }) => {
  await page.goto("/");
  await page.locator(".tx-launcher").click();
  await page.locator(".tx-consent, .tx-input").first().waitFor();
  const consent = page.locator(".tx-consent button.tx-primary");
  if (await consent.isVisible()) await consent.click();

  const box = await page.locator(".tx-panel").boundingBox();
  const viewport = page.viewportSize();
  expect(box.width).toBeGreaterThanOrEqual(viewport.width - 2);
  expect(box.height).toBeGreaterThanOrEqual(viewport.height - 2);

  await page.locator(".tx-input textarea").fill("Termin vereinbaren");
  await page.locator(".tx-send").click();
  await expect(page.locator(".tx-msg.tx-assistant .tx-bubble").last()).toContainText("Termin");
});
