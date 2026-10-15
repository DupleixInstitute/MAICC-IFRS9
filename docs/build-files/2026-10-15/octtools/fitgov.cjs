const ui = require('./ui.cjs');
const fit = process.argv[2];
(async () => { const out = {};
  { const { browser, page } = await ui.open('maker'); await ui.goto(page, '/fli-adjustments?period=2026-09');
    out.propose = (await ui.post(page, `/fli-adjustments/${fit}/propose`, { note: 'Final check 14 Oct 2026: a fit to test the month-end path end to end on the clone' })).flash;
    out.maker_approve = (await ui.post(page, `/fli-adjustments/${fit}/approve`, {})).flash; await browser.close(); }
  { const { browser, page } = await ui.open('checker'); await ui.goto(page, '/fli-adjustments?period=2026-09');
    out.checker_approve = (await ui.post(page, `/fli-adjustments/${fit}/approve`, {})).flash; await ui.shot(page, 'A4_fit_approved'); await browser.close(); }
  for (const k of Object.keys(out)) out[k] = Object.fromEntries(Object.entries(out[k] || {}).filter(([, v]) => v));
  console.log(JSON.stringify(out, null, 1)); })();
