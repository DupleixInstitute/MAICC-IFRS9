module.exports = async (L, p, log) => {
  await L.goto(p, '/portfolios/create');
  const ins = await p.$$('main input[type=text], main input:not([type]), main textarea');
  await ins[0].type('UAT test portfolio');
  if (ins[1]) await ins[1].type('Created by the UI audit to test imports without touching the real portfolios');
  L.resetLog(p);
  const b = await p.evaluate(() => { const b = [...document.querySelectorAll('main button[type=submit], main button')].filter(b => b.offsetParent).pop(); b.click(); return b.textContent.trim(); });
  log('submit', b); await L.idle(p, 2000);
  log('flash', await L.flash(p), p.url(), p._log.bad);
  await L.shot(p, 'B1k_portfolio_created', false);
};
