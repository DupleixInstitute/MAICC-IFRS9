// node clicks.cjs <urls.json> <out.jsonl> : clicks every safe visible button on each page, records modals and errors
const L = require('./lib.cjs');
const [urlsFile, outFile] = process.argv.slice(2);
const items = JSON.parse(L.fs.readFileSync(urlsFile, 'utf8'));
const RISKY = /delete|remove|approve|reject|run|calculat|lock|import|upload|save|submit|apply|propose|build|generat|close|discard|seed|retire|expire|sign|confirm|classify|rematch|recalc|update|send|toggle|start|refresh|load|land|post|log ?out|reopen|commit|process|activate|deactivate|attach|trigger|cancel|reset|clear|fetch|sync|weights|version|store|add scenario|mark|tick|allocate|link/i;
(async () => {
  const b = await L.launch(); const p = await L.newPage(b);
  if (process.env.UAT_COOKIE) await L.useCookie(p, process.env.UAT_COOKIE);
  let n = 0;
  for (const it of items) {
    n++;
    const rec = { url: it.url, clicks: [] };
    try {
      await L.goto(p, it.url);
      const labels = await p.evaluate(() => [...document.querySelectorAll('main button, main [role=tab]')].filter(e => e.offsetParent !== null && !e.disabled && (e.type !== 'submit')).map(e => (e.textContent.replace(/\s+/g, ' ').trim() || e.getAttribute('title') || e.getAttribute('aria-label') || '').slice(0, 50)));
      const uniq = [...new Set(labels)].filter(t => t && !RISKY.test(t)).slice(0, 14);
      for (const t of uniq) {
        L.resetLog(p);
        const before = p.url();
        const ok = await p.evaluate(t => { const el = [...document.querySelectorAll('main button, main [role=tab]')].find(e => e.offsetParent !== null && !e.disabled && e.type !== 'submit' && ((e.textContent.replace(/\s+/g, ' ').trim() || e.getAttribute('title') || e.getAttribute('aria-label') || '').slice(0, 50) === t)); if (!el) return false; el.click(); return true; }, t);
        if (!ok) continue;
        await L.idle(p, 700);
        const modal = await p.evaluate(() => { const d = [...document.querySelectorAll('[role=dialog], dialog[open], .fixed.inset-0')].filter(e => e.offsetParent !== null || getComputedStyle(e).position === 'fixed'); const v = d.find(e => e.getBoundingClientRect().height > 50); return v ? v.innerText.replace(/\s+/g, ' ').slice(0, 80) : null; });
        const errs = [...p._log.pageerr, ...p._log.bad.filter(x => !x.includes('fonts.g'))];
        rec.clicks.push({ t, modal, nav: p.url() !== before ? p.url() : null, errs });
        if (modal) { await p.keyboard.press('Escape'); await L.sleep(400); }
        if (p.url() !== before) { await L.goto(p, it.url); }
      }
      // pagination: click page 2 if present
      const pg = await p.evaluate(() => { const a = [...document.querySelectorAll('main nav a, main nav button, main button')].find(e => e.offsetParent !== null && e.textContent.trim() === '2'); if (a) { a.click(); return true } return false; });
      if (pg) { L.resetLog(p); await L.idle(p, 800); rec.page2 = { errs: [...p._log.pageerr, ...p._log.bad], url: p.url() }; }
    } catch (e) { rec.fatal = e.message.slice(0, 200); }
    L.fs.appendFileSync(outFile, JSON.stringify(rec) + '\n');
    const bad = rec.clicks.filter(c => c.errs.length);
    console.log(n, it.url, 'clicks', rec.clicks.length, 'modals', rec.clicks.filter(c => c.modal).length, bad.length ? 'ERR ' + JSON.stringify(bad).slice(0, 300) : '', rec.page2 && rec.page2.errs.length ? 'P2ERR ' + rec.page2.errs.join(';').slice(0, 200) : '', rec.fatal || '');
  }
  await b.close();
})();
