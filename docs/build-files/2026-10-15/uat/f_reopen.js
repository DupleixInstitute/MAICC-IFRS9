module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-calculations?status=LOCKED');
  const r = await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && b.title === 'Reopen locked EIR'); if (!b) return null; const row = b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 160); b.click(); return row; });
  log('reopen', r); await L.sleep(500);
  await L.setVal(p, '#reopen-reason', 'UAT: controlled reopen to test the recalculate and lock maker-checker');
  L.resetLog(p);
  const b = await p.evaluate(() => { const bs = [...document.querySelectorAll('button')].filter(b => b.offsetParent && /Reopen/.test(b.textContent) && !b.title); const x = bs.pop(); if (!x) return null; x.click(); return x.textContent.trim(); });
  log('confirm', b); await L.idle(p, 2500);
  log('flash', await L.flash(p), p._log.bad);
};
