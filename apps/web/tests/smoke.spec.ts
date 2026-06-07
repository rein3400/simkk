import { expect, test, type Page } from "@playwright/test";

async function login(page: Page, role = "Kasir") {
  await page.goto("/");
  await expect(page.getByRole("heading", { name: "SIM-KK", exact: true })).toBeVisible();
  await page.getByRole("button", { name: role }).click();
  await page.getByRole("button", { name: "Masuk ke SIM-KK" }).click();
  await expect(page.getByTestId("role-lock")).toContainText(role);
}

test("login reaches the operational shell", async ({ page }) => {
  await login(page);
  await expect(page.getByText("Operasional Klinik")).toBeVisible();
  await expect(page.getByText("Catalog tindakan & produk")).toBeVisible();
  await expect(page.getByTestId("role-lock")).toContainText("Kasir");
  await expect(page.getByTestId("role-scope")).toContainText("POS");
  await expect(page.getByTestId("nav-medical")).toHaveCount(0);
});

test("manager navigation reaches all four modules", async ({ page }) => {
  await login(page, "Manajer");
  await expect(page.getByText("Preview laporan")).toBeVisible();
  await page.getByTestId("nav-medical").click();
  await expect(page.getByText("Riwayat kronologis")).toBeVisible();
  await page.getByTestId("nav-inventory").click();
  await expect(page.getByText("Stok, batch, dan HPP")).toBeVisible();
  await page.getByTestId("nav-reports").click();
  await expect(page.getByText("Preview laporan")).toBeVisible();
  await page.getByTestId("nav-pos").click();
  await expect(page.getByText("Keranjang tagihan")).toBeVisible();
});

test("each operational role lands on a distinct module", async ({ page }) => {
  await login(page, "Terapis");
  await expect(page.getByText("Riwayat kronologis")).toBeVisible();
  await expect(page.getByTestId("nav-pos")).toHaveCount(0);
  await page.getByRole("button", { name: "Keluar" }).click();

  await login(page, "Gudang");
  await expect(page.getByText("Stok, batch, dan HPP")).toBeVisible();
  await expect(page.getByTestId("nav-reports")).toHaveCount(0);
});

test("POS payment flow locks commission snapshot", async ({ page }) => {
  await login(page);
  const search = page.getByRole("searchbox", { name: /cari/i });
  await search.fill("Daily Sunscreen");
  await expect(page.getByTestId("service-card-7")).toBeVisible();
  await expect(page.getByTestId("service-card-1")).toBeHidden();
  await search.fill("");

  await page.getByTestId("service-card-1").click();
  await page.getByTestId("service-card-1").click();
  await expect(page.getByTestId("cart-qty-1")).toHaveText("2");
  await page.getByRole("button", { name: "Kurangi item" }).click();
  await expect(page.getByTestId("cart-qty-1")).toHaveText("1");
  await page.getByTestId("payment-method").selectOption("QRIS");
  await page.getByTestId("discount-input").fill("10000");
  await page.getByTestId("therapist-select").selectOption("2");
  await page.getByTestId("pay-button").click();
  await expect(page.getByText("Komisi terkunci").first()).toBeVisible();
  await expect(page.getByText("QRIS").last()).toBeVisible();
  await expect(page.getByText(/Receipt RCPT-/)).toBeVisible();
});

test("medical photo upload uses file preview and consent gate", async ({ page }) => {
  await login(page, "Terapis");
  await page.getByTestId("photo-input").evaluate((node) => {
    const bytes = Uint8Array.from(
      atob("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/awp4kAAAAAASUVORK5CYII="),
      (char) => char.charCodeAt(0),
    );
    const file = new File([bytes], "after-clinic.png", { type: "image/png" });
    const transfer = new DataTransfer();
    transfer.items.add(file);
    const input = node as HTMLInputElement;
    input.files = transfer.files;
    input.dispatchEvent(new Event("change", { bubbles: true }));
  });
  await expect(page.getByTestId("photo-preview")).toBeVisible();
  await expect(page.getByTestId("upload-photo")).toBeDisabled();
  await page.getByTestId("photo-consent").check();
  await page.getByTestId("upload-photo").click();
  await expect(page.getByText("Foto klinis tersimpan dengan referensi lokal.")).toBeVisible();
});

test("report export downloads a real file", async ({ page }) => {
  await login(page, "Manajer");
  await expect(page.getByRole("columnheader", { name: "Saldo" })).toBeVisible();
  const downloadPromise = page.waitForEvent("download");
  await page.getByTestId("export-report").click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe("finance.pdf");
});

for (const viewport of [
  { width: 1440, height: 900 },
  { width: 1024, height: 768 },
  { width: 390, height: 844 },
]) {
  test(`viewport ${viewport.width}x${viewport.height} has no page horizontal scroll`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await login(page);
    const scroll = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }));
    expect(scroll.scrollWidth).toBeLessThanOrEqual(scroll.clientWidth + 1);
  });
}
