'use strict';
// Check file on every import screen: the screen's own spec endpoint gives the sample and the
// check url; the sample is downloaded and sent to the check (writes nothing).
const fs = require('fs');
const ui = require('./ui.cjs');
const keys = ['clients', 'loan-book', 'loan-book-ebanker', 'collateral', 'credit-loss-data', 'eir-contract-master', 'eir-schedule', 'eir-fees',
  'eir-contract-transactions', 'eir-gl-interest', 'eir-reference-rates', 'eir-disbursements', 'trial-balance', 'takeon', 'ebanker-pack', 'sp-grades',
  'sicr-groups', 'sicr-items', 'macro-series', 'rbm-policy-rate', 'imf-weo'];
(async () => {
  const { browser, page } = await ui.open(process.argv[2] || 'maker');
  const rows = [];
  await ui.goto(page, '/workspace');
  for (const k of keys) {
    const r = await page.evaluate(async (k) => {
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      const xs = m ? decodeURIComponent(m[1]) : '';
      const spec = await (await fetch('/import-templates/' + k + '/spec', { headers: { Accept: 'application/json' } })).json();
      const out = { key: k, template: !!spec.template_url, check_url: !!spec.check_url };
      if (!spec.template_url || !spec.check_url) return out;
      const t = await fetch(spec.template_url);
      out.template_status = t.status;
      const cd = t.headers.get('content-disposition') || '';
      const name = (cd.match(/filename="([^"]+)"/) || [])[1] || (k + '.csv');
      const blob = await t.blob();
      const fd = new FormData();
      fd.append('file', blob, name);
      if (k === 'trial-balance') fd.append('period', '2025-12');
      const c = await fetch(spec.check_url, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xs, 'X-Requested-With': 'XMLHttpRequest' } });
      const txt = await c.text();
      out.check_status = c.status;
      try {
        const j = JSON.parse(txt);
        out.ok = j.ok; out.rows = j.rows; out.errors = j.errors ?? (j.issues || []).filter((i) => i.severity === 'error').length;
        out.first = (j.issues || []).slice(0, 2).map((i) => i.message);
        out.summary = j.summary || j.message || null;
      } catch (e) { out.body = txt.slice(0, 200); }
      return out;
    }, k);
    rows.push(r);
    console.log(JSON.stringify(r));
  }
  fs.writeFileSync(ui.OUT + '/C_import_checks.json', JSON.stringify(rows, null, 1));
  await browser.close();
})();
