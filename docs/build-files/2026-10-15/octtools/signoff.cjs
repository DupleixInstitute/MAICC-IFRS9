'use strict';
// Sign off the month on FLI Overview: the maker's attempt (by request, the button is disabled for them), then the checker's (the button).
const ui = require('./ui.cjs');
const period = process.argv[2] || '2026-09';
(async () => {
  const out = {};
  {
    const { browser, page } = await ui.open('maker');
    await ui.goto(page, '/fli?period=' + period);
    const p = await ui.props(page);
    out.snapshot = p.snapshot.id;
    out.maker_button_disabled = await page.evaluate(() => { const b = [...document.querySelectorAll('button')].find((x) => /Sign off the month/.test(x.innerText)); return b ? b.disabled : null; });
    out.maker_attempt = await ui.post(page, '/fli/snapshots/' + p.snapshot.id + '/sign-off', { commentary: 'Maker trying to sign off the record they made (must be refused).' });
    await browser.close();
  }
  {
    const { browser, page } = await ui.open('checker');
    await ui.goto(page, '/fli?period=' + period);
    // without commentary first (38 loans not discounted: refused, a commentary is required)
    out.checker_no_commentary_button_disabled = await page.evaluate(() => { const b = [...document.querySelectorAll('button')].find((x) => /Sign off the month/.test(x.innerText)); return b ? b.disabled : null; });
    await page.type('#commentary', 'Final check 14 Oct 2026: September re-run from the screens after a correction; the booked allowance equals the bootstrap figure. 38 loans have no locked original EIR, so they are not discounted; signed off with them named on the record.');
    const resp = page.waitForResponse((r) => r.url().includes('/fli') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 300000 });
    await page.evaluate(() => [...document.querySelectorAll('button')].find((x) => /Sign off the month/.test(x.innerText)).click());
    await ui.sleep(1000);
    await page.evaluate(() => { const b = [...document.querySelectorAll('button')].reverse().find((x) => x.offsetParent && /^(Sign off|Confirm|Yes)/.test(x.innerText.trim()) && !/the month/.test(x.innerText)); if (b) b.click(); });
    const r = await resp; const j = await r.json().catch(() => null);
    out.checker_flash = j && j.props ? j.props.flash : null;
    out.after = j && j.props && j.props.snapshot ? { status: j.props.snapshot.status, signer: j.props.snapshot.signer, booked: j.props.snapshot.booked_ecl, booked_disc: j.props.snapshot.booked_ecl_discounted } : null;
    await ui.sleep(1500);
    await ui.shot(page, 'A2_fli_signed_off');
    await browser.close();
  }
  console.log(JSON.stringify(out, null, 1));
})();
