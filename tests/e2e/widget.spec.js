import { expect, test } from "@playwright/test";

// Visitors are not logged in. Selectors pierce the widget's open Shadow DOM.
test.use({ storageState: { cookies: [], origins: [] } });

async function openChat(page) {
  await page.goto("/");
  await page.locator(".tx-launcher").click();
  // Fresh visitor: the consent notice comes first (accepted once, remembered afterwards).
  await page.locator(".tx-consent, .tx-input").first().waitFor();
  const consent = page.locator(".tx-consent button.tx-primary");
  if (await consent.isVisible()) await consent.click();
  await expect(page.locator(".tx-input textarea")).toBeVisible();
}

const lastReply = (page) => page.locator(".tx-msg.tx-assistant .tx-bubble").last();

test("visitor asks a suggested question and gets an answer with a source link", async ({ page }) => {
  await openChat(page);
  await page.locator(".tx-suggestions button").first().click();
  await expect(lastReply(page)).toContainText("Auf unserer Seite");
  await expect(page.locator(".tx-sources a").first()).toBeVisible();
});

test("an unanswerable question gets no source links", async ({ page }) => {
  await openChat(page);
  await page.locator(".tx-input textarea").fill("Gibt es hier UFOs?");
  await page.locator(".tx-send").click();
  await expect(lastReply(page)).toContainText("leider nichts sagen");
  await expect(page.locator(".tx-sources")).toHaveCount(0);
  // The internal token never reaches the visitor.
  await expect(page.locator(".tx-messages")).not.toContainText("[[");
});

test("booking button opens the booking page, the chat survives a reload, New chat clears it", async ({ page, context }) => {
  // Keep the test offline: answer the external booking page ourselves.
  await context.route("https://example.com/**", (route) => route.fulfill({ contentType: "text/html", body: "<p>booking</p>" }));

  await openChat(page);
  await page.locator(".tx-input textarea").fill("Termin vereinbaren");
  await page.locator(".tx-send").click();

  const action = page.locator(".tx-action").last();
  await expect(action).toBeVisible();
  const popupPromise = context.waitForEvent("page");
  await action.click();
  const popup = await popupPromise;
  await expect(popup).toHaveURL(/example\.com\/e2e-termin/);
  await popup.close();

  // Same tab, new page load: the conversation is restored from the session.
  await page.reload();
  await expect(page.locator(".tx-messages")).toContainText("Termin vereinbaren");

  // The restart button sits right before the close button in the header.
  await page.locator(".tx-header .tx-icon-btn").nth(-2).click();
  await expect(page.locator(".tx-messages")).not.toContainText("Termin vereinbaren");
});
