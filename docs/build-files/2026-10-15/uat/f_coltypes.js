module.exports = async (L, p, log) => {
  await L.goto(p, '/collateral/types');
  log('before', await L.mainText(p, 400));
  for (const [code, name, hc, m] of [['PROPERTY', 'Property (UAT test)', '0.70', '24'], ['VEHICLE', 'Vehicle (UAT test)', '0.60', '12']]) {
    await L.clickText(p, 'New collateral type', 'button'); await L.sleep(600);
    for (const [sel, v] of [['#t-code', code], ['#t-name', name], ['#t-haircut', hc], ['#t-months', m]]) { await L.setVal(p, sel, v); }
    await L.shot(p, 'B1d_coltype_modal_' + code, false);
    L.resetLog(p);
    await L.clickText(p, 'Add type', 'button'); await L.idle(p, 1500); await p.keyboard.press('Escape'); await L.sleep(300);
    log(code, await L.flash(p), p._log.bad);
  }
  await L.shot(p, 'B1d_coltypes_after', false);
  log('after', await L.mainText(p, 600));
};
