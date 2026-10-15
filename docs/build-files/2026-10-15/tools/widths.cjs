const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  for (const u of process.argv.slice(2)) {
    const errs = []; const h = e => errs.push(e.message); p.on('pageerror', h);
    await p.goto('http://127.0.0.1:' + (process.env.PORT || 8070) + u, { waitUntil: 'load', timeout: 120000 });
    await new Promise(r => setTimeout(r, 2500));
    const r = await p.evaluate(() => 'h=' + document.body.scrollHeight + ' ' + [...document.querySelectorAll('table')].map(t => { const w = t.parentElement; return `${t.scrollWidth}/${w.clientWidth}` }).join(' ') + ' tabs:' + [...document.querySelectorAll('nav')].map(n => n.scrollWidth > n.clientWidth ? 'OVER' + n.scrollWidth + '/' + n.clientWidth : 'ok').join(','));
    console.log(u, r, errs.join('|').slice(0, 200));
    p.off('pageerror', h);
    if (process.env.SHOT) await p.screenshot({ path: T + '../w_' + u.replace(/[^a-z0-9]/gi, '_').slice(0, 40) + '.png' });
  }
  await b.close();
})();
