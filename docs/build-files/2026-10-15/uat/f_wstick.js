module.exports = async (L, p, log) => {
  await L.goto(p, '/workspace?period=2026-09');
  L.resetLog(p);
  const r = await p.evaluate(() => { const row = [...document.querySelectorAll('main div.flex.items-center')].find(d => /Review IFRS 9 reports/.test(d.innerText) && d.querySelector('button[title="Mark as done"]')); if (!row) return null; row.querySelector('button[title="Mark as done"]').click(); return 'clicked'; });
  log('tick', r); await L.idle(p, 2000);
  log('flash', await L.flash(p), p._log.bad);
  log('after', (await L.mainText(p, 2500)).match(/Review IFRS 9 reports[^]*?(Done|Pending)/)?.[0]?.slice(-80));
};
