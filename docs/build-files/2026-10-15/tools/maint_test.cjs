const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.argv[2]); const out = process.argv[3];
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8050/maintenance', { waitUntil: 'networkidle2', timeout: 120000 }); console.log('url', p.url()); await p.waitForFunction(() => [...document.querySelectorAll('button')].some(b => b.innerText.startsWith('Diagnostics')), { timeout: 60000 });
  await new Promise(r => setTimeout(r, 3000));
  await p.screenshot({ path: out + '/maint_setup.png', fullPage: true });
  // Diagnostics tab
  await p.evaluate(() => [...document.querySelectorAll('nav button')].find(b => b.innerText.startsWith('Diagnostics')).click());
  await new Promise(r => setTimeout(r, 800));
  await p.evaluate(() => { const card = [...document.querySelectorAll('h3')].find(h => h.innerText === 'System snapshot').closest('div.flex.flex-col'); [...card.querySelectorAll('button')].find(b => b.innerText.includes('Run')).click(); });
  for (let i = 0; i < 40; i++) { await new Promise(r => setTimeout(r, 1500)); const s = await p.evaluate(() => { const pre = document.querySelector('pre'); const badge = pre && pre.parentElement.querySelector('.maiic-badge'); return badge ? badge.innerText : ''; }); if (s && !s.startsWith('running')) break; }
  const res = await p.evaluate(() => ({ status: document.querySelector('pre').parentElement.querySelector('.maiic-badge').innerText, out: document.querySelector('pre').innerText.slice(0, 400) }));
  await p.screenshot({ path: out + '/maint_run.png' });
  console.log(JSON.stringify({ errs, ...res }, null, 1));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
