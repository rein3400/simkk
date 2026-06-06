import { defineConfig, devices } from "@playwright/test";

// Separate config dedicated to static HTML previews in outputs/sim-kk-ui-previews/.
// Kept distinct from playwright.config.ts (which targets the Vite dev server for
// the Vue app) so the existing smoke.spec.ts continues to run unchanged.
export default defineConfig({
  testDir: "./tests",
  testMatch: /previews-smoke\.spec\.ts$/,
  timeout: 30000,
  expect: { timeout: 5000 },
  fullyParallel: true,
  workers: 1,
  retries: 0,
  reporter: [["list"]],
  use: {
    baseURL: process.env.STATIC_PREVIEW_BASE_URL || "http://127.0.0.1:4174",
    headless: true,
    viewport: { width: 1280, height: 800 },
    trace: "retain-on-failure",
  },
  webServer: {
    command: "node tests/static-server/serve.mjs",
    url: "http://127.0.0.1:4174",
    reuseExistingServer: !process.env.CI,
    timeout: 15000,
    stdout: "ignore",
    stderr: "pipe",
  },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
  ],
});
