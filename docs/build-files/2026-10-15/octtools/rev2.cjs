const ui = require('./ui.cjs');
(async () => { const { browser, page } = await ui.open('checker'); await ui.goto(page, '/eir-reconciliation?period=2026-09');
 const p = await ui.props(page); console.log(JSON.stringify({ readiness: p.revenueReadiness, keys: Object.keys(p).slice(0, 30) }, null, 1));
 await ui.shot(page, 'A2_eir_reconciliation_2026-09_after'); await browser.close(); })();
