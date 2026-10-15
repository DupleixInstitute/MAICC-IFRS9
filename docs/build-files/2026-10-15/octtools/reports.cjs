'use strict';
// Reports for a month: the IFRS 9 note and the RBM return render and download; then October asked for by name.
const fs = require('fs');
const ui = require('./ui.cjs');
async function dl(page, url, name) {
  const r = await page.evaluate(async (url) => {
    const r = await fetch(url, { credentials: 'same-origin' });
    const b = new Uint8Array(await r.arrayBuffer());
    let s = ''; for (let i = 0; i < b.length; i += 8192) s += String.fromCharCode.apply(null, b.subarray(i, i + 8192));
    return { status: r.status, type: r.headers.get('content-type'), disp: r.headers.get('content-disposition'), b64: btoa(s), size: b.length };
  }, url);
  if (r.status === 200 && name) fs.writeFileSync(ui.OUT + '/' + name, Buffer.from(r.b64, 'base64'));
  const head = Buffer.from(r.b64, 'base64').slice(0, 300).toString('utf8');
  return { url, status: r.status, type: r.type, file: (r.disp || '').replace(/.*filename="?([^";]+)"?.*/, '$1'), size: r.size, head: r.status === 200 ? head.slice(0, 8).replace(/[^\x20-\x7e]/g, '.') : head.replace(/\s+/g, ' ').slice(0, 300) };
}
(async () => {
  const { browser, page } = await ui.open('checker');
  const out = {};
  try {
    await ui.goto(page, '/ifrs9-reports/fs-disclosure?opening=2025-12&closing=2026-09');
    let p = await ui.props(page);
    out.note = { opening: p.opening, closing: p.closing, error: p.error, months: (p.months || []).slice(0, 3), has_note: !!p.note };
    await ui.shot(page, 'A2_ifrs9_note_2025-12_to_2026-09');
    out.note_downloads = [];
    for (const f of ['pdf', 'docx', 'xlsx', 'csv']) out.note_downloads.push(await dl(page, `/ifrs9-reports/fs-disclosure?opening=2025-12&closing=2026-09&download=${f}`, `A2_ifrs9_note_2026-09.${f}`));

    await ui.goto(page, '/rbm-return?period=2026-09');
    p = await ui.props(page);
    out.rbm = { period: p.period, error: p.error, has_return: !!p.return };
    out.rbm_text = (await ui.text(page)).split('\n').filter((l) => /loss|1,510,956,083|facilit/i.test(l)).slice(0, 6);
    await ui.shot(page, 'A2_rbm_return_2026-09');
    out.rbm_downloads = [];
    for (const f of ['pdf', 'xlsx', 'csv']) out.rbm_downloads.push(await dl(page, `/rbm-return/export?period=2026-09&format=${f}`, `A2_rbm_return_2026-09.${f}`));

    // October by name
    await ui.goto(page, '/ifrs9-reports/fs-disclosure?opening=2025-12&closing=2026-10');
    p = await ui.props(page);
    out.note_oct = { opening: p.opening, closing: p.closing, error: p.error };
    await ui.shot(page, 'A3_ifrs9_note_closing_2026-10');
    out.note_oct_download = await dl(page, '/ifrs9-reports/fs-disclosure?opening=2025-12&closing=2026-10&download=pdf', null);
    await ui.goto(page, '/rbm-return?period=2026-10');
    p = await ui.props(page);
    out.rbm_oct = { period: p.period, error: p.error, has_return: !!p.return };
    await ui.shot(page, 'A3_rbm_return_2026-10');
    out.rbm_oct_download = await dl(page, '/rbm-return/export?period=2026-10&format=pdf', null);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
