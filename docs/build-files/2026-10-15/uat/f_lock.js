module.exports = async (L, p, log) => {
  const unlock = process.env.UNLOCK === '1';
  await L.goto(p, '/eir-feed?tab=periods');
  for (let pg = 1; pg < 4; pg++) { const has = await p.evaluate(() => [...document.querySelectorAll('main tbody tr')].some(r => r.innerText.startsWith('2026-09'))); if (has) break; await p.evaluate(n => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && b.textContent.trim() === String(n)); b && b.click(); }, pg + 1); await L.sleep(400); }
  const r = await p.evaluate(() => { const row = [...document.querySelectorAll('main tbody tr')].find(r => r.innerText.startsWith('2026-09')); if (!row) return null; const b = row.querySelector('button'); b.click(); return row.innerText.replace(/\s+/g, ' ') + ' | ' + b.title; });
  log('row', r); await L.sleep(600);
  await p.evaluate(v => { const t = [...document.querySelectorAll('textarea')].filter(e => e.offsetParent).pop(); Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(t, v); t.dispatchEvent(new Event('input', { bubbles: true })); }, unlock ? 'UAT: restatement test complete, unlocking the test copy' : 'UAT: September 2026 month-end signed off (test)');
  await L.shot(p, 'B10_' + (unlock ? 'unlock' : 'lock') + '_modal', false);
  L.resetLog(p);
  log('submit', await L.clickText(p, unlock ? 'Unlock the period' : 'Lock the period', 'button')); await L.idle(p, 2500);
  log('flash', await L.flash(p), 'bad', p._log.bad);
  await L.shot(p, 'B10_' + (unlock ? 'unlocked' : 'locked'), false);
};
