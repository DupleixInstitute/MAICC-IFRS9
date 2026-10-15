// node multi.cjs list.txt   (lines: name path) - full-page shots at 1440, prints status, JS errors, and page facts
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const fs = require('fs');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
const lines = fs.readFileSync(process.argv[2], 'utf8').split('\n').map(s => s.trim()).filter(Boolean);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const c = require(T + 'c_auditA.json');
  for (const ln of lines) { for (let attempt=0; attempt<4; attempt++) {
    const [name, path] = ln.split(/\s+/);
    const p = await b.newPage();
    await p.setViewport({ width: 1440, height: 900 });
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    const errs = [];
    p.on('pageerror', e => errs.push('pageerror: ' + e.message));
    p.on('console', m => { if ((m.type() === 'error' || m.type() === 'warning') && !/401|Help manual/.test(m.text())) errs.push(m.type() + ': ' + m.text().slice(0, 200)); });
    p.on('response', r => { if (r.status() >= 400 && !r.url().includes('/api/manuals/')) errs.push('HTTP ' + r.status() + ' ' + r.url()); });
    let status;
    try { const resp = await p.goto('http://127.0.0.1:8071/' + path.replace(/^\//, ''), { waitUntil: 'networkidle2', timeout: 120000 }); status = resp && resp.status(); } catch (e) { status = 'ERR ' + e.message; }
    await new Promise(r => setTimeout(r, 3500));
    await p.screenshot({ path: name + '.png', fullPage: true });
    const facts = await p.evaluate(() => {
      const h1 = document.querySelector('header h1, header h2, h1, h2');
      const rows = [...document.querySelectorAll('table')].map(t => t.querySelectorAll('tbody tr').length);
      const btns = [...document.querySelectorAll('header button, header a')].map(x => x.innerText.trim()).filter(Boolean).slice(0, 12);
      const tabs = [...document.querySelectorAll('nav a, [role=tab]')].map(x => x.innerText.trim().replace(/\s+/g,' ')).filter(Boolean).slice(0,0);
      return { title: h1 && h1.innerText.trim().slice(0, 80), tables: rows, headerBtns: btns, height: document.body.scrollHeight };
    });
    await p.close();
    if (errs.some(e=>/CONNECTION_RESET|timeout/.test(e)) || String(status).startsWith('ERR')) { await new Promise(r=>setTimeout(r,3000)); if (attempt<3) continue; }
    console.log(name, JSON.stringify({ status, ...facts, errors: errs.slice(0, 6) })); break;
  } }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
