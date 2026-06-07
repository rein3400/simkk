// @ts-check
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  page.on('console', m => console.log('  [browser]', m.type(), m.text()));
  page.on('pageerror', e => console.log('  [pageerror]', e.message));
  page.on('requestfailed', r => console.log('  [reqfail]', r.url(), r.failure()?.errorText));

  await page.goto('http://127.0.0.1:5173/');
  await page.waitForSelector('input[aria-label="Username"]', { timeout: 10000 });
  await page.getByRole('button', { name: 'Manajer', exact: true }).click();
  await page.waitForTimeout(300);
  const respPromise = page.waitForResponse(r => r.url().includes('/api/login'), { timeout: 10000 });
  await page.getByRole('button', { name: /Masuk ke SIM-KK/ }).click();
  const resp = await respPromise.catch(() => null);
  if (resp) console.log('LOGIN resp:', resp.status(), await resp.text().catch(() => 'n/a'));
  await page.waitForTimeout(4000);
  console.log('URL after login:', page.url());
  const hasShell = await page.locator('.app-shell').count();
  console.log('AppShell present:', hasShell);
  const err = await page.locator('.microcopy').textContent().catch(() => null);
  console.log('Login err:', err);
  await page.screenshot({ path: 'C:/tools/debug-after-login.png', fullPage: true });

  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
