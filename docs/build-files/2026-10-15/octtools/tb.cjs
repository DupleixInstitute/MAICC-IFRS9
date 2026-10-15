'use strict';
// Trial balance screen: Check file on a real Finance TB, then Load it (a landed file is recognised by its hash).
const ui = require('./ui.cjs');
const [file, name] = process.argv.slice(2);
(async () => {
  const { browser, page } = await ui.open('maker');
  const out = { file: file.split(/[\\/]/).pop() };
  try {
    await ui.goto(page, '/eir-trial-balances');
    const before = await ui.props(page);
    out.summary_before = before.summary;
    await page.evaluate(() => [...document.querySelectorAll('button')].find((b) => /Upload/i.test(b.innerText)).click());
    await ui.sleep(800);
    await (await page.$('#tb-file')).uploadFile(file);
    await ui.sleep(500);
    // the spec panel's Check file
    const chk = page.waitForResponse((r) => r.url().includes('/import-templates/trial-balance/check'), { timeout: 600000 }).catch(() => null);
    out.check_clicked = await page.evaluate(() => { const b = [...document.querySelectorAll('button')].find((x) => /Check (the )?file/i.test(x.innerText) && x.offsetParent); if (b && !b.disabled) { b.click(); return b.innerText.trim(); } return b ? 'disabled' : null; });
    const cr = await chk;
    if (cr) { const j = await cr.json().catch(() => null); out.check = j ? { ok: j.ok, rows: j.rows, errors: (j.issues || []).filter((i) => i.severity === 'error').length, first: (j.issues || []).slice(0, 3).map((i) => i.message) } : null; }
    await ui.sleep(1200);
    await ui.shot(page, name + '_check', false);
    const resp = page.waitForResponse((r) => r.url().includes('/eir-trial-balances') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 900000 });
    await page.evaluate(() => [...document.querySelectorAll('button[type=submit]')].find((b) => /Load trial balance/.test(b.innerText)).click());
    const r = await resp; const j = await r.json().catch(() => null);
    out.flash = j && j.props ? Object.fromEntries(Object.entries(j.props.flash).filter(([, v]) => v && typeof v === 'string')) : null;
    out.summary_after = j && j.props ? j.props.summary : null;
    await ui.sleep(1200);
    await ui.shot(page, name + '_load', false);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
