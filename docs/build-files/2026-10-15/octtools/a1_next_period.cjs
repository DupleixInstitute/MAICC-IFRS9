'use strict';
// Test A, next period (October 2026): Workspace, Maintenance month-end and build, Periods tab, FLI run.
const ui = require('./ui.cjs');
(async () => {
  const { browser, page } = await ui.open('maker');
  const out = {};
  try {
    // Workspace for 2026-10
    await ui.goto(page, '/workspace?period=2026-10');
    const p = await ui.props(page);
    out.workspace = { period: p.period, periods: (p.periods || []).slice(0, 4), tasks: p.tasks.map((t) => `${t.key}:${t.status}:${t.detail || ''}`) };
    await ui.shot(page, 'A1_workspace_2026-10');
    // tick the sign-off for October (no loan book): must be refused
    const tog = await ui.api(page, 'POST', '/workspace/toggle', { period: '2026-10', task_key: 'signoff' });
    await ui.goto(page, '/workspace?period=2026-10');
    const p2 = await ui.props(page);
    out.workspace_signoff = { status: tog.status, flash: tog.flash, body: tog.body, signoff: p2.tasks.find((t) => t.key === 'signoff').status };
    await ui.shot(page, 'A1_workspace_2026-10_signoff_refused', false);

    // Maintenance: month-end for 2026-10, from the month dropdown
    await ui.goto(page, '/maintenance');
    const mp = await ui.props(page);
    out.maintenance_choices = { latest: mp.status.latest_month, periods: mp.periods.slice(0, 3) };
    await page.select('#m-period', '2026-10');
    await ui.shot(page, 'A1_maintenance_2026-10_selected', false);
    const me = await ui.api(page, 'POST', '/maintenance/run', { key: 'month-end', period: '2026-10' });
    out.maintenance_month_end = me;
    // the same request through the screen's button
    const clicked = await page.evaluate(() => {
      const b = [...document.querySelectorAll('button')].find((x) => /Run the month-end calculations/i.test(x.closest('div,li,section')?.innerText || '') && /run/i.test(x.innerText));
      if (b) { b.click(); return b.innerText; }
      return null;
    });
    await ui.sleep(1500);
    // a confirm dialog may appear: confirm it
    await page.evaluate(() => { const b = [...document.querySelectorAll('button')].find((x) => /^(Run|Confirm|Yes|Run it)/i.test(x.innerText.trim()) && x.offsetParent && x.closest('[role=dialog],.fixed')); if (b) b.click(); });
    await ui.sleep(2500);
    out.maintenance_click = clicked;
    out.maintenance_screen_text = (await ui.text(page)).split('\n').filter((l) => /refused|no loan book|October 2026/i.test(l)).slice(0, 5);
    await ui.shot(page, 'A1_maintenance_month-end_2026-10_refused', false);

    // FLI Overview run for 2026-10
    out.fli_run = await ui.api(page, 'POST', '/fli/run', { key: 'fli-all', period: '2026-10' });

    // Periods tab of the E-Banker Feed
    await ui.goto(page, '/eir-feed?tab=periods');
    const fp = await ui.props(page);
    out.feed_periods = { lastLedgerDate: fp.lastLedgerDate, last_periods: fp.periods.slice(-3), locks: fp.locks };
    await ui.shot(page, 'A1_feed_periods_tab');

    // Propose a loan book build for 2026-10 from the Builds tab
    const b = await ui.api(page, 'POST', '/eir-feed/build', { from: '2026-10', to: '2026-10', method: '' }, { Accept: 'text/html' });
    out.feed_build = b;
    await ui.goto(page, '/eir-feed?tab=builds');
    const bp = await ui.props(page);
    await ui.shot(page, 'A1_feed_build_2026-10_refused', false);
  } catch (e) {
    out.error = e.message;
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
