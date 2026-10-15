// usage: node shot.cjs <width> <light|dark> <collapsed 0|1> name=/url [name=/url ...]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
const [width, theme, collapsed, ...pairs] = process.argv.slice(2);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: Number(width), height: Number(process.env.H || (Number(width) > 1500 ? 1080 : 768)) });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.evaluateOnNewDocument((theme, collapsed) => {
    try { localStorage.setItem('maiic.theme', theme); localStorage.setItem('maiic.sidebar.collapsed', collapsed === '1' ? '1' : '0'); } catch (e) {}
  }, theme, collapsed);
  for (const pr of pairs) { const i = pr.indexOf('='); const prefix = pr.slice(0, i), u = pr.slice(i + 1);
    const errs = [];
    const h = e => errs.push('pageerror: ' + e.message);
    const hc = m => { if (m.type() === 'error') errs.push('console: ' + m.text()); };
    p.on('pageerror', h); p.on('console', hc);
    try { await p.goto('http://127.0.0.1:8071' + u, { waitUntil: 'load', timeout: 180000 }); } catch (e) { console.log(prefix, u, 'GOTO FAILED', e.message); p.off('pageerror', h); p.off('console', hc); continue; }
    await new Promise(r => setTimeout(r, 3000));
    // a reset connection from the single-threaded test server: load the page once more
    if (errs.some(e => /ERR_CONNECTION_RESET/.test(e))) { errs.length = 0; try { await p.reload({ waitUntil: 'load', timeout: 180000 }); } catch (e) {} await new Promise(r => setTimeout(r, 3000)); }
    if (process.env.OPENALL === '1') { await p.evaluate(() => document.querySelectorAll('nav[aria-label="Main menu"] button[aria-controls^="menu-block-"][aria-expanded="false"]').forEach(x => x.click())); await new Promise(r => setTimeout(r, 400)); }
    if (process.env.OPENADMIN === '1') { await p.evaluate(() => { const blocks = document.querySelectorAll('nav[aria-label="Main menu"] [id^="menu-block-"]'); const last = blocks[blocks.length - 1]; if (last) last.querySelectorAll('button[aria-expanded="false"]').forEach(x => x.click()); }); await new Promise(r => setTimeout(r, 400)); }
    // app.blade.php pins the app to light; the dark palette is checked by setting the class
    if (theme === 'dark') { await p.evaluate(() => document.documentElement.classList.add('dark')); await new Promise(r => setTimeout(r, 600)); }
    const r = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, dark: document.documentElement.classList.contains('dark'), act: (() => { const r = document.querySelector('[data-actions-row]'); if (!r) return 'none'; return 'n=' + r.children.length + ' top=' + Math.round(r.getBoundingClientRect().top + scrollY) + ' before=' + ((r.nextElementSibling && (r.nextElementSibling.tagName + '.' + String(r.nextElementSibling.className).slice(0, 30))) || 'end'); })() }));
    const name = prefix + '_' + width + '_' + theme + (collapsed === '1' ? '_collapsed' : '') + '.png';
    await p.screenshot({ path: T + 'ui_breadcrumbs/' + name, fullPage: process.env.FULL === '1' });
    console.log(name, u, JSON.stringify(r), errs.length ? errs.join(' | ').slice(0, 400) : 'no errors');
    p.off('pageerror', h); p.off('console', hc);
  }
  await b.close();
})();
