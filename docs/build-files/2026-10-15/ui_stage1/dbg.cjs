const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1366, height: 768 });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8071' + process.argv[2], { waitUntil: 'load', timeout: 180000 });
  await new Promise(r => setTimeout(r, 3000));
  console.log(await p.evaluate(() => { const r = document.querySelector('[data-actions-row]'); const a = document.querySelector('[data-actions-anchor]'); return JSON.stringify({ row: !!r, rowParent: r && r.parentElement && r.parentElement.className, next: r && r.nextElementSibling && r.nextElementSibling.outerHTML.slice(0, 80), anchor: !!a, tables: document.querySelectorAll('main table').length, wraps: document.querySelectorAll('main .maiic-table-wrap').length }); }));
  await b.close();
})();
