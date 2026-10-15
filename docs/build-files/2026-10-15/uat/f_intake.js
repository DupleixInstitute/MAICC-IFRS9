module.exports = async (L, p, log) => {
  const F = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/files/';
  const type = process.env.ITYPE || 'disbursements';
  await L.goto(p, '/eir-intake?type=' + type);
  const s = await L.hrefOf(p, 'Download sample');
  const r = await L.fetchTo(p, s, F + 'intake_' + type + '.csv'); log('sample', s, r.s, r.cd, r.size);
  log('sel', await p.evaluate(() => document.querySelector('#eir-import-type').value));
  await L.upload(p, F + 'intake_' + type + '.csv');
  L.resetLog(p);
  log('import', await L.clickText(p, 'Import file', 'button')); await L.idle(p, 3000);
  // a second step may ask to confirm a mapping
  const btns = await L.texts(p, 'main button'); log('buttons', btns.filter(b => /import|confirm|continue|load/i.test(b)));
  for (const t of ['Confirm and import', 'Import', 'Continue']) { const b = btns.find(x => x.startsWith(t) && x !== 'Import file'); if (b) { log('step2', await L.clickText(p, b, 'button')); await L.idle(p, 3000); break; } }
  log('flash', await L.flash(p)); log('bad', p._log.bad);
  log('result', await p.evaluate(() => { const h = [...document.querySelectorAll('h3')].find(h => /Import result/.test(h.textContent)); return h ? h.parentElement.innerText.replace(/\s+/g, ' ').slice(0, 500) : null; }));
  await L.shot(p, 'B1h_intake_' + type, true);
};
