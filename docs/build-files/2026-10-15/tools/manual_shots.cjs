// node manual_shots.cjs <cookie.json> : the forward-looking manual figures, as JPEG, into public/manual/screenshots
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.argv[2]);
const OUT = 'C:/xampp/htdocs/MAICC-IFRS9/public/manual/screenshots/';
const SHOTS = {
  'macro-elements': 'macro-statistics?tab=variables',
  'scenario-profiles': 'scenario-profiles',
  'fli': 'fli?period=2026-08',
  'credit-loss-data': 'credit-loss-data',
  'adjusted-forecast': 'fli-adj/forecasting/manual-forecast?period=2026-08',
  'economic-scenarios': 'fli-adj/scenarios?period=2026-08',
  'external-calculations': 'fli-adj/external?period=2026-08',
  'calculation-history': 'fli-adj/external/list',
};
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1600, height: 1000 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  for (const [key, path] of Object.entries(SHOTS)) {
    let ok = false;
    for (let i = 0; i < 3 && !ok; i++) {
      const errs = [];
      p.removeAllListeners('console');
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      await p.goto('http://127.0.0.1:8070/' + path, { waitUntil: 'load', timeout: 120000 });
      await new Promise(r => setTimeout(r, 3000));
      ok = errs.length === 0;
      if (ok) await p.screenshot({ path: OUT + key + '.jpg', type: 'jpeg', quality: 85 });
    }
    console.log(key, ok ? 'saved' : 'FAILED');
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
