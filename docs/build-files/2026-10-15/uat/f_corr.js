module.exports = async (L, p, log) => {
  await L.goto(p, '/fli-correlation?period=2026-09');
  log('kpis', (await L.mainText(p, 900)).slice(250));
  L.resetLog(p); const t0 = Date.now();
  log('run', await L.clickText(p, 'Run the finder for', 'button'));
  await L.idle(p, 3000);
  log('secs', (Date.now() - t0) / 1000, 'flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B4_correlation_run', false);
};
