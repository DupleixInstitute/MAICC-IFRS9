const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  for (const u of process.argv.slice(2)) {
    const errs = []; const h = e => errs.push(e.message); p.on('pageerror', h);
    const r = await p.goto('http://127.0.0.1:8072' + u, { waitUntil: 'load', timeout: 150000 });
    await new Promise(r => setTimeout(r, 2500));
    const links = await p.evaluate(() => [...document.querySelectorAll('a')].filter(a => /eir-intake/.test(a.href)).map(a => a.innerText.trim().replace(/\s+/g, ' ') + ' -> ' + a.href.replace(location.origin, '') + (a.offsetParent ? '' : ' (hidden)')));
    console.log(u, r.status(), JSON.stringify([...new Set(links)]), errs.join('|'));
    p.off('pageerror', h);
  }
  await b.close();
})();
