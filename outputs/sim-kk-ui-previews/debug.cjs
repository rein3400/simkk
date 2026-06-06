const { chromium } = require('D:/users/stefa/project/sim-kk/apps/web/node_modules/playwright');
const path = require('path');
(async () => {
  const dir = __dirname.replace(/\\/g, '/');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('console', m => console.log('[BROWSER]', m.type(), m.text().substring(0,200)));
  page.on('pageerror', e => console.log('[ERROR]', e.message));
  await page.goto('file:///' + path.join(dir, 'pos.html').replace(/\\/g, '/'));
  await page.waitForTimeout(3000);
  const result = await page.evaluate(() => {
    const sample = document.querySelector('.bg-cream');
    const s = getComputedStyle(document.body);
    const twScript = document.querySelector('script[src*="tailwind"]');
    return {
      bodyBg: s.backgroundColor,
      bodyFont: s.fontFamily,
      hasBgCream: sample ? 'yes' : 'no',
      twScriptSrc: twScript ? twScript.src : 'no script',
    };
  });
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();
