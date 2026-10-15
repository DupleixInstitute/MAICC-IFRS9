module.exports = async (L, p, log) => {
  const act = process.env.ACT; const target = process.env.CID || '';
  await L.goto(p, '/eir-calculations' + (target ? '?contract_id=' + target : ''));
  log('kpis', (await L.mainText(p, 700)).slice(150, 700));
  L.resetLog(p);
  const title = act === 'calc' ? /^(Calculate|Recalculate|Retry calculation)$/ : /^Approve and lock$/;
  const r = await p.evaluate((src, target) => { const re = new RegExp(src); const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && re.test(b.title) && (!target || b.closest('tr').innerText.includes(target))); if (!b) return null; const row = b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 220); b.click(); return row; }, title.source, target);
  log(act, r);
  if (r) { const c = await L.confirmDlg(p); log('confirm', c); await L.idle(p, 4000); }
  log('flash', await L.flash(p), 'bad', p._log.bad);
  log('errors box', await p.evaluate(() => [...document.querySelectorAll('.text-red-600, .text-red-700')].filter(e => e.offsetParent).map(e => e.innerText).join(' | ').slice(0, 300)));
  await L.shot(p, 'B6_eircalc_' + act + '_' + (process.env.WHO || ''), false);
};
