const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const c = require(T + 'c_auditA.json');
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  p.on('pageerror', e => console.log('pageerror: ' + e.message));
  p.on('console', m => console.log('console.' + m.type() + ': ' + m.text().slice(0, 300)));
  p.on('requestfailed', r => console.log('FAILED ' + r.url() + ' ' + (r.failure()||{}).errorText));
  p.on('response', r => { if (r.status() >= 300) console.log('HTTP ' + r.status() + ' ' + r.url()); });
  await p.goto('http://127.0.0.1:8071/' + process.argv[2], { waitUntil: 'networkidle2', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  const page = await p.evaluate(() => { const a = document.getElementById('app'); return a ? JSON.parse(a.dataset.page || '{}').component : 'noapp'; });
  console.log('component', page, 'html len', (await p.content()).length);
  await b.close();
})();
