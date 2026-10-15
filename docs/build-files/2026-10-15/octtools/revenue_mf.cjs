'use strict';
// EIR revenue for a month from EIR Reconciliation (the "Run <period> only" button), then the Mega Farm run, then the ECL page.
const ui = require('./ui.cjs');
const period = process.argv[2] || '2026-09';
(async () => {
  const { browser, page } = await ui.open('maker');
  const out = {};
  try {
    await ui.goto(page, '/eir-reconciliation?period=' + period);
    const resp = page.waitForResponse((r) => r.url().includes('/eir-reconciliation') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 1800000 });
    const clicked = await page.evaluate((p) => { const b = [...document.querySelectorAll('button')].find((x) => x.innerText.trim() === `Run ${p} only`); if (b && !b.disabled) { b.click(); return true; } return b ? 'disabled' : false; }, period);
    out.clicked = clicked;
    if (clicked === true) {
      const r = await resp; const j = await r.json().catch(() => null);
      out.revenue_run = j && j.props ? j.props.revenueRun : null;
      out.flash = j && j.props ? j.props.flash : null;
    }
    await ui.sleep(2000);
    await ui.shot(page, 'A2_eir_revenue_' + period);

    await ui.goto(page, '/megafarm?period=' + period);
    out.megafarm = await ui.post(page, '/megafarm/run', { period });
    await ui.shot(page, 'A2_megafarm_' + period);

    await ui.goto(page, '/expected-credit-loss/list?period=' + period);
    out.ecl_page_text = (await ui.text(page)).split('\n').filter((l) => /booked|discount|allowance|total|6,473|10,654/i.test(l)).slice(0, 15);
    await ui.shot(page, 'A2_ecl_page_' + period);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
