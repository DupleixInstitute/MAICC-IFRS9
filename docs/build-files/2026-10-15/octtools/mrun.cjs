'use strict';
// Run one Maintenance card from the screen: node mrun.cjs "<card title>" <period|-> <who> <shotname> [tab]
const fs = require('fs');
const ui = require('./ui.cjs');
const [title, period, who, name, tab] = process.argv.slice(2);
(async () => {
  const { browser, page } = await ui.open(who || 'maker');
  const out = { title, period };
  try {
    await ui.goto(page, '/maintenance');
    if (tab) {
      await page.evaluate((tab) => [...document.querySelectorAll('button,a')].find((b) => b.innerText.trim().startsWith(tab)).click(), tab);
      await ui.sleep(800);
    }
    if (period && period !== '-') { await page.select('#m-period', period); await ui.sleep(300); }
    const posted = page.waitForResponse((r) => r.url().endsWith('/maintenance/run'), { timeout: 120000 });
    const ok = await page.evaluate((title) => {
      const h = [...document.querySelectorAll('h2,h3,h4,div')].find((e) => e.children.length <= 2 && e.innerText.trim() === title);
      let card = h; while (card && !card.querySelector('button')) card = card.parentElement;
      const btn = [...card.querySelectorAll('button')].find((b) => /Run/.test(b.innerText));
      if (!btn) return false; btn.click(); return true;
    }, title);
    if (!ok) throw new Error('card not found: ' + title);
    await ui.sleep(1000);
    // the confirm dialog
    await page.evaluate(() => {
      const b = [...document.querySelectorAll('button')].reverse().find((x) => x.offsetParent && /^Run$/.test(x.innerText.trim()));
      if (b) b.click();
    });
    const r = await posted;
    const j = await r.json().catch(() => ({}));
    out.post = { status: r.status(), ok: j.ok, message: j.message, id: j.id, command: j.command };
    if (j.ok) {
      const t0 = Date.now();
      let st;
      for (;;) {
        await ui.sleep(15000);
        const o = await ui.api(page, 'GET', '/maintenance/output/' + j.id);
        try { st = JSON.parse(o.body).run; } catch (e) { st = null; }
        if (st && st.status !== 'running') break;
        if (Date.now() - t0 > 5400000) { out.timeout = true; break; }
      }
      out.run = st ? { status: st.status, exit_code: st.exit_code, seconds: Math.round((Date.now() - t0) / 1000) } : null;
      if (st) fs.writeFileSync(ui.OUT + '/' + name + '.log', st.output || '');
      out.tail = st ? (st.output || '').split('\n').filter((l) => l.trim()).slice(-12) : null;
    }
    await ui.sleep(2500);
    await ui.shot(page, name, false);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
