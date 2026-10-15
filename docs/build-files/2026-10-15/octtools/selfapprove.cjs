const ui = require('./ui.cjs');
(async () => { const { browser, page } = await ui.open(process.argv[3] || 'maker');
  await ui.goto(page, process.argv[4] || '/eir-feed?tab=periods');
  const r = await ui.post(page, process.argv[2], {});
  console.log(JSON.stringify({ err: r.error, status: r.status, flash: r.flash, body: r.body && r.body.slice(0, 300) })); await browser.close(); })();
