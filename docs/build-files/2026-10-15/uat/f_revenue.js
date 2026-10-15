module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-reconciliation?period=2026-09');
  log('btns', (await L.texts(p, 'main button')).filter(b => /revenue|Run|catch/i.test(b)));
  L.resetLog(p); const t0 = Date.now();
  const c = await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && /month|period/i.test(b.textContent) && /run/i.test(b.textContent)); if (!b) return null; b.click(); return b.textContent.trim(); });
  log('click', c); log('confirm', await L.confirmDlg(p)); await L.idle(p, 4000);
  log('secs', (Date.now() - t0) / 1000, 'flash', await L.flash(p), 'bad', p._log.bad);
  log('result', await p.evaluate(() => { const e = [...document.querySelectorAll('main div')].filter(d => d.offsetParent && /Revenue run|revenue/i.test(d.innerText) && d.innerText.length < 600); e.sort((a, b) => a.innerText.length - b.innerText.length); return e[0] ? e[0].innerText.replace(/\s+/g, ' ') : null; }));
  await L.shot(p, 'B6_revenue_run', false);
  const ex = await p.evaluate(() => [...document.querySelectorAll('a[href]')].filter(a => /export/.test(a.href)).map(a => a.getAttribute('href')));
  for (const h of ex.slice(0, 3)) log('export', h, await L.fetchTo(p, h, null));
  await L.goto(p, '/eir-as-at');
  const ex2 = await p.evaluate(() => [...document.querySelectorAll('a[href]')].filter(a => /export/.test(a.href)).map(a => a.getAttribute('href')));
  log('asat text', (await L.mainText(p, 600)).slice(200, 600));
  for (const h of ex2.slice(0, 3)) log('asat export', h, await L.fetchTo(p, h, null));
  await L.shot(p, 'B6_asat', false);
};
