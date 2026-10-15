const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()) });
  for (const [u, f] of process.argv.slice(2).map(x => x.split('|'))) {
    await p.goto('http://127.0.0.1:8070' + u, { waitUntil: 'networkidle2', timeout: 120000 });
    await new Promise(r => setTimeout(r, 800));
    await p.screenshot({ path: T + '../' + f, fullPage: false });
    console.log(u, p.url(), errs.splice(0).join(' | ').slice(0, 300));
  }
  await b.close();
})();
