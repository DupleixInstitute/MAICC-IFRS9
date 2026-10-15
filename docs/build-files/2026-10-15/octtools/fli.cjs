'use strict';
// FLI Overview steps for a month, through the endpoint the Overview's buttons call.
// node fli.cjs <period> <who> <shotprefix> <key> [key...]
const fs = require('fs');
const ui = require('./ui.cjs');
const [period, who, prefix, ...keys] = process.argv.slice(2);
(async () => {
  const { browser, page } = await ui.open(who);
  const out = { period, steps: [] };
  try {
    await ui.goto(page, '/fli?period=' + period);
    const p = await ui.props(page);
    out.before = p.steps.map((s) => `${s.key}:${s.state}:${s.command || ''}:${(s.status || '').slice(0, 140)}`);
    await ui.shot(page, prefix + '_before');
    for (const key of keys) {
      const r = await ui.api(page, 'POST', '/fli/run', { key, period });
      let j = {}; try { j = JSON.parse(r.body); } catch (e) {}
      const step = { key, status: r.status, ok: j.ok, message: j.message, command: j.command };
      if (j.ok) {
        const t0 = Date.now(); let st;
        for (;;) {
          await ui.sleep(10000);
          const o = await ui.api(page, 'GET', '/fli/run/' + j.id);
          try { st = JSON.parse(o.body).run; } catch (e) { st = null; }
          if (st && st.status !== 'running') break;
          if (Date.now() - t0 > 5400000) break;
        }
        step.run = st ? st.status : null; step.seconds = Math.round((Date.now() - t0) / 1000);
        if (st) fs.writeFileSync(ui.OUT + '/' + prefix + '_' + key + '.log', st.output || '');
        step.tail = st ? (st.output || '').split('\n').filter((l) => l.trim()).slice(-6) : null;
      }
      out.steps.push(step);
      console.log(JSON.stringify(step));
      if (!j.ok || step.run !== 'done') break;
    }
    await ui.goto(page, '/fli?period=' + period);
    const p2 = await ui.props(page);
    out.after = p2.steps.map((s) => `${s.key}:${s.state}:${(s.status || '').slice(0, 160)}`);
    out.snapshot = p2.snapshot ? { id: p2.snapshot.id, status: p2.snapshot.status, booked: p2.snapshot.booked_ecl, booked_disc: p2.snapshot.booked_ecl_discounted, reconciles: p2.snapshot.reconciles, disc_reconciles: p2.snapshot.discounted_reconciles, needs_commentary: p2.snapshot.needs_commentary, unresolved: p2.snapshot.discount_unresolved_loans } : null;
    await ui.shot(page, prefix + '_after');
  } catch (e) { out.error = e.message; }
  fs.writeFileSync(ui.OUT + '/' + prefix + '.json', JSON.stringify(out, null, 1));
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
