// node ev.cjs <path> <js-expression-file-or-string>
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const fs = require('fs');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const c = require(T + 'c_auditA.json');
  for (let a = 0; a < 4; a++) {
    const p = await b.newPage();
    await p.setViewport({ width: 1440, height: 900 });
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    try {
      await p.goto('http://127.0.0.1:8071/' + process.argv[2], { waitUntil: 'networkidle2', timeout: 90000 });
      await new Promise(r => setTimeout(r, 2500));
      const expr = fs.existsSync(process.argv[3]) ? fs.readFileSync(process.argv[3], 'utf8') : process.argv[3];
      const r = await p.evaluate(expr);
      console.log(typeof r === 'string' ? r : JSON.stringify(r, null, 1));
      break;
    } catch (e) { console.log('retry', e.message.slice(0, 100)); }
    await p.close();
  }
  await b.close();
})();
