'use strict';
// Test A: propose a loan book build for 2026-10 from the Builds tab (the real form).
const ui = require('./ui.cjs');
const from = process.argv[2] || '2026-10';
const to = process.argv[3] || from;
const who = process.argv[4] || 'maker';
(async () => {
  const { browser, page } = await ui.open(who);
  const out = {};
  try {
    await ui.goto(page, '/eir-feed?tab=builds');
    await page.evaluate(() => [...document.querySelectorAll('button')].find((b) => /Propose a build/.test(b.innerText)).click());
    await ui.sleep(800);
    await page.evaluate((from, to) => {
      const ins = [...document.querySelectorAll('input[type=month]')];
      ins[0].value = from; ins[0].dispatchEvent(new Event('input', { bubbles: true }));
      ins[1].value = to; ins[1].dispatchEvent(new Event('input', { bubbles: true }));
    }, from, to);
    await ui.shot(page, `A1_build_form_${from}`, false);
    const resp = page.waitForResponse((r) => r.url().includes('/eir-feed') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 900000 });
    await page.evaluate(() => [...document.querySelectorAll('button[type=submit]')].find((b) => /Propose the build/.test(b.innerText)).click());
    const r = await resp;
    const j = await r.json().catch(() => null);
    out.flash = j && j.props ? j.props.flash : null;
    await ui.sleep(1500);
    out.screen = (await ui.text(page)).split('\n').filter((l) => /refused|landing zone|proposed|Build \d+/i.test(l)).slice(0, 6);
    await ui.shot(page, `A1_build_${from}_result`, false);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
