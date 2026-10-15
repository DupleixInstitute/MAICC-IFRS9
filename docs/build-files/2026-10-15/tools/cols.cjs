const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:' + (process.env.PORT || 8072) + process.argv[2], { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2500));
  const idx = Number(process.argv[3] || 0);
  console.log(await p.evaluate((i) => { const t = document.querySelectorAll('table')[i]; return [...t.querySelectorAll('thead th')].map(th => th.textContent.trim().slice(0, 20) + ':' + Math.round(th.getBoundingClientRect().width)).join(' | ') }, idx));
  const el = (await p.$$('table'))[idx]; await el.screenshot({ path: T + '../tbl.png' });
  await b.close();
})();
