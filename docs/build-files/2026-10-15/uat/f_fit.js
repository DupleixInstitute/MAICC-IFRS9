module.exports = async (L, p, log) => {
  const act = process.env.ACT; // propose|approve
  await L.goto(p, '/fli-adjustments?period=2026-09');
  log('kpis', (await L.mainText(p, 700)).slice(200, 700));
  L.resetLog(p);
  const title = act === 'propose' ? 'Propose this fit for use' : 'Approve this fit';
  const fit = process.env.FIT || '';
  for (let pg = 2; pg < 10; pg++) { const found = await p.evaluate((t, fit) => [...document.querySelectorAll('main button')].some(b => b.title === t && b.offsetParent && (!fit || new RegExp('^' + fit + '[^0-9]').test(b.closest('tr').innerText.trim()))), title, fit); if (found) break; const ok = await p.evaluate(n => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && b.textContent.trim() === String(n)); if (b) { b.click(); return true } return false; }, pg); if (!ok) break; await L.sleep(400); }
  const r = await p.evaluate((t, fit) => { const b = [...document.querySelectorAll('main button')].find(b => b.title === t && b.offsetParent && (!fit || new RegExp('^' + fit + '[^0-9]').test(b.closest('tr').innerText.trim()))); if (!b) return null; const row = b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 200); b.click(); return row; }, title, fit);
  log(act, r);
  if (r) { log('confirm', await L.confirmDlg(p)); await L.idle(p, 3000); }
  log('flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B4_fit_' + act + '_' + (process.env.WHO || ''), false);
};
