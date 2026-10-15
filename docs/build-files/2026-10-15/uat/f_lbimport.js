module.exports = async (L, p, log) => {
  const F = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/files/';
  await L.goto(p, '/loan_application/loan-books/import');
  const s = await L.hrefOf(p, 'Download sample'); const r = await L.fetchTo(p, s, F + 'loanbook_sample.csv'); log('sample', s, r.s, r.cd, r.size);
  await p.click('#loan_portfolio_id'); await L.sleep(500);
  log('options', await p.evaluate(() => [...document.querySelectorAll('.multiselect-option')].map(o => o.innerText.trim())));
  await p.evaluate(() => { const o = [...document.querySelectorAll('.multiselect-option')].find(o => /UAT test/.test(o.innerText)); o && o.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); o && o.click(); }); await L.sleep(400);
  log('portfolio', await p.evaluate(() => document.querySelector('#loan_portfolio_id').innerText.trim().slice(0, 80)));
  await L.setVal(p, '#reporting_period', '2026-10');
  await L.upload(p, F + 'loanbook_sample.csv');
  log('check', await L.clickText(p, 'Check file', 'button')); await L.idle(p, 2000);
  log('checkres', await p.evaluate(() => { const els = [...document.querySelectorAll('div')].filter(e => e.offsetParent && /Check only|passed the check|has problems/i.test(e.innerText) && e.innerText.length < 1500); els.sort((a, b) => a.innerText.length - b.innerText.length); return els[0] ? els[0].innerText.replace(/\s+/g, ' ').slice(0, 400) : null; }));
  await L.shot(p, 'BN_loanbook_check', false);
  L.resetLog(p);
  log('submit', await L.clickText(p, 'Start import', 'button')); await L.idle(p, 4000);
  log('invalid', await p.evaluate(() => [...document.querySelectorAll('form :invalid')].map(e => e.id || e.name || e.tagName)), await p.evaluate(() => [...document.querySelectorAll('.text-red-600')].filter(e => e.offsetParent).map(e => e.innerText)));
  log('flash', await L.flash(p), p.url(), p._log.bad);
  await L.shot(p, 'BN_loanbook_import', false);
};
