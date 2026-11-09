import { defineConfig } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

// Optional local overrides (not committed): E2E_BASE_URL, E2E_TOKEN, E2E_CHROMIUM.
try {
  process.loadEnvFile(join(here, ".env.local"));
} catch (e) {
  // No file: use the defaults below (wp-env, see .wp-env.json).
}

const executablePath = process.env.E2E_CHROMIUM || undefined;

export default defineConfig({
  testDir: here,
  testMatch: "**/*.spec.js",
  globalSetup: join(here, "global-setup.js"),
  globalTeardown: join(here, "global-teardown.js"),
  // One site, shared state (settings, index): tests run one after the other.
  workers: 1,
  fullyParallel: false,
  timeout: 30_000,
  expect: { timeout: 8_000 },
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["list"], ["html", { open: "never", outputFolder: join(here, "report") }]] : [["list"]],
  outputDir: join(here, "results"),
  use: {
    baseURL: process.env.E2E_BASE_URL || "http://localhost:8889",
    storageState: join(here, ".auth", "admin.json"),
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    launchOptions: executablePath ? { executablePath } : {},
  },
  projects: [
    { name: "desktop", testIgnore: /\.mobile\.spec\.js$/, use: { viewport: { width: 1280, height: 800 } } },
    {
      // iPhone-sized touch screen (Chromium emulation, so one browser is enough in CI).
      name: "mobile",
      testMatch: /\.mobile\.spec\.js$/,
      use: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true },
    },
  ],
});
