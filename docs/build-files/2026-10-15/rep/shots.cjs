const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const SP = process.env.SP;
const c = require(SP + '/cookie.json');
const base = 'http://127.0.0.1:8099';
const pages = [
  ['note', '/ifrs9-reports/fs-disclosure?opening=2025-12&closing=2026-09&unit=m'],
  ['rbm', '/rbm-return?period=2026-09'],
  ['lbr', '/report/loan-book-reconciliation?portfolio_id=4&start_period=2026-05&end_period=2026-06'],
  ['charge', '/ifrs9-reports/ecl-charge?period=2026-06'],
  ['prov', '/ifrs9-reports/provision-comparison?period=2026-09'],
];
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  for (const [name, path] of pages) {
    for (const mode of ['light', 'dark']) {
      const p = await b.newPage();
      await p.setViewport({ width: 1500, height: 900 });
      await p.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: mode }, { name: 'prefers-reduced-motion', value: 'reduce' }]);
      await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
      await p.evaluateOnNewDocument((m) => { try { localStorage.setItem('maiic.theme', m); } catch (e) {} }, mode);
      const errs = [];
      p.on('pageerror', e => errs.push(e.message));
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      const resp = await p.goto(base + path, { waitUntil: 'networkidle0', timeout: 180000 });
      await new Promise(r => setTimeout(r, 1500));
      if (mode === 'dark') { await p.evaluate(() => document.documentElement.classList.add('dark')); await new Promise(r => setTimeout(r, 500)); }
      await p.screenshot({ path: SP + `/shots/${name}_${mode}.png`, fullPage: true });
      const dark = await p.evaluate(() => document.documentElement.classList.contains('dark'));
      console.log(name, mode, resp.status(), p.url().slice(0, 90), 'dark=' + dark, errs.slice(0, 3));
      await p.close();
    }
  }
  await b.close();
})();
