// Generate screenshots via Playwright (CommonJS)
// Usage: cd outputs/sim-kk-ui-previews && node screenshot.cjs
const { chromium } = require('D:/users/stefa/project/sim-kk/apps/web/node_modules/playwright');
const path = require('path');
const fs = require('fs');

const dir = __dirname.replace(/\\/g, '/');
const files = [
  'login', 'pos', 'rekam-medis', 'gudang',
  'laporan', 'laporan-daily', 'laporan-inventory-movements'
];

(async () => {
  const assetsDir = path.join(dir, 'assets');
  if (!fs.existsSync(assetsDir)) fs.mkdirSync(assetsDir, { recursive: true });
  const browser = await chromium.launch();
  for (const f of files) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const url = 'file:///' + path.join(dir, f + '.html').replace(/\\/g, '/');
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
    } catch (e) {
      console.log('  warn: load slow for ' + f + ' — ' + e.message);
    }
    await page.waitForTimeout(2500);
    try {
      await page.screenshot({ path: path.join(assetsDir, f + '.png'), fullPage: false });
      console.log('  saved assets/' + f + '.png');
    } catch (e) {
      console.log('  error: ' + f + ' - ' + e.message);
    }
    await page.close();
  }
  await browser.close();
  console.log('Done.');
})();
