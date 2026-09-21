const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({args: ['--no-sandbox', '--disable-dev-shm-usage']});
  const ctx = await browser.newContext({ignoreHTTPSErrors: true});
  const page = await ctx.newPage();

  const logs = [];
  page.on('console', m => logs.push(`[${m.type()}] ${m.text()}`.slice(0, 220)));
  page.on('pageerror', e => logs.push(`[pageerror] ${String(e).slice(0, 220)}`));

  // Log in.
  await page.goto('http://localhost:8000/login/index.php', {waitUntil: 'domcontentloaded'});
  await page.fill('#username', 'admin');
  await page.fill('#password', 'Test@1234pass');
  await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.click('#loginbtn')]);

  // Activity page.
  await page.goto('http://localhost:8000/mod/livesession/view.php?id=2', {waitUntil: 'networkidle'});

  const btn = page.locator('[id^="livesession-join-"]');
  if (await btn.count() === 0) {
    console.log('RESULT: join button not present on the page');
    console.log(logs.join('\n'));
    await browser.close();
    return;
  }

  await btn.first().click();
  await page.waitForTimeout(6000);

  const status = await page.locator('[id^="livesession-status-"]').first().innerText().catch(() => '(no status element)');
  const globals = await page.evaluate(() => ({
    ZoomMtgEmbedded: typeof window.ZoomMtgEmbedded,
    ReactWidgets: typeof window.ReactWidgets,
    defineAmd: typeof (window.define && window.define.amd),
  }));

  console.log('STATUS TEXT: ' + status.replace(/\s+/g, ' ').trim());
  console.log('GLOBALS AFTER LOAD: ' + JSON.stringify(globals));
  console.log('--- console ---');
  console.log(logs.filter(l => /livesession|zoom|sdk|error/i.test(l)).slice(0, 12).join('\n') || '(nothing relevant)');
  await browser.close();
})();
