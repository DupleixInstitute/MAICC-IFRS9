module.exports = async (L, p, log) => {
  await L.goto(p, '/workspace?period=2026-09');
  log('checklist', (await L.mainText(p, 2200)).slice(300, 2200));
  await L.shot(p, 'B11_workspace_checklist', true);
  await L.clickText(p, 'Awaiting approval', 'main button'); await L.sleep(400);
  log('approvals', (await L.mainText(p, 3000)).split('Awaiting approval')[1]?.slice(0, 700));
  await L.shot(p, 'B11_workspace_approvals', false); await L.dark(p, true); await L.shot(p, 'B11_workspace_approvals_dark', false);
};
