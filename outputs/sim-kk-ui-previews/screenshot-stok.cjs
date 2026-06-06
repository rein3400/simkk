const { chromium } = require('D:/users/stefa/project/sim-kk/apps/web/node_modules/playwright');
const path = require('path');
(async () => {
  const dir = __dirname.replace(/\\/g, '/');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('file:///' + path.join(dir, 'laporan-stok-komisi.html').replace(/\\/g, '/'), { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: path.join(dir, 'assets', 'laporan-stok-komisi.png'), fullPage: false });
  console.log('saved');
  await browser.close();
})();
