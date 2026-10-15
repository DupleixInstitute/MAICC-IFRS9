module.exports = async (L, p, log) => {
  const tag = process.env.TAG || 'B2';
  await L.goto(p, '/workspace');
  await L.clickText(p, 'Awaiting approval', 'main button'); await L.sleep(400);
  log('workspace', (await L.mainText(p, 2000)).split('Awaiting approval')[1]?.slice(0, 400));
  await L.shot(p, tag + '_workspace_queue', false);
  await L.goto(p, '/eir-feed?tab=builds');
  const btns = await p.evaluate(() => [...document.querySelectorAll('main button')].filter(b => b.offsetParent).map(b => (b.textContent.trim() || b.title)));
  log('buttons', btns);
  L.resetLog(p); const t0 = Date.now();
  const c = await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && /approve/i.test(b.textContent + ' ' + b.title)); if (b) { b.click(); return b.textContent.trim() || b.title; } return null; });
  log('approve click', c);
  if (c) { log('confirm', await L.confirmDlg(p, 'Approve')); await L.idle(p, 3000); }
  log('secs', (Date.now() - t0) / 1000, 'flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, tag + '_build_after', false);
  log('table', (await L.mainText(p, 1600)).slice(700));
};
