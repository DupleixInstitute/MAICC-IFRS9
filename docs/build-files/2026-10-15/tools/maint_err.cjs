const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' }); const p = await b.newPage(); await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push('PAGE: ' + e.message)); p.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') errs.push(m.type() + ': ' + m.text().slice(0, 300)); });
  p.on('requestfailed', r => errs.push('FAILED: ' + r.url()));
  const resp = await p.goto('http://127.0.0.1:8050/maintenance', { waitUntil: 'networkidle2', timeout: 90000 });
  await new Promise(r => setTimeout(r, 2000));
  console.log('status', resp.status(), 'h1:', await p.evaluate(() => (document.querySelector('h1,h2')||{}).innerText));
  console.log(errs.slice(0, 10).join('\n'));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
