module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-takeon?tab=blocks');
  L.resetLog(p); const t0 = Date.now();
  log('click', await L.clickText(p, 'Build the take-on population', 'button'));
  const c = await L.confirmDlg(p); log('confirm', c);
  await L.idle(p, 3000);
  log('secs', (Date.now() - t0) / 1000, 'flash', await L.flash(p), 'bad', p._log.bad);
  await L.goto(p, '/eir-takeon?tab=population');
  await L.shot(p, 'E3_takeon_population', false);
  await L.dark(p, true); await L.shot(p, 'E3_takeon_population_dark', false); await L.dark(p, false);
  log('rows', await p.evaluate(() => [...document.querySelectorAll('main table tbody tr')].slice(0, 4).map(r => r.innerText.replace(/\s+/g, ' ').slice(0, 300))));
};
