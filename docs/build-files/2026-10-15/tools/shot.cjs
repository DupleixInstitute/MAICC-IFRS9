// Usage: node shot.cjs <cookie.json> <out.png> <path-or-url> [width]
// Full-page light-mode screenshot of the MAIIC app on http://127.0.0.1:8050, logged in.
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, out, target, width] = process.argv.slice(2);
const c = require(cookieFile);
const url = target.startsWith('http') ? target : 'http://127.0.0.1:8070/' + target.replace(/^\//, '');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: Number(width || 1440), height: 900 });
  await p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  const resp = await p.goto(url, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, Number(process.env.WAIT || 2500)));
  await p.screenshot({ path: out, fullPage: true });
  const h = await p.evaluate(() => document.body.scrollHeight);
  console.log(JSON.stringify({ url: p.url(), status: resp && resp.status(), pageHeight: h, errors: errs.slice(0, 8) }));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
