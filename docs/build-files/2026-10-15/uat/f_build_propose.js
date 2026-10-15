module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-feed?tab=builds');
  log('before', await L.mainText(p, 900));
  await L.clickText(p, 'Propose a build', 'button'); await L.sleep(700);
  const m = await p.$$('input[type=month]');
  await L.setVal(p, 'input[type=month]', '2026-09');
  await p.evaluate(() => { const ms = [...document.querySelectorAll('input[type=month]')]; const s = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set; ms.forEach(m => { s.call(m, '2026-09'); m.dispatchEvent(new Event('input', { bubbles: true })); }); });
  await L.shot(p, 'B2_build_modal', false);
  L.resetLog(p); const t0 = Date.now();
  log('submit', await L.clickText(p, 'Propose the build', 'button')); await L.idle(p, 3000);
  log('secs', (Date.now() - t0) / 1000, 'flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B2_build_proposed', false);
  log('after', await L.mainText(p, 1200));
};
