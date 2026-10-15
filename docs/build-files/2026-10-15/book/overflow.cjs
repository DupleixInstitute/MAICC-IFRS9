// node overflow.cjs <cookie.json> <path> : prints scrollWidth/clientWidth of each .maiic-table-wrap
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, target] = process.argv.slice(2);
const c = require(cookieFile);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8070/' + target, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  const r = await p.evaluate(() => [...document.querySelectorAll('.maiic-table-wrap')].map(w => [w.scrollWidth, w.clientWidth]));
  console.log(target, JSON.stringify(r));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
