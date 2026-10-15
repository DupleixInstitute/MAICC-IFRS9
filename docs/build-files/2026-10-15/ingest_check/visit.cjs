// node visit.cjs url1 url2 ... ; env DARK=1 sets html.dark; SHOT=1 screenshot
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  for (const u of process.argv.slice(2)) {
    const errs = [];
    const h1 = e => errs.push('PAGEERR ' + e.message);
    const h2 = m => { if (m.type() === 'error') errs.push('CONSOLE ' + m.text()); };
    const h3 = r => { if (r.status() >= 400) errs.push('HTTP ' + r.status() + ' ' + r.url()); };
    p.on('pageerror', h1); p.on('console', h2); p.on('response', h3);
    const resp = await p.goto('http://127.0.0.1:8072' + u, { waitUntil: 'load', timeout: 120000 });
    await new Promise(r => setTimeout(r, 3000));
    const info = await p.evaluate(() => {
      const el = document.getElementById('app');
      let comp = ''; try { comp = JSON.parse(el.dataset.page).component } catch (e) {}
      const btns = [...document.querySelectorAll('main button, main a, button, a')].map(x => (x.innerText || '').trim()).filter(t => t && t.length < 40);
      const h = [...document.querySelectorAll('h1,h2')].map(x => x.innerText.trim()).slice(0, 4);
      return { comp, title: h, url: location.pathname + location.search, btns: [...new Set(btns)].filter(t => /import|upload|sample|template|download|intake|load|new|add|export|preview/i.test(t)).slice(0, 30), rows: document.querySelectorAll('tbody tr').length, empty: [...document.querySelectorAll('main')].map(m => m.innerText.length)[0] };
    });
    console.log(JSON.stringify({ u, status: resp.status(), ...info, errs: errs.slice(0, 8) }));
    if (process.env.SHOT) await p.screenshot({ path: T + 'ingest_check/v_' + u.replace(/[^a-z0-9]/gi, '_').slice(0, 60) + '.png' });
    p.off('pageerror', h1); p.off('console', h2); p.off('response', h3);
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
