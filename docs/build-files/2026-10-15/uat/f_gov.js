module.exports = async (L, p, log) => {
  const act = process.env.ACT;
  await L.goto(p, '/eir-governance');
  L.resetLog(p);
  const opened = await p.evaluate(() => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && /\bday_count\b/.test(b.innerText)); if (b) { b.click(); return b.innerText.replace(/\s+/g, ' ').slice(0, 150); } return null; });
  log('card', opened); await L.sleep(800);
  if (act === 'propose') {
    log('btn', await L.clickText(p, 'Propose a change', 'button')); await L.sleep(800);
    const opts = await p.evaluate(() => { const s = [...document.querySelectorAll('select')].filter(s => s.offsetParent).pop(); return [...s.options].map(o => o.value + (o.disabled ? '(in force)' : '')); });
    log('options', opts);
    const val = opts.find(o => o && !o.includes('(in force)'));
    await p.evaluate(v => { const s = [...document.querySelectorAll('select')].filter(s => s.offsetParent).pop(); s.value = v; s.dispatchEvent(new Event('change')); }, val);
    await p.evaluate(() => { const set = (el, v) => { Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set.call(el, v); el.dispatchEvent(new Event('input', { bubbles: true })); }; set([...document.querySelectorAll('input[type=date]')].filter(i => i.offsetParent).pop(), '2026-11-01'); set([...document.querySelectorAll('textarea')].filter(i => i.offsetParent).pop(), 'UAT test: day count convention change from November 2026, to test maker-checker.'); });
    await L.shot(p, 'B8_gov_propose_form', false);
    log('submit', await L.clickText(p, 'Propose change', 'button')); await L.idle(p, 2500);
  } else {
    const r = await p.evaluate(() => { const b = [...document.querySelectorAll('button')].find(b => b.offsetParent && b.textContent.trim() === 'Approve'); if (!b) return 'no approve button'; if (b.disabled) return 'disabled: ' + b.title; b.click(); return 'clicked'; });
    log('approve', r); if (r === 'clicked') { log('confirm', await L.confirmDlg(p)); await L.idle(p, 2500); }
  }
  log('flash', await L.flash(p), 'bad', p._log.bad);
  log('err', await p.evaluate(() => { const d = [...document.querySelectorAll('.text-red-700, .bg-red-50')].filter(e => e.offsetParent).map(e => e.innerText).join(' | '); return d.slice(0, 300); }));
  await L.shot(p, 'B8_gov_' + act + '_' + (process.env.WHO || ''), false);
};
