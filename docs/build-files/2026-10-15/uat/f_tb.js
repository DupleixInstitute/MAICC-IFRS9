module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-trial-balances');
  log('kpis', await L.mainText(p, 500));
  await L.clickText(p, 'Upload trial balance', 'button'); await L.sleep(700);
  const s = await L.hrefOf(p, 'Download sample'); if (s) log('sample', await L.fetchTo(p, s, null));
  const inp = await p.$('#tb-file'); await inp.uploadFile('C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/trial-balances/monthly/Trial Balance_31 August 2026.xls'); await L.sleep(500);
  log('check', await L.clickText(p, 'Check file', 'button')); await L.idle(p, 2500);
  log('checkres', await p.evaluate(() => { const els = [...document.querySelectorAll('div')].filter(e => e.offsetParent !== null && /Check only|passed the check|has problems/i.test(e.innerText) && e.innerText.length < 1500); els.sort((a, b) => a.innerText.length - b.innerText.length); return els[0] ? els[0].innerText.replace(/\s+/g, ' ').slice(0, 400) : null; }));
  await L.shot(p, 'B1f_tb_check', false);
  L.resetLog(p);
  log('load', await L.clickText(p, 'Load trial balance', 'button')); await L.idle(p, 3000);
  log('flash', await L.flash(p)); log('bad', p._log.bad);
  await L.shot(p, 'B1f_tb_result', false);
};
