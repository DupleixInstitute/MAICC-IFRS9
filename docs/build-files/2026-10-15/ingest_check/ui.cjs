// node ui.cjs scen.json [only-name]
const fs = require('fs');
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
const scen = JSON.parse(fs.readFileSync(process.argv[2], 'utf8')).filter(s => !process.argv[3] || s.name === process.argv[3]);
const sleep = ms => new Promise(r => setTimeout(r, ms));
async function clickText(p, t) {
  const ok = await p.evaluate((t) => {
    const els = [...document.querySelectorAll('button, a, [role=tab], label, span')].filter(e => e.offsetParent !== null);
    const e = els.find(e => (e.innerText || '').trim() === t) || els.find(e => (e.innerText || '').trim().startsWith(t));
    if (!e) return false; e.click(); return true;
  }, t);
  return ok;
}
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const reqs = [];
  p.on('request', r => { if (r.method() !== 'GET') reqs.push(r.method() + ' ' + r.url().replace('http://127.0.0.1:8072', '')); });
  for (const s of scen) {
    const errs = []; reqs.length = 0;
    const h1 = e => errs.push('PAGEERR ' + e.message.slice(0, 200));
    const h2 = m => { if (m.type() === 'error') errs.push('CONSOLE ' + m.text().slice(0, 200)); };
    const h3 = r => { if (r.status() >= 400) errs.push('HTTP ' + r.status() + ' ' + r.url().replace('http://127.0.0.1:8072', '')); };
    p.on('pageerror', h1); p.on('console', h2); p.on('response', h3);
    let status = 0;
    try {
      const resp = await p.goto('http://127.0.0.1:8072' + s.url, { waitUntil: 'load', timeout: 150000 });
      status = resp.status();
      await sleep(2500);
      const clicked = [];
      for (const t of (s.click || [])) { clicked.push(t + ':' + await clickText(p, t)); await sleep(1200); }
      if (s.upload) {
        const inputs0 = await p.$$("input[type=file]"); const inputs = inputs0.slice(); if ((s.upload.index || 0) < 0) s.upload.index = inputs.length + s.upload.index;
        if (inputs[s.upload.index || 0]) { await inputs[s.upload.index || 0].uploadFile(s.upload.path); await sleep(s.upload.wait || 3000); clicked.push('upload:ok(' + inputs.length + ' inputs)'); }
        else clicked.push('upload:NO INPUT (' + inputs.length + ')');
      }
      for (const t of (s.after || [])) { clicked.push(t + ':' + await clickText(p, t)); await sleep(s.afterWait || 3000); }
      const info = await p.evaluate((sel) => {
        const modal = [...document.querySelectorAll('.fixed, [role=dialog]')].filter(e => e.offsetParent !== null || getComputedStyle(e).position === 'fixed').filter(e => e.innerText && e.innerText.trim().length > 40 && e.getBoundingClientRect().width > 300);
        const el = sel ? document.querySelector(sel) : (modal.length ? modal[modal.length - 1] : document.querySelector('main'));
        return { modal: modal.length > 0, text: el ? el.innerText.replace(/\s+/g, ' ').slice(0, 900) : '' };
      }, s.sel);
      const shot = T + 'ingest_check/ui_' + s.name;
      await p.screenshot({ path: shot + '_light.png' });
      await p.evaluate(() => document.documentElement.classList.add('dark')); await sleep(400);
      const darkInfo = await p.evaluate(() => {
        // white panels left in dark mode: visible elements wider than 200px with a near-white background
        const bad = [...document.querySelectorAll('div, section, table, thead, tbody, tr, td, th, form, input, select, textarea, label, p')].filter(e => { const r = e.getBoundingClientRect(); if (r.width < 120 || r.height < 16 || r.bottom < 0 || r.top > innerHeight) return false; const bg = getComputedStyle(e).backgroundColor; const m = bg.match(/rgba?\((\d+), (\d+), (\d+)(?:, ([\d.]+))?/); return m && (m[4] === undefined || +m[4] > 0.5) && +m[1] > 235 && +m[2] > 235 && +m[3] > 235; });
        return bad.slice(0, 5).map(e => e.tagName + '.' + (e.className || '').toString().slice(0, 60) + ' "' + (e.innerText || '').trim().slice(0, 40) + '"');
      });
      await p.screenshot({ path: shot + '_dark.png' });
      await p.evaluate(() => document.documentElement.classList.remove('dark'));
      console.log(JSON.stringify({ name: s.name, status, clicked, modal: info.modal, text: info.text, whiteInDark: darkInfo, posts: [...reqs], errs: errs.slice(0, 8) }));
    } catch (e) { console.log(JSON.stringify({ name: s.name, status, fail: e.message, errs })); }
    p.off('pageerror', h1); p.off('console', h2); p.off('response', h3);
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
