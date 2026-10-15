module.exports = async (L, p, log) => {
  const act = process.env.ACT;
  const t0 = Date.now();
  await L.goto(p, '/eir-write-offs');
  log('load secs', (Date.now() - t0) / 1000, (await L.mainText(p, 600)).slice(150, 600));
  L.resetLog(p);
  if (act === 'propose') {
    await L.clickText(p, 'Propose write-off', 'button'); await L.sleep(600);
    const c = await p.evaluate((woc) => { const s = document.querySelector('#wo-contract'); const o = [...s.options].find(o => o.value && new RegExp(woc).test(o.value + o.text)) || s.options[1]; s.value = o.value; s.dispatchEvent(new Event('change')); return o.text; }, process.env.WOC || '104430000070');
    log('contract', c);
    await L.setVal(p, '#wo-date', process.env.WODATE || '2026-09-30'); await L.setVal(p, '#wo-amount', '1000000');
    await L.setVal(p, '#wo-reason', 'UAT test: uncollectable balance after recovery exhausted');
    await L.setVal(p, '#wo-evidence', 'UAT test: board minute reference for the test only'); await L.setVal(p, '#wo-gl', 'GL 1064 test');
    await L.shot(p, 'B7_wo_form', false);
    await p.evaluate(() => { const b = [...document.querySelectorAll('button[type=submit]')].filter(b => b.offsetParent).find(b => b.textContent.trim() === 'Propose'); b.click(); });
  } else {
    const r = await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && b.title === 'Approve'); if (!b) return null; const row = b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 200); b.click(); return row; });
    log('approve', r); if (r) log('confirm', await L.confirmDlg(p));
  }
  await L.idle(p, 4000);
  log('flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B7_wo_' + act + '_' + (process.env.WHO || ''), false);
};
