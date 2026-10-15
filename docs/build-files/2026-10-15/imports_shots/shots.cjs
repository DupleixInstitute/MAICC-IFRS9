// node shots.cjs <scenario> <out prefix>
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/c_fli.json');
const D = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/imports_shots/';
const [scenario, out] = process.argv.slice(2);
const wait = ms => new Promise(r => setTimeout(r, ms));
async function clickText(p, text) {
  const ok = await p.evaluate(t => { const el = [...document.querySelectorAll('button, a')].find(e => e.textContent.trim() === t && e.offsetParent !== null); if (el) { el.click(); return true } return false }, text);
  if (!ok) throw new Error('no button ' + text);
}
const S = {
  loanbook: { url: 'loan_application/loan-books/import', run: async p => { await wait(2500); const n = await p.$$eval('input[type=file]', a => a.length); if (!n) { console.log('inputs', n, (await p.evaluate(() => document.body.innerText.slice(0, 300)))); return } await (await p.$('input[type=file]')).uploadFile(D + 'bad_loanbook.csv'); await wait(600); await clickText(p, 'Check file'); await wait(2500) } },
  eirfees: { url: 'eir-intake?type=fees', run: async p => { await (await p.$('input[type=file]')).uploadFile(D + 'bad_fees.csv'); await wait(600); await clickText(p, 'Import file'); await wait(3500) } },
  tb: { url: 'eir-trial-balances', run: async p => { await clickText(p, 'Upload trial balance'); await wait(800); await (await p.$('input[type=file]')).uploadFile('C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/trial-balances/monthly/Trial Balance_31 December 2025.xls'); await wait(600); await clickText(p, 'Check file'); await wait(4000) }, modal: true },
  sicr: { url: 'sicr-items', run: async p => { await clickText(p, 'Import CSV'); await wait(800); await (await p.$('input[type=file]')).uploadFile(D + 'bad_sicr_items.csv'); await wait(600); await clickText(p, 'Check file'); await wait(2500) }, modal: true },
  macro: { url: 'macro-statistics?tab=import', run: async p => { await clickText(p, 'Import'); await wait(800); const inputs = await p.$$('input[type=file]'); await inputs[0].uploadFile(D + 'bad_macro.csv'); await wait(400); await clickText(p, 'Preview'); await wait(2500) }, modal: true },
  takeon: { url: 'eir-takeon', run: async p => { await clickText(p, 'Load take-on workbook'); await wait(1500) }, modal: true },
  pack: { url: 'eir-feed?tab=loads', run: async p => { await clickText(p, 'Load a pack'); await wait(1500) }, modal: true },
  sp: { url: 'internal-grading', run: async p => { const ok = await p.evaluate(() => { const el = [...document.querySelectorAll('button')].find(e => /S&P/.test(e.textContent)); if (el) { el.click(); return el.textContent.trim() } return null }); if (!ok) throw new Error('no S&P button'); await wait(1500) }, modal: true },
  clients: { url: 'client/import', run: async p => { await wait(500) } },
  cld: { url: 'credit-loss-data/import', run: async p => { await wait(500) } },
};
(async () => {
  const s = S[scenario];
  const b = await puppeteer.launch({ headless: 'new' });
  const res = {};
  for (const mode of ['light', 'dark']) {
    const p = await b.newPage();
    await p.setViewport({ width: 1440, height: 900 });
    await p.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: mode }]);
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    const errs = [];
    p.on('pageerror', e => errs.push('pageerror: ' + e.message));
    p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 200)); });
    await p.evaluateOnNewDocument(m => { try { localStorage.setItem('theme', m) } catch (e) {} }, mode);
    const resp = await p.goto('http://127.0.0.1:8071/' + s.url, { waitUntil: 'networkidle0', timeout: 120000 });
    await p.evaluate(m => document.documentElement.classList.toggle('dark', m === 'dark'), mode);
    await s.run(p);
    await p.evaluate(m => document.documentElement.classList.toggle('dark', m === 'dark'), mode);
    await wait(300);
    await p.screenshot({ path: `${out}_${mode}.png`, fullPage: !s.modal });
    res[mode] = { status: resp && resp.status(), errors: errs.slice(0, 5), dark: await p.evaluate(() => document.documentElement.classList.contains('dark')) };
    await p.close();
  }
  console.log(scenario, JSON.stringify(res));
  await b.close();
})().catch(e => { console.error(scenario, e.message); process.exit(1); });
