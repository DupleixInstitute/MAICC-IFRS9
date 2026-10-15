const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  for (const w of [1707, 1280]) {
    const p = await b.newPage();
    await p.setViewport({ width: w, height: 900 });
    const errs = []; p.on('console', m => { if (m.type()==='error') errs.push(m.text()); }); p.on('pageerror', e => errs.push(e.message));
    await p.goto('http://127.0.0.1:8050/login', { waitUntil: 'load', timeout: 60000 });
    await new Promise(r => setTimeout(r, 2500));
    await p.screenshot({ path: process.argv[2] + '/login_' + w + '.png' });
    const vis = await p.evaluate(() => { const el = document.querySelector('h1'); return el ? getComputedStyle(el.closest('div.relative.hidden') || el).display + ' | ' + el.innerText.slice(0,30) : 'no h1'; });
    console.log(w, vis, JSON.stringify(errs.slice(0,3)));
  }
  await b.close();
})();
