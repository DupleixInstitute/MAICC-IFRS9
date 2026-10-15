// node step.cjs <cookie> <script.js> : runs an async (L, p, log) script with a logged-in page
const L = require('./lib.cjs');
const [ck, script] = process.argv.slice(2);
(async () => {
  const b = await L.launch(); const p = await L.newPage(b, 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/files');
  await L.useCookie(p, ck);
  const log = (...a) => console.log(...a.map(x => typeof x === 'string' ? x : JSON.stringify(x)));
  p.on('dialog', async d => { log('NATIVE DIALOG', d.type(), d.message()); await d.dismiss(); });
  try { await require(require('path').resolve(script))(L, p, log); } catch (e) { log('SCRIPT ERROR', e.message); }
  log('errors', { pageerr: p._log.pageerr, bad: p._log.bad.filter(x => !x.includes('fonts.g')) });
  await b.close();
})();
