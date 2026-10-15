module.exports = async (L, p, log) => {
  const act = process.env.ACT; const cid = process.env.CID;
  await L.goto(p, '/eir-fee-classification?contract_id=' + cid);
  const n = await p.evaluate(() => { const h = [...document.querySelectorAll('main thead input[type=checkbox]')].find(c => c.offsetParent); if (h) h.click(); return document.querySelectorAll('main tbody input[type=checkbox]:checked').length; });
  log('selected', n); await L.sleep(400);
  L.resetLog(p);
  if (act === 'classify') {
    log('btn', await L.clickText(p, 'Classify manually', 'button')); await L.sleep(500);
    await p.evaluate(() => { const sels = [...document.querySelectorAll('select')].filter(s => s.offsetParent); const s = sels.find(s => [...s.options].some(o => /Integral - include/.test(o.text))); const o = [...s.options].find(o => /Integral - include/.test(o.text)); s.value = o.value; s.selectedIndex = o.index; s.dispatchEvent(new Event('change')); const t = [...document.querySelectorAll('textarea')].filter(e => e.offsetParent).pop(); Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(t, 'UAT: arrangement and legal fees on origination are integral to the EIR (IFRS 9 B5.4.2)'); t.dispatchEvent(new Event('input', { bubbles: true })); });
    log('direction select', await p.evaluate(() => { const s = [...document.querySelectorAll('select')].filter(s => s.offsetParent).find(s => [...s.options].some(o => o.value === 'RECEIVED')); if (!s) return null; s.value = 'RECEIVED'; s.dispatchEvent(new Event('change')); return 'RECEIVED'; }));
    await L.shot(p, 'B6_fee_classify_modal', false);
    log('save', await L.clickText(p, 'Save classification', 'button'));
  } else {
    log('btn', await L.clickText(p, 'Review/approve', 'button')); await L.sleep(500);
    log('approve', await L.clickText(p, 'Approve classifications', 'button'));
  }
  await L.idle(p, 2500);
  log('flash', await L.flash(p), 'bad', p._log.bad);
  log('err', await p.evaluate(() => [...document.querySelectorAll('.text-red-700')].filter(e => e.offsetParent).map(e => e.innerText).join(' | ').slice(0, 300)));
  log('rows', await p.evaluate(() => [...document.querySelectorAll('main tbody tr')].slice(0, 3).map(r => r.innerText.replace(/\s+/g, ' ').slice(-60))));
  await L.shot(p, 'B6_fee_' + act + '_' + (process.env.WHO || ''), false);
};
