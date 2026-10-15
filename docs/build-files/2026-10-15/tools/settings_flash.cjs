const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.argv[2]); const dir = process.argv[3];
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1400, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8070/setting?tab=organisation', { waitUntil: 'load', timeout: 280000 });
  await new Promise(r => setTimeout(r, 2000));
  // validation error: blank name (required removed so the server answers)
  await p.evaluate(() => { const i = document.querySelector('#company_name'); i.removeAttribute('required'); i.value=''; i.dispatchEvent(new Event('input')); const e = document.querySelector('#company_email'); e.type='text'; e.value='not-an-email'; e.dispatchEvent(new Event('input')); });
  const v = await p.evaluate(() => document.querySelector('#company_name').value);
  if (v !== '') { console.log('abort: name not blank'); process.exit(1); }
  await p.click('form button[type=submit]');
  await new Promise(r => setTimeout(r, 9000));
  await p.screenshot({ path: dir + '/after_validation_error.png' });
  console.log(JSON.stringify({ errs })); await b.close(); return;
  // success: save the reporting currency unchanged
  await p.goto('http://127.0.0.1:8070/setting?tab=reporting', { waitUntil: 'load', timeout: 280000 });
  await new Promise(r => setTimeout(r, 2000));
  await p.click('form button[type=submit]');
  await new Promise(r => setTimeout(r, 9000));
  await p.screenshot({ path: dir + '/after_success_flash.png' });
  // confirm dialog smoke test: mount through the module is not exposed, so only page errors are checked
  console.log(JSON.stringify({ url: p.url(), errs }));
  await b.close();
})();
