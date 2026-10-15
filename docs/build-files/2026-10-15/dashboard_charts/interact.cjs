// Change the "ECL by portfolio" From select in the browser and check a partial reload happens
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/c_fli.json');
const OUT = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/dashboard_charts/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1366, height: 900 });
  await p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.evaluateOnNewDocument(() => { try { localStorage.setItem('maiic.theme', 'light') } catch (e) {} });
  const errs = []; const partials = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  p.on('request', r => { const h = r.headers(); if (h['x-inertia-partial-data']) partials.push(h['x-inertia-partial-data'] + ' ' + r.url()); });
  for (let a = 1; a <= 4; a++) {
    try { const r = await p.goto('http://127.0.0.1:8072/dashboard', { waitUntil: 'load', timeout: 180000 }); if (r.status() === 200 && await p.$('canvas')) break } catch (e) { console.log('retry', e.message) }
    await sleep(5000)
  }
  await sleep(15000);
  const pick = async (title, h3, value) => p.evaluate((h3, title, value) => {
    const panel = [...document.querySelectorAll('h3')].find(h => h.textContent.trim().startsWith(h3))?.closest('.maiic-panel');
    const sel = panel && [...panel.querySelectorAll('select')].find(s => s.title === title);
    if (!sel) return 'no select';
    sel.value = value; sel.dispatchEvent(new Event('change'));
    return 'ok';
  }, h3, title, value);
  const before = await p.evaluate(() => window.scrollY);
  await p.evaluate(() => window.scrollTo(0, 2000));
  console.log('pf_from', await pick('First period of this chart', 'ECL by portfolio', 'all'));
  await sleep(15000);
  console.log('st_from', await pick('First period of this chart', 'Base and stressed', 'all'));
  await sleep(15000);
  console.log('stage', await pick('The stage the bars show', 'Base and stressed', '0'));
  await sleep(2000);
  const after = await p.evaluate(() => ({ y: window.scrollY, url: location.search, captions: [...document.querySelectorAll('h3')].map(h => h.textContent.trim() + ' | ' + (h.nextElementSibling?.textContent || '').trim().slice(0, 110)) }));
  await p.screenshot({ path: OUT + 'dashboard_ranges_interaction_1366_light.png', fullPage: true });
  console.log(JSON.stringify({ partials, scrollAfter: after.y, url: after.url, captions: after.captions, errors: errs }, null, 1));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
