// env: IURL, ITAG, IFILE (optional real file; default: the page's sample), ISUBMIT (submit button text), IPRE (js to eval before upload), ICHECKONLY=1 to skip submit
module.exports = async (L, p, log) => {
  const F = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/files/';
  const tag = process.env.ITAG;
  log('status', await L.goto(p, process.env.IURL));
  if (process.env.IPRE) { await p.evaluate(process.env.IPRE); await L.sleep(800); }
  let file = process.env.IFILE;
  const sample = await L.hrefOf(p, 'Download sample');
  const guide = await L.hrefOf(p, 'Download column guide') || await L.hrefOf(p, 'Column guide');
  if (sample) { const ext = (sample.match(/\.(\w+)(\?|$)/) || [])[1]; const r = await L.fetchTo(p, sample, F + tag + '_sample.bin'); log('sample', sample, r.s, r.ct, r.cd, r.size); if (!file) { const name = ((r.cd || '').match(/filename="?([^";]+)/) || [])[1] || (tag + '_sample.csv'); L.fs.renameSync(F + tag + '_sample.bin', F + tag + '_' + name); file = F + tag + '_' + name; } }
  else log('NO SAMPLE LINK');
  if (guide) { const r = await L.fetchTo(p, guide, null); log('guide', r.s, r.ct, r.size); }
  if (!file) return;
  log('file', file);
  await L.upload(p, file, Number(process.env.IFILEIDX || 0));
  const chk = await L.clickText(p, 'Check file', 'button');
  if (chk) { await L.idle(p, 1500); log('check', await p.evaluate(() => { const els = [...document.querySelectorAll('main div')].filter(e => e.offsetParent !== null && /Check only|passed the check|was refused|refused|row \d+/i.test(e.innerText) && e.innerText.length < 1500); els.sort((a, b) => a.innerText.length - b.innerText.length); return els.length ? els[0].innerText.replace(/\s+/g, ' ').slice(0, 500) : null; })); await L.shot(p, tag + '_check', true); }
  else log('NO CHECK BUTTON');
  if (process.env.ICHECKONLY) return;
  L.resetLog(p);
  const sub = await L.clickText(p, process.env.ISUBMIT || 'Start import', 'button');
  log('submit', sub);
  await L.idle(p, 3000);
  log('flash', await L.flash(p));
  log('url', p.url());
  await L.shot(p, tag + '_result', true);
};
