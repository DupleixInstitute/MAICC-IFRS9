// node crawl.cjs <urls.json> <out.jsonl> [prefix]
// Visits each url logged in as admin; records status, errors, crumbs; light+dark shots;
// probes search, pagination, sorting, and fetches export-looking links.
const L = require('./lib.cjs');
const [urlsFile, outFile, prefix = 'A'] = process.argv.slice(2);
const items = JSON.parse(L.fs.readFileSync(urlsFile, 'utf8'));
(async () => {
  const b = await L.launch();
  const p = await L.newPage(b);
  if (process.env.UAT_COOKIE) await L.useCookie(p, process.env.UAT_COOKIE); else console.log('login ->', await L.login(p, process.env.UAT_EMAIL || 'uat.maker@maiic.test', process.env.UAT_PW || 'UatPass#2026'));
  let n = 0;
  for (const it of items) {
    n++;
    const slug = prefix + String(n).padStart(3, '0') + '_' + it.url.replace(/[^a-z0-9]+/gi, '_').slice(0, 60);
    const rec = { ...it, slug };
    try {
      L.resetLog(p);
      const t0 = Date.now();
      rec.status = await L.goto(p, it.url);
      rec.ms = Date.now() - t0;
      Object.assign(rec, await L.info(p));
      rec.unstyled = !!p._unstyled;
      rec.hOverflow = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      rec.light = await L.shot(p, slug, true);
      await L.dark(p, true);
      rec.darkShot = await L.shot(p, slug + '_dark', false);
      // dark contrast probe: count visible elements with white bg in dark mode inside main
      rec.darkWhite = await p.evaluate(() => {
        const main = document.querySelector('main') || document.body; let c = 0; const ex = [];
        for (const el of main.querySelectorAll('div,section,table,td,th,thead,tr,input,select,textarea,li')) {
          if (!el.offsetParent) continue; const r = el.getBoundingClientRect(); if (r.width < 120 || r.height < 24) continue;
          const bg = getComputedStyle(el).backgroundColor; if (bg === 'rgb(255, 255, 255)' || bg === 'rgb(249, 250, 251)' || bg === 'rgb(243, 244, 246)') { c++; if (ex.length < 3) ex.push(el.tagName + '.' + String(el.className).slice(0, 60)); }
        }
        return { count: c, ex };
      });
      await L.dark(p, false);
      // probes
      rec.probe = await p.evaluate(() => {
        const vis = e => e.offsetParent !== null;
        const links = [...document.querySelectorAll('a[href]')].filter(vis).map(a => ({ t: a.textContent.replace(/\s+/g, ' ').trim().slice(0, 40), h: a.getAttribute('href') }))
          .filter(a => /export|pdf|excel|xlsx|csv|download|sample|template/i.test(a.h + ' ' + a.t));
        const btns = [...document.querySelectorAll('button')].filter(vis).map(b => b.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean).slice(0, 40);
        const search = !!document.querySelector('input[type=search], input[placeholder*="earch"]');
        const pager = [...document.querySelectorAll('nav a, nav button')].filter(vis).some(a => a.textContent.trim() === '2');
        const tables = document.querySelectorAll('table').length;
        const rows = document.querySelectorAll('table tbody tr').length;
        const empty = [...document.querySelectorAll('.maiic-empty, td[colspan]')].filter(vis).map(e => e.textContent.replace(/\s+/g, ' ').trim().slice(0, 100)).slice(0, 2);
        return { links, btns, search, pager, tables, rows, empty };
      });
      // export fetches
      rec.exports = [];
      for (const l of rec.probe.links.slice(0, 6)) {
        if (!l.h || l.h.startsWith('#') || l.h.startsWith('javascript')) continue;
        const r = await p.evaluate(async h => { try { const r = await fetch(h, { credentials: 'same-origin' }); const b = await r.blob(); return { s: r.status, ct: r.headers.get('content-type'), size: b.size, cd: r.headers.get('content-disposition') }; } catch (e) { return { err: e.message } } }, l.h);
        rec.exports.push({ ...l, ...r });
      }
      // search probe
      if (rec.probe.search) {
        L.resetLog(p);
        const inp = await p.$('input[type=search], input[placeholder*="earch"]');
        try { await inp.type('zz'); await L.idle(p, 1500); rec.searchErr = [...p._log.pageerr, ...p._log.bad]; } catch (e) { rec.searchErr = [e.message]; }
      }
      rec.errors = { console: p._log.console, pageerr: p._log.pageerr, bad: p._log.bad };
    } catch (e) { rec.fatal = e.message.slice(0, 300); }
    L.fs.appendFileSync(outFile, JSON.stringify(rec) + '\n');
    console.log(n, rec.status, rec.ms, it.url, rec.err ? 'ERR:' + rec.err.slice(0, 80) : '', rec.fatal || '', (rec.errors && (rec.errors.pageerr.length + rec.errors.bad.length)) || '');
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
