module.exports = async (L, p, log) => {
  await L.goto(p, '/fli/models?period=2026-09');
  L.resetLog(p);
  log('click', await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.title === 'Retire this version'); if (!b) return null; b.click(); return b.closest('tr').innerText.replace(/\s+/g, ' ').slice(0, 160); }));
  await L.sleep(700);
  await p.evaluate(() => { const app = document.querySelector('#app'); const el = [...document.querySelectorAll('textarea, input[type=text], input:not([type])')].find(e => e.offsetParent && !app.contains(e)); const set = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set; set.call(el, 'UAT: the fit needs a BROAD_MONEY actual the data does not have at the month-end'); el.dispatchEvent(new Event('input', { bubbles: true })); });
  log('confirm', await L.confirmDlg(p, 'Retire')); await L.idle(p, 2500);
  log('flash', await L.flash(p), p._log.bad);
  await L.shot(p, 'B4_model_retired', false);
};
