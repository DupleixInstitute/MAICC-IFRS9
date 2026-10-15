// node popshot.cjs <cookie.json> <out.png> <path> <buttonTitle> [rowIndex] [width]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, out, target, title, idx, width] = process.argv.slice(2);
const c = require(cookieFile);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: Number(width || 1440), height: 1000 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  await p.goto('http://127.0.0.1:8070/' + target, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  const btns = await p.$$(`button[title="${title}"]`);
  if (!btns.length) throw new Error('no button ' + title);
  await btns[Number(idx || 0)].click();
  await new Promise(r => setTimeout(r, 3000));
  await p.screenshot({ path: out });
  const txt = await p.evaluate(() => { const m = document.querySelector('.fixed.inset-0'); return m ? m.innerText.slice(0, 1500) : 'no modal'; });
  console.log(JSON.stringify({ errors: errs.slice(0, 8) }));
  console.log(txt);
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
