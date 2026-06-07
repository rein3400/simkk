// @ts-check
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, '..', '..', 'outputs', 'ui-review-2026-06-02');
fs.mkdirSync(OUT, { recursive: true });

const ROLES = ['Kasir', 'Terapis', 'Gudang', 'Manajer'];
const VIEWS = {
  Kasir:   ['pos'],
  Terapis: ['medical'],
  Gudang:  ['inventory'],
  Manajer: ['pos', 'medical', 'inventory', 'reports'],
};

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  // 1. Login screen
  await page.goto('http://127.0.0.1:5173/');
  await page.waitForSelector('input[aria-label="Username"]', { timeout: 10000 });
  await page.screenshot({ path: path.join(OUT, '00-login.png'), fullPage: true });
  console.log('  00-login.png');

  for (const role of ROLES) {
    const username = role.toLowerCase();
    await page.goto('http://127.0.0.1:5173/');
    await page.waitForSelector('input[aria-label="Username"]', { timeout: 10000 });

    // Click role button
    await page.getByRole('button', { name: role, exact: true }).click();
    await page.waitForTimeout(200);
    await page.getByRole('button', { name: /Masuk ke SIM-KK/ }).click();
    await page.waitForSelector('.app-shell', { timeout: 15000 });
    await page.waitForTimeout(2500);

    // Default view for role
    await page.screenshot({ path: path.join(OUT, `${role.toLowerCase()}-default.png`), fullPage: true });
    console.log(`  ${role.toLowerCase()}-default.png`);

    // Each additional view Manajer can see
    for (const view of VIEWS[role]) {
      try {
        await page.locator(`[data-testid="nav-${view}"]`).click({ timeout: 3000 });
        await page.waitForTimeout(800);
        await page.screenshot({ path: path.join(OUT, `${role.toLowerCase()}-${view}.png`), fullPage: true });
        console.log(`  ${role.toLowerCase()}-${view}.png`);
      } catch (e) {
        console.log(`  SKIP ${role.toLowerCase()}-${view} (no nav): ${e.message.slice(0,80)}`);
      }
    }

    // Logout (click user menu or similar)
    try {
      await page.getByRole('button', { name: /Keluar|Logout/ }).click({ timeout: 2000 });
      await page.waitForTimeout(500);
    } catch {}
  }

  await browser.close();
  console.log('Done. Output:', OUT);
})().catch(err => { console.error(err); process.exit(1); });
