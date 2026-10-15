const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.env.SP + '/cookie.json');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  for (const mode of ['light']) {
    const p = await b.newPage();
    await p.setViewport({ width: 1500, height: 900 });
    await p.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: mode }, { name: 'prefers-reduced-motion', value: 'reduce' }]);
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    await p.goto('' + (process.env.URL || 'http://127.0.0.1:8010/dashboard') + '', { waitUntil: 'load', timeout: 60000 });
    await new Promise(r => setTimeout(r, 4000));
    await p.screenshot({ path: process.env.SP + `/dash_${mode}.png`, fullPage: true });
    console.log(mode, p.url(), errs.slice(0, 5));
  }
  await b.close();
})();
