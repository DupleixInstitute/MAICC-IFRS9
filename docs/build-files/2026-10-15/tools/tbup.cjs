const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8072/eir-trial-balances', { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  await p.screenshot({ path: T + '../tb_index.png' });
  const btn = await p.$$('button'); for (const x of btn) { if ((await x.evaluate(e => e.textContent)).includes('Upload trial balance')) { await x.click(); break; } }
  await new Promise(r => setTimeout(r, 800));
  await p.screenshot({ path: T + '../tb_modal.png' });
  const input = await p.$('#tb-file'); await input.uploadFile(process.argv[2]);
  const sub = await p.$$('form button[type=submit]'); await sub[sub.length - 1].click();
  await new Promise(r => setTimeout(r, 15000));
  const flash = await p.evaluate(() => JSON.stringify(JSON.parse(document.getElementById('app').dataset.page || '{}').props?.flash || {}));
  console.log('flash(initial page):', flash);
  console.log(await p.evaluate(() => document.body.innerText.match(/(Trial balance[^\n]{0,200}|This file was already[^\n]{0,120}|refused[^\n]{0,200})/)?.[0] || 'no message text'));
  await p.screenshot({ path: T + '../tb_after.png' });
  console.log('errors:', errs.join(' | ').slice(0, 300));
  await b.close();
})();
