// node shoot.cjs <query> <tag> [clickBreakdownChart]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/c_fli.json');
const OUT = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/dashboard_charts/';
const [query = '', tag = 'default', clickChart = ''] = process.argv.slice(2);
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  for (const width of [1366, 1920]) {
    for (const mode of ['light', 'dark']) {
      const p = await b.newPage();
      await p.setViewport({ width, height: 900 });
      await p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
      await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
      await p.evaluateOnNewDocument((m) => {
        try { localStorage.setItem('maiic.theme', m) } catch (e) {}
        document.addEventListener('DOMContentLoaded', () => document.documentElement.classList.toggle('dark', m === 'dark'))
      }, mode);
      const errs = [];
      p.on('pageerror', e => errs.push('pageerror: ' + e.message));
      p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
      let ok = false;
      for (let attempt = 1; attempt <= 4 && !ok; attempt++) {
        try {
          const resp = await p.goto('http://127.0.0.1:8072/dashboard' + (query ? '?' + query : ''), { waitUntil: 'load', timeout: 180000 });
          ok = resp && resp.status() === 200 && !!(await p.waitForSelector('canvas', { timeout: 90000 }).catch(() => null)) && !!(await p.evaluate(() => [...document.styleSheets].length > 0 && !!document.querySelector('.maiic-panel') && getComputedStyle(document.querySelector('.maiic-panel')).borderRadius !== '0px'));
          if (!ok) { console.log('attempt', attempt, 'status', resp && resp.status(), p.url()); await sleep(5000); }
        } catch (e) { console.log('attempt', attempt, e.message); await sleep(5000); }
      }
      await sleep(20000);
      if (clickChart) {
        await p.evaluate(() => {
          const panel = [...document.querySelectorAll('h3')].find(h => h.textContent.trim() === 'Where the ECL sits')?.closest('.maiic-panel');
          const btn = panel && [...panel.querySelectorAll('button')].find(x => x.textContent.trim() === 'Chart');
          if (btn) btn.click();
        });
        await sleep(2500);
      }
      const dark = await p.evaluate(() => document.documentElement.classList.contains('dark'));
      const file = OUT + `dashboard_${tag}_${width}_${mode}.png`;
      await p.screenshot({ path: file, fullPage: true });
      console.log(JSON.stringify({ file, ok, dark, errors: errs.slice(0, 6) }));
      await p.close();
    }
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
