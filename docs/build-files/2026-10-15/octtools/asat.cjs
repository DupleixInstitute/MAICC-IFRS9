const ui = require('./ui.cjs');
(async () => { const { browser, page } = await ui.open('checker'); await ui.goto(page, '/eir-as-at'); const p = await ui.props(page);
 console.log(JSON.stringify({ yearEnds: p.yearEnds, last: p.lastLedgerDate, date: p.date, error: p.error, book: !!p.book }));
 await ui.goto(page, '/workspace?period=2026-09'); const w = await ui.props(page); console.log(JSON.stringify({ ws: w.period, periods: w.periods.slice(0, 3), tasks: w.tasks.map(t => t.key + ':' + t.status) }));
 await ui.shot(page, 'A2_workspace_2026-09_after'); await browser.close(); })();
