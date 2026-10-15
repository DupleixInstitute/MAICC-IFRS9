module.exports = async (L, p, log) => {
  await L.goto(p, '/expected-credit-loss/create');
  await L.setVal(p, '#reporting_period', '2026-09');
  await L.setVal(p, '#ecl_calculation_level', 'portfolio');
  await L.sleep(300);
  const pf = await p.evaluate(() => { const s = document.querySelector('#ecl_calculation_id'); const o = [...s.options].find(o => /MAIIC Agricultural/.test(o.text)); return o ? o.value : null; });
  await L.setVal(p, '#ecl_calculation_id', pf);
  await L.setVal(p, '#pd_type', 'pd_post_fli'); await L.setVal(p, '#lgd_type', 'collection_lgd');
  await p.evaluate(() => { const r = document.querySelector('input[type=radio][value=discounted]'); r && r.click(); });
  await L.shot(p, 'B5_ecl_create_form', false);
  L.resetLog(p); const t0 = Date.now();
  await p.evaluate(() => document.querySelector('form button[type=submit]').click());
  await L.idle(p, 3000);
  log('secs', (Date.now() - t0) / 1000, 'url', p.url(), 'flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B5_ecl_after_run', false);
  log('kpis', (await L.mainText(p, 900)).slice(150, 900));
};
