const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('response', async r => { if (r.url().includes('import-sp')) { let t=''; try { t = (await r.text()).slice(0,300) } catch(e){}; console.log('RESP', r.status(), r.url(), t) } }); p.on('requestfailed', r => console.log('FAIL', r.url(), r.failure()?.errorText)); p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8072/internal-grading', { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 5000));
  const click = async (txt) => { for (const x of await p.$$('button')) { if ((await x.evaluate(e => e.textContent)).includes(txt)) { await x.click(); return true } } return false };
  console.log('open', await click('Import S&P table'));
  await new Promise(r => setTimeout(r, 600));
  const input = await p.$('input[type=file][accept=".xlsx,.xlsm"]'); await input.uploadFile(require('path').resolve(T, '..', 'ecl_dec2025.xlsx'));
  await click('Preview'); await new Promise(r => setTimeout(r, 12000));
  await p.screenshot({ path: T + '../sp_preview.png' });
  console.log('rows', await p.evaluate(() => document.querySelectorAll('.fixed table tbody tr').length));
  //
  await p.screenshot({ path: T + '../sp_after.png' });
  console.log('errors', errs.join('|').slice(0, 300));
  await b.close();
})();
