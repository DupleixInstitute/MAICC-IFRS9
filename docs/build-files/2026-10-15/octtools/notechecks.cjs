const ui = require('./ui.cjs');
(async () => { const { browser, page } = await ui.open('checker');
  for (const [o, c] of [['2025-12', '2026-09'], ['2026-08', '2026-09']]) {
    await ui.goto(page, `/ifrs9-reports/fs-disclosure?opening=${o}&closing=${c}`); const p = await ui.props(page);
    const checks = (p.note && p.note.checks) || [];
    console.log(o, c, p.note && p.note.status, JSON.stringify(checks.filter(x => x.level === 'fail').map(x => (x.label || x.name || '') + ': ' + (x.detail || x.message || '')).slice(0, 5)));
  }
  await browser.close(); })();
