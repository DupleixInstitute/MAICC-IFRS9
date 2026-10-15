// node shot_import.cjs <path> <fileToChoose|-> <outPrefix> [clickCheck=1]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/c_fli.json');
const [target, file, out, check] = process.argv.slice(2);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  const resp = await p.goto('http://127.0.0.1:8071/' + target.replace(/^\//, ''), { waitUntil: 'networkidle0', timeout: 120000 });
  if (file !== '-') {
    const input = await p.$('input[type=file]');
    await input.uploadFile(file);
    await new Promise(r => setTimeout(r, 800));
    if (check !== '0') {
      const btns = await p.$$('button');
      for (const bt of btns) { const t = await p.evaluate(e => e.textContent.trim(), bt); if (t === 'Check file') { await bt.click(); break; } }
      await new Promise(r => setTimeout(r, 2500));
    }
  }
  for (const mode of ['light', 'dark']) {
    await p.evaluate(m => { document.documentElement.classList.toggle('dark', m === 'dark'); }, mode);
    await new Promise(r => setTimeout(r, 400));
    await p.screenshot({ path: `${out}_${mode}.png`, fullPage: true });
  }
  const text = await p.evaluate(() => document.body.innerText.slice(0, 400));
  console.log(JSON.stringify({ url: p.url(), status: resp && resp.status(), errors: errs.slice(0, 8) }));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
