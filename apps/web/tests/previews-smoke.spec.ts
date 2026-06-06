import { expect, test, type Page } from "@playwright/test";

// Static HTML preview smoke tests.
// Each test loads a self-contained HTML file in outputs/sim-kk-ui-previews/
// via the static server configured in playwright.previews.config.ts.
// Tests are idempotent: each opens a fresh page, no shared state.

const SCREENSHOT_DIR = "test-results/previews";

async function gotoPreview(page: Page, file: string) {
  // Static server maps "/" to login.html; explicit files use root-relative URL.
  // Use domcontentloaded — external font/image CDNs are not required for smoke tests.
  await page.goto(`/${file}`, { waitUntil: "domcontentloaded" });
}

test.describe("login.html", () => {
  test("all 4 role chips render and click toggles active class", async ({ page }) => {
    await gotoPreview(page, "login.html");
    await expect(page).toHaveTitle(/Login Preview/);
    const chips = page.locator(".role-chip");
    await expect(chips).toHaveCount(4);

    // Default active = Kasir (bg-ink + text-cream classes from server-rendered HTML)
    await expect(chips.filter({ hasText: "Kasir" })).toHaveClass(/bg-ink/);
    await expect(chips.filter({ hasText: "Terapis" })).not.toHaveClass(/bg-ink/);

    // Click Terapis -> bg-ink moves to Terapis, Kasir loses it
    await chips.filter({ hasText: "Terapis" }).click();
    await expect(chips.filter({ hasText: "Terapis" })).toHaveClass(/bg-ink/);
    await expect(chips.filter({ hasText: "Kasir" })).not.toHaveClass(/bg-ink/);

    // Click Gudang -> bg-ink moves
    await chips.filter({ hasText: "Gudang" }).click();
    await expect(chips.filter({ hasText: "Gudang" })).toHaveClass(/bg-ink/);
    await expect(chips.filter({ hasText: "Terapis" })).not.toHaveClass(/bg-ink/);

    // Click Manajer -> bg-ink moves
    await chips.filter({ hasText: "Manajer" }).click();
    await expect(chips.filter({ hasText: "Manajer" })).toHaveClass(/bg-ink/);
    await expect(chips.filter({ hasText: "Gudang" })).not.toHaveClass(/bg-ink/);

    await page.screenshot({ path: `${SCREENSHOT_DIR}/login-roles.png`, fullPage: true });
  });
});

test.describe("pos.html", () => {
  test("clicking service tile increments cart count", async ({ page }) => {
    await gotoPreview(page, "pos.html");
    await expect(page).toHaveTitle(/POS Preview/);

    // Initial cart count is "0 item"
    const itemCount = page.locator("#item-count");
    await expect(itemCount).toHaveText("0 item");

    const firstTile = page.locator(".service-tile").first();
    await expect(firstTile).toBeVisible();
    const firstName = await firstTile.getAttribute("data-name");
    expect(firstName).toBeTruthy();

    // First click -> 1 item, cart-empty hidden, cart-lines visible
    await firstTile.click();
    await expect(itemCount).toHaveText("1 item");
    await expect(page.locator("#cart-empty")).toBeHidden();
    await expect(page.locator("#cart-lines")).toBeVisible();

    // Second click on same tile -> 2 items
    await firstTile.click();
    await expect(itemCount).toHaveText("2 item");

    // Subtotal should reflect 2 * first tile price
    const expectedPrice = Number(await firstTile.getAttribute("data-price"));
    expect(expectedPrice).toBeGreaterThan(0);
    const subtotalText = (await page.locator("#subtotal").textContent()) ?? "";
    const expectedSubtotal = `Rp ${(expectedPrice * 2).toLocaleString("id-ID")}`;
    expect(subtotalText).toBe(expectedSubtotal);

    // Click a different tile -> 3 items
    const secondTile = page.locator(".service-tile").nth(1);
    await secondTile.click();
    await expect(itemCount).toHaveText("3 item");

    await page.screenshot({ path: `${SCREENSHOT_DIR}/pos-cart.png`, fullPage: true });
  });
});

test.describe("gudang.html", () => {
  test("clicking filter chip toggles active class", async ({ page }) => {
    await gotoPreview(page, "gudang.html");
    await expect(page).toHaveTitle(/Gudang Preview/);

    const chips = page.locator(".filter-chip");
    await expect(chips).toHaveCount(3);

    // Use data-filter for exact, non-substring matching ("Akan kadaluarsa" contains "Kadaluarsa").
    const allChip = page.locator('.filter-chip[data-filter="all"]');
    const soonChip = page.locator('.filter-chip[data-filter="soon"]');
    const expiredChip = page.locator('.filter-chip[data-filter="expired"]');

    // Default active = Semua
    await expect(allChip).toHaveClass(/bg-ink/);
    await expect(soonChip).not.toHaveClass(/bg-ink/);

    // Click "Akan kadaluarsa" -> bg-ink moves
    await soonChip.click();
    await expect(soonChip).toHaveClass(/bg-ink/);
    await expect(allChip).not.toHaveClass(/bg-ink/);

    // Click "Kadaluarsa" -> bg-ink moves
    await expiredChip.click();
    await expect(expiredChip).toHaveClass(/bg-ink/);
    await expect(soonChip).not.toHaveClass(/bg-ink/);

    // Back to "Semua"
    await allChip.click();
    await expect(allChip).toHaveClass(/bg-ink/);
    await expect(expiredChip).not.toHaveClass(/bg-ink/);

    await page.screenshot({ path: `${SCREENSHOT_DIR}/gudang-filter.png`, fullPage: true });
  });
});

test.describe("laporan.html", () => {
  test("renders exactly 4 report cards", async ({ page }) => {
    await gotoPreview(page, "laporan.html");
    await expect(page).toHaveTitle(/Laporan Hub Preview/);

    // Hub page lists 4 laporan documents (Arus Kas, Stok & Komisi, Daily, Inventory Movements)
    const cardLinks = page.locator("main a.group");
    await expect(cardLinks).toHaveCount(4);

    const expectedHeadings = ["Arus Kas", "Stok & Komisi", "Daily Report", "Inventory Movements"];
    for (const heading of expectedHeadings) {
      await expect(page.getByRole("heading", { name: heading })).toBeVisible();
    }

    await page.screenshot({ path: `${SCREENSHOT_DIR}/laporan-hub.png`, fullPage: true });
  });
});
