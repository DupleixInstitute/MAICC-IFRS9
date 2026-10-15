// node expand.cjs <cookie.json> <out.png> <path> <selector> <indexes comma>
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, out, target, sel, idx] = process.argv.slice(2);
const c = require(cookieFile);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8070/' + target, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  for (const i of String(idx || '').split(',').filter(Boolean)) {
    await p.evaluate((sel, i) => { const els = [...document.querySelectorAll(sel)]; if (els[i]) els[i].click(); }, sel, Number(i));
    await new Promise(r => setTimeout(r, 500));
  }
  await p.screenshot({ path: out, fullPage: true });
  console.log(JSON.stringify({ errors: errs }));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
