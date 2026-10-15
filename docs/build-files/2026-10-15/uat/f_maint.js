// env: MTAB (tab label start), MCARD (card label), MPERIOD (optional YYYY-MM), MTAG
module.exports = async (L, p, log) => {
  await L.goto(p, '/maintenance');
  log('db', await p.evaluate(() => { const m = document.body.innerText.match(/maiic_ifrs9\w*/); return m && m[0]; }));
  const tabs = await L.texts(p, 'main nav button'); log('tabs', tabs);
  const t = tabs.find(x => x.startsWith(process.env.MTAB)); await L.clickText(p, t, 'main nav button'); await L.sleep(500);
  if (process.env.MPERIOD) log('period', await L.setVal(p, '#m-period', process.env.MPERIOD), await p.evaluate(() => document.querySelector('#m-period').value));
  const ok = await p.evaluate(label => { const card = [...document.querySelectorAll('main h3')].find(h => h.textContent.trim() === label); if (!card) return 'no card'; const box = card.closest('div.flex.flex-col'); box.querySelector('code') && (window.__cmd = box.querySelector('code').textContent); const b = [...box.querySelectorAll('button')].find(b => /Run/.test(b.textContent)); if (!b || b.disabled) return 'run disabled'; b.click(); return 'clicked'; }, process.env.MCARD);
  log('run', ok, await p.evaluate(() => window.__cmd));
  log('confirm', await L.confirmDlg(p, 'Run'));
  const t0 = Date.now(); let out = '';
  const D = 'C:/xampp/htdocs/MAICC-IFRS9/storage/app/command-runs/';
  await L.sleep(3000);
  const runId = L.fs.readdirSync(D).filter(f => f.endsWith('.log')).sort().pop().replace('.log', '');
  log('runId', runId);
  for (let i = 0; i < 1200; i++) {
    await L.sleep(5000);
    const logTxt = L.fs.readFileSync(D + runId + '.log', 'utf8');
    if (/===EXIT/.test(logTxt)) { out = logTxt; break; }
    if (i % 24 === 0) log('...', ((Date.now() - t0) / 1000) + 's', logTxt.split('\n').filter(l => /Step \d+ of/.test(l)).slice(-1)[0]);
  }
  await L.sleep(6000);
  log('ui', (await p.evaluate(() => { const pre = document.querySelector('pre'); return pre ? pre.innerText : ''; })).slice(-400));
  log('secs', (Date.now() - t0) / 1000);
  log('output tail', out.slice(-3000));
  await L.shot(p, (process.env.MTAG || 'maint') + '_run', false);
};
