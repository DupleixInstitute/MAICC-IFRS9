// Interaction checks: collapse button, group click on the rail, keyboard, and
// the actions row after a client-side tab switch.
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
const sleep = ms => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1366, height: 768 });
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  const state = () => p.evaluate(() => {
    const side = document.querySelector('.md\\:fixed.md\\:inset-y-0');
    const btn = [...document.querySelectorAll('button[aria-label]')].find(x => /the menu$/.test(x.getAttribute('aria-label')) && /Collapse|Expand/.test(x.getAttribute('aria-label')));
    const mainCol = side && side.nextElementSibling;
    let ls = null; try { ls = localStorage.getItem('maiic.sidebar.collapsed'); } catch (e) {}
    return { sideW: side && Math.round(side.getBoundingClientRect().width), contentLeft: mainCol && Math.round(mainCol.querySelector('main').getBoundingClientRect().left), btn: btn && btn.getAttribute('aria-label'), expanded: btn && btn.getAttribute('aria-expanded'), ls, scroll: document.documentElement.scrollWidth };
  });
  await p.goto('http://127.0.0.1:8071/eir-trial-balances', { waitUntil: 'load', timeout: 180000 }); await sleep(2500);
  await p.evaluate(() => { try { localStorage.removeItem('maiic.sidebar.collapsed'); } catch (e) {} });
  await p.reload({ waitUntil: 'load' }); await sleep(2500);
  console.log('initial', JSON.stringify(await state()));
  // collapse with the keyboard: focus the button and press Enter
  await p.evaluate(() => [...document.querySelectorAll('button[aria-label="Collapse the menu"]')].find(x => x.offsetParent).focus());
  await p.keyboard.press('Enter'); await sleep(500);
  console.log('after Enter', JSON.stringify(await state()));
  const titles = await p.evaluate(() => [...document.querySelectorAll('nav[aria-label="Main menu"] [title]')].filter(x => x.offsetParent).map(x => x.getAttribute('title')).slice(0, 20));
  console.log('rail tooltips', titles.join(' | '));
  await p.reload({ waitUntil: 'load' }); await sleep(2500);
  console.log('after reload (remembered)', JSON.stringify(await state()));
  // click a group on the rail: the sidebar opens with the group open
  await p.evaluate(() => [...document.querySelectorAll('nav[aria-label="Main menu"] button[title="IFRS 9 Model Setup"]')].find(x => x.offsetParent).click());
  await sleep(500);
  const opened = await p.evaluate(() => { const b = [...document.querySelectorAll('nav[aria-label="Main menu"] button')].find(x => x.offsetParent && x.textContent.trim() === 'IFRS 9 Model Setup'); return b ? b.getAttribute('aria-expanded') : 'not found'; });
  console.log('rail group click', JSON.stringify(await state()), 'IFRS 9 Model Setup aria-expanded=' + opened);

  // actions row after an in-page tab switch on a report
  await p.goto('http://127.0.0.1:8071/ifrs9-reports/sector-ecl', { waitUntil: 'load', timeout: 180000 }); await sleep(2500);
  const where = () => p.evaluate(() => { const r = document.querySelector('[data-actions-row]'); const n = r && r.nextElementSibling; return r ? { kids: r.children.length, next: n && (n.tagName + '.' + String(n.className).slice(0, 24)), hasTable: !!(n && n.querySelector('table')) } : null; });
  console.log('report before tab switch', JSON.stringify(await where()));
  await p.evaluate(() => { const t = [...document.querySelectorAll('button')].find(x => x.offsetParent && /^PD by portfolio/.test(x.textContent.trim())); if (t) t.click(); });
  await sleep(1500);
  console.log('report after tab switch', JSON.stringify(await where()));
  // the moved buttons still work: the CSV link keeps its href
  console.log('report actions', await p.evaluate(() => [...document.querySelectorAll('[data-actions-row] a, [data-actions-row] button')].map(x => x.textContent.trim() + (x.getAttribute('href') ? ' -> ' + x.getAttribute('href').slice(0, 50) : '')).join(' | ')));
  console.log('errors', errs.length ? errs.join(' | ').slice(0, 500) : 'none');
  await p.evaluate(() => { try { localStorage.setItem('maiic.sidebar.collapsed', '0'); } catch (e) {} });
  await b.close();
})();
