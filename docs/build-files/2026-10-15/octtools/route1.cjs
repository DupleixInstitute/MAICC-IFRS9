'use strict';
// Route 1: E-Banker Feed screen, Load a pack: node route1.cjs <zip> <check|load> <shot>
const ui = require('./ui.cjs');
const [zip, mode, name] = process.argv.slice(2);
(async () => {
  const { browser, page } = await ui.open('maker');
  const out = { zip: zip.split(/[\\/]/).pop(), mode };
  try {
    await ui.goto(page, '/eir-feed?tab=loads');
    await page.evaluate(() => [...document.querySelectorAll('button')].find((b) => /Load a pack/.test(b.innerText)).click());
    await ui.sleep(800);
    const input = await page.$('#pack-file');
    await input.uploadFile(zip);
    if (mode === 'check') await page.evaluate(() => { const c = [...document.querySelectorAll('label')].find((l) => /Check only/.test(l.innerText)).querySelector('input'); if (!c.checked) c.click(); });
    await ui.sleep(500);
    out.before_submit = await page.evaluate(() => { const b = [...document.querySelectorAll('button[type=submit]')].find((b) => /Check the pack|Load the pack/.test(b.innerText)); const f = document.querySelector('#pack-file'); return { button: b ? b.innerText + (b.disabled ? ' (disabled)' : '') : null, files: f ? f.files.length : -1 }; });
    if (out.before_submit.button && /disabled/.test(out.before_submit.button)) { await page.evaluate(() => { const f = document.querySelector('#pack-file'); f.dispatchEvent(new Event('change', { bubbles: true })); }); await ui.sleep(300); }
    await ui.shot(page, name + '_form', false);
    const resp = page.waitForResponse((r) => r.url().includes('/eir-feed') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 900000 });
    await page.evaluate(() => [...document.querySelectorAll('button[type=submit]')].find((b) => /Check the pack|Load the pack/.test(b.innerText)).click());
    const t0 = Date.now();
    const r = await resp; const j = await r.json().catch(() => null);
    out.seconds = Math.round((Date.now() - t0) / 1000);
    out.flash = j && j.props ? Object.fromEntries(Object.entries(j.props.flash).filter(([, v]) => v)) : null;
    if (out.flash && out.flash.import_check) out.flash.import_check = { ok: out.flash.import_check.ok, issues: (out.flash.import_check.issues || []).length };
    out.latest_load = j && j.props ? (({ id, pack, route, status, files, failed }) => ({ id, pack, route, status, files, failed }))(j.props.loads[0]) : null;
    await ui.sleep(1500);
    await ui.shot(page, name, false);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
