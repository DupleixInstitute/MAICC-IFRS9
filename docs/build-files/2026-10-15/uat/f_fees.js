module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-fee-classification');
  log('tabs', await L.texts(p, 'main nav button'));
  await L.shot(p, 'E1_fees_held', true);
  await L.dark(p, true); await L.shot(p, 'E1_fees_held_dark', false); await L.dark(p, false);
  log('held first', await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.title === 'Tie this fee line to its loan'); return b ? b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 300) : null; }));
  await L.clickText(p, 'Source differences', 'main nav button'); await L.sleep(400);
  log('diff rows', await p.evaluate(() => [...document.querySelectorAll('main table')].map(t => t.innerText.slice(0, 0)).length));
  await L.shot(p, 'E1_fees_differences', false);
  log('superseded markers', await p.evaluate(() => [...document.querySelectorAll('.maiic-badge')].filter(b => /Superseded by/.test(b.textContent)).length));
  if (process.env.TIE) {
    await L.clickText(p, 'Held GL fee lines', 'main nav button'); await L.sleep(300);
    const row = await p.evaluate(() => { const rows = [...document.querySelectorAll('main button')].filter(b => b.title === 'Tie this fee line to its loan').map(b => b.closest('tr')); const r = rows.find(r => !/None$/.test(r.cells[6].innerText.trim())) || rows[0]; r.querySelector('button[title="Tie this fee line to its loan"]').click(); return r.innerText.replace(/\s+/g, ' ').slice(0, 300); });
    log('tie row', row); await L.sleep(600);
    const cur = await p.evaluate(() => { const app = document.querySelector('#app'); const i = [...document.querySelectorAll('input.maiic-input')].filter(e => e.offsetParent).pop(); return i ? i.value : null; });
    log('prefilled loan', cur);
    if (!cur) await p.evaluate(v => { const i = [...document.querySelectorAll('input.maiic-input')].filter(e => e.offsetParent).pop(); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(i, v); i.dispatchEvent(new Event('input', { bubbles: true })); }, process.env.TIE);
    await p.evaluate(() => { const t = [...document.querySelectorAll('textarea')].filter(e => e.offsetParent).pop(); Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(t, 'UAT: narration names the borrower and the loan drew that day'); t.dispatchEvent(new Event('input', { bubbles: true })); });
    await L.shot(p, 'E1_fees_tie_modal', false);
    L.resetLog(p);
    log('submit', await L.clickText(p, 'Tie to the loan', 'button')); await L.idle(p, 3000);
    log('flash', await L.flash(p), p._log.bad);
  }
};
