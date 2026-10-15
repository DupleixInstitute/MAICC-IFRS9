// node click.cjs <cookie.json> <out.png> <path> <mode> [file]
// mode: "click:<button text>" clicks the first button/a with that text; "upload" sets the first file input to [file].
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [cookieFile, out, target, mode, file] = process.argv.slice(2);
const c = require(cookieFile);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  await p.goto('http://127.0.0.1:8070/' + target, { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 2000));
  for (const step of mode.split('|')) {
    if (step.startsWith('click:')) {
      const text = step.slice(6);
      await p.evaluate((t) => {
        const el = [...document.querySelectorAll('button, a')].filter(e => e.textContent.trim().startsWith(t))[2];
        if (el) el.click(); else throw new Error('no button ' + t);
      }, text);
    } else if (step === 'upload') {
      const input = (await p.$$('input[type=file]'))[Number(process.env.IDX||0)];
      await input.uploadFile(file);
    }
    await new Promise(r => setTimeout(r, 1200));
  }
  await p.screenshot({ path: out, fullPage: true });
  console.log(JSON.stringify({ errors: errs.slice(0, 8) }));
  await b.close();
})();
