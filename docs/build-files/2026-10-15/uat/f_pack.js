module.exports = async (L, p, log) => {
  const F = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/files/';
  for (const dry of [true, false]) {
    await L.goto(p, '/eir-feed?tab=loads');
    await L.clickText(p, 'Load a pack', 'button'); await L.sleep(700);
    const inp = await p.$('#pack-file'); await inp.uploadFile(F + (process.env.PACK || 'ebanker-pack-2026-10-09.zip'));
    if (dry) await p.evaluate(() => { const c = [...document.querySelectorAll('input[type=checkbox]')].find(c => c.closest('label') && /Check only/.test(c.closest('label').textContent)); c.click(); });
    await L.sleep(400);
    L.resetLog(p);
    log(dry ? 'dry' : 'load', await L.clickText(p, dry ? 'Check the pack' : 'Load the pack', 'button'));
    await L.idle(p, 3000);
    log('flash', await L.flash(p)); log('bad', p._log.bad);
    await L.shot(p, 'B1e_pack_' + (dry ? 'check' : 'load'), false);
  }
};
