// node ui.cjs steps.json : [{goto}, {clickText, within?}, {click: css}, {type:[css,text]}, {eval}, {shot}, {wait}]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
const steps = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const c = require(T + 'c_auditA.json');
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const log = [];
  p.on('pageerror', e => log.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error' && !/401|Help manual/.test(m.text())) log.push('console: ' + m.text().slice(0, 200)); });
  p.on('dialog', async d => { log.push('BROWSER DIALOG ' + d.type() + ': ' + d.message()); await d.dismiss(); });
  p.on('response', r => { if (r.status() >= 400 && !r.url().includes('/api/manuals/')) log.push('HTTP ' + r.status() + ' ' + r.url()); });
  for (const s of steps) {
    try {
      if (s.goto) { for (let a=0;a<3;a++){ try { await p.goto('http://127.0.0.1:8071/' + s.goto, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch(e){ if(a==2) throw e; } } await new Promise(r => setTimeout(r, 1500)); }
      if (s.clickText) {
        const ok = await p.evaluate((txt, within, idx) => {
          let scope = document;
          if (within) { const rows = [...document.querySelectorAll('tr, li, div')].filter(e => e.innerText && e.innerText.includes(within)); scope = rows.sort((a,b)=>a.innerText.length-b.innerText.length)[0]; if (!scope) return 'no scope ' + within; }
          const els = [...scope.querySelectorAll('button, a, [role=button], label, summary')].filter(e => (e.innerText || '').trim() === txt || e.getAttribute('title') === txt || (e.getAttribute('aria-label')||'') === txt);
          if (!els.length) return 'not found ' + txt;
          (idx === -1 ? els[els.length-1] : els[idx || 0]).click(); return 'clicked ' + txt;
        }, s.clickText, s.within, s.idx);
        log.push(ok);
        await new Promise(r => setTimeout(r, s.wait || 1500));
      }
      if (s.click) { await p.click(s.click); await new Promise(r => setTimeout(r, s.wait || 1500)); log.push('clicked ' + s.click); }
      if (s.type) { await p.click(s.type[0], { clickCount: 3 }); await p.type(s.type[0], s.type[1]); }
      if (s.select) { await p.select(s.select[0], s.select[1]); }
      if (s.eval) { const r = await p.evaluate(s.eval); log.push('eval: ' + JSON.stringify(r)); }
      if (s.shot) { await p.screenshot({ path: s.shot, fullPage: !!s.full }); log.push('shot ' + s.shot); }
    } catch (e) { log.push('ERR ' + JSON.stringify(s).slice(0,80) + ' ' + e.message.slice(0, 150)); }
  }
  console.log(log.join('\n'));
  await b.close();
})();
