const fs = require('fs'); const ui = require('./ui.cjs');
const types = ['contract_master', 'schedule', 'fees', 'contract_transactions', 'gl_interest', 'reference_rates', 'disbursements'];
(async () => { const { browser, page } = await ui.open('checker'); await ui.goto(page, '/eir-intake'); await ui.shot(page, 'C_eir_intake_screen', false);
  const rows = [];
  for (const t of types) {
    rows.push(await page.evaluate(async (t) => {
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); const xs = m ? decodeURIComponent(m[1]) : '';
      const s = await fetch('/eir-intake/sample/' + t); const out = { type: t, sample_status: s.status };
      if (s.status !== 200) return out;
      const fd = new FormData(); fd.append('file', await s.blob(), t + '.csv'); fd.append('import_type', t);
      const r = await fetch('/eir-intake/analyze', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xs, 'X-Requested-With': 'XMLHttpRequest' } });
      const txt = await r.text(); out.status = r.status;
      try { const j = JSON.parse(txt); out.missing_required = j.missing_required; out.check_ok = j.check ? j.check.ok : null; out.check_errors = j.check ? (j.check.issues || []).filter(i => i.severity === 'error').length : null; out.first = j.check ? (j.check.issues || []).slice(0, 2).map(i => i.message) : j.error; } catch (e) { out.body = txt.slice(0, 200); }
      return out; }, t));
    console.log(JSON.stringify(rows[rows.length - 1]));
  }
  fs.writeFileSync(ui.OUT + '/C_eir_intake_checks.json', JSON.stringify(rows, null, 1)); await browser.close(); })();
