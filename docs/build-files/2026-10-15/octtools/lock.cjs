'use strict';
// Lock / request unlock / approve unlock of a period on E-Banker Feed, Periods tab.
// node lock.cjs <lock|unlock|approve> <period> <who> <shot>
const ui = require('./ui.cjs');
const [action, period, who, name] = process.argv.slice(2);
async function inertiaAfter(page, fn) {
  const resp = page.waitForResponse((r) => r.url().includes('/eir-feed') && r.request().method() === 'GET' && r.request().headers()['x-inertia'], { timeout: 300000 });
  await fn();
  const r = await resp; const j = await r.json().catch(() => null);
  return j && j.props ? { flash: j.props.flash, locks: j.props.locks, unlockRequests: j.props.unlockRequests } : null;
}
(async () => {
  const { browser, page } = await ui.open(who);
  const out = { action, period, who };
  try {
    await ui.goto(page, '/eir-feed?tab=periods');
    // the periods run oldest first, 15 a page: go to the last page
    for (let i = 0; i < 5; i++) {
      const more = await page.evaluate(() => { const b = [...document.querySelectorAll('nav[aria-label=Pages] button')].find((x) => /Next/.test(x.innerText) && !x.disabled); if (b) { b.click(); return true; } return false; });
      if (!more) break; await ui.sleep(400);
    }
    if (action === 'lock' || action === 'unlock') {
      const t = (action === 'lock' ? 'Lock ' : 'Request the unlock of ') + period;
      const found = await page.evaluate((t) => { const b = document.querySelector(`button[title="${t}"]`); if (b) { b.click(); return true; } return false; }, t);
      if (!found) throw new Error('no button ' + t);
      await ui.sleep(800);
      await page.type('textarea', action === 'lock'
        ? 'Final check 14 Oct 2026: lock September 2026 after the month-end to test the lock.'
        : 'Final check 14 Oct 2026: a correction needs September 2026 re-run from the screens.');
      await ui.shot(page, name + '_form', false);
      out.result = await inertiaAfter(page, () => page.evaluate(() => [...document.querySelectorAll('button[type=submit]')].find((b) => /Lock the period|Request the unlock/.test(b.innerText)).click()));
    } else if (action === 'approve') {
      const found = await page.evaluate((p) => { const b = document.querySelector(`button[title="Approve and unlock ${p}"]`); if (b) { b.click(); return true; } return false; }, period);
      out.approve_button_visible = found;
      if (found) {
        await ui.sleep(800);
        out.result = await inertiaAfter(page, () => page.evaluate(() => [...document.querySelectorAll('button')].reverse().find((b) => b.offsetParent && /Approve and unlock/.test(b.innerText)).click()));
      }
    }
    await ui.sleep(1500);
    await ui.shot(page, name, false);
  } catch (e) { out.error = e.message; }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
