module.exports = async (L, p, log) => {
  const D = 'C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/takeon/';
  for (const mode of ['Check only', 'Load the workbook']) {
    await L.goto(p, '/eir-takeon?tab=blocks');
    await L.clickText(p, 'Load take-on workbook', 'button'); await L.sleep(700);
    const ins = await p.$$('input[type=file]');
    await ins[ins.length - 2].uploadFile(D + 'Amortisation schedules as at 31 October 2024 - as received 11 Sep 2026.xlsx');
    await ins[ins.length - 1].uploadFile(D + 'Take-on schedules with mapping - fees completed by MAIIC - received 13 Oct 2026.xlsx');
    await L.sleep(500); L.resetLog(p);
    log(mode, await L.clickText(p, mode, 'button')); await L.idle(p, 3000);
    log('flash', await L.flash(p)); log('bad', p._log.bad);
    await L.shot(p, 'B1g_takeon_' + mode.replace(/\W+/g, '_'), false);
  }
};
