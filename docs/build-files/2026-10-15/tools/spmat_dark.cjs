const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  for (const mode of ['dark']) {
    const p = await b.newPage(); await p.setViewport({ width: 1600, height: 1000 });
    await p.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: mode }]);
    const c = require(T + 'tools/c_fli.json');
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    await p.evaluateOnNewDocument((m) => { try { localStorage.setItem('maiic.theme', m) } catch (e) {} }, mode);
    await p.goto('http://127.0.0.1:8050/internal-grading', { waitUntil: 'load', timeout: 120000 });
    await new Promise(r => setTimeout(r, 15000));
    await p.evaluate(() => document.documentElement.classList.add('dark')); await new Promise(r => setTimeout(r, 500)); await p.screenshot({ path: T + `grades_list_${mode}.png` });
    await p.click('table.maiic-table tbody tr'); await new Promise(r => setTimeout(r, 3000));
    await p.evaluate(() => document.documentElement.classList.add('dark')); await p.screenshot({ path: T + `grades_matrix_${mode}.png` });
    await p.close();
  }
  await b.close();
})();
