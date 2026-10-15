// node multi.cjs <list.json>  list: [[name, path], ...]  -> screenshots <name>.png + metrics
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const D = __dirname + '/';
const c = require(D + '../../c_auditB.json');
const list = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  let errs = [], bad = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 200)));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 200)); });
  p.on('response', r => { if (r.status() >= 400) bad.push(r.status() + ' ' + r.url()); });
  for (const [name, path] of list) {
    errs = []; bad = [];
    let resp;
    for (let attempt = 0; attempt < 4; attempt++) {
      errs = []; bad = [];
      try { resp = await p.goto('http://127.0.0.1:8072/' + path.replace(/^\//, ''), { waitUntil: 'networkidle2', timeout: 180000 }); }
      catch (e) { console.log(name + ' GOTO FAIL ' + e.message); }
      await new Promise(r => setTimeout(r, Number(process.env.WAIT || 1500)));
      const ok = await p.evaluate(() => document.body.scrollHeight > 50 && !!document.querySelector('main,form,#app *'));
      if (ok && !errs.some(e => e.includes('CONNECTION_RESET') || e.includes('preload'))) break;
    }
    if (!process.env.NOSHOT) await p.screenshot({ path: D + name + '.png', fullPage: true });
    const m = await p.evaluate(() => {
      const hdr = document.querySelector('main header');
      const title = hdr ? hdr.innerText.split('\n').slice(0, 2).join(' / ') : (document.querySelector('main h1, main h2') || {}).innerText;
      const actWrap = hdr ? hdr.querySelector('.flex-none') : null;
      const actions = actWrap ? [...actWrap.querySelectorAll('a,button,select,input')].map(e => (e.innerText || e.value || e.title || e.tagName).trim().slice(0, 30)) : null;
      const tables = [...document.querySelectorAll('main table')].map(t => t.querySelectorAll('tbody tr').length);
      const pagers = document.querySelectorAll('main nav[aria-label="Pages"], main nav[aria-label*="agin"], main .pagination').length;
      const txt = document.body.innerText;
      const flags = ['Server Error', 'Exception', 'undefined', 'NaN', 'Not Found', 'Forbidden', 'Infinity', '[object'].filter(f => txt.includes(f));
      const tabs = [...document.querySelectorAll('nav[aria-label="Section"] a')].map(a => a.innerText.replace(/\s+/g, ' ').trim());
      const alertsUsed = null;
      return { title, actions, tables, pagers, flags, tabs, h: document.body.scrollHeight };
    });
    console.log(name + ' ' + JSON.stringify({ status: resp && resp.status(), url: p.url().replace('http://127.0.0.1:8072', ''), ...m, errs: errs.slice(0, 5), bad: bad.slice(0, 5) }));
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
