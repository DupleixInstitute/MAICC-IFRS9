// node admin_click.cjs <cookie.json> <out.png> <path> <button text> [second button text]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, out, target, text, text2] = process.argv.slice(2);
const c = require(cookieFile);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8070/' + target, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  for (const t of [text, text2].filter(Boolean)) {
    const ok = await p.evaluate((t) => {
      const el = [...document.querySelectorAll('button')].find(x => x.textContent.trim().startsWith(t));
      if (el) { el.click(); return true } return false
    }, t);
    if (!ok) errs.push('no button ' + t);
    await new Promise(r => setTimeout(r, 800));
  }
  await p.screenshot({ path: out, fullPage: true });
  console.log(JSON.stringify({ errors: errs }));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
