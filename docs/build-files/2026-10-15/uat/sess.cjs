// node sess.cjs <email> <out.json> : logs in via the login page and saves the session cookie
const L = require('./lib.cjs');
const [email, out] = process.argv.slice(2);
(async () => { const b = await L.launch(); const p = await L.newPage(b);
  const u = await L.login(p, email, 'UatPass#2026');
  const c = (await p.cookies()).find(c => /session/.test(c.name));
  L.fs.writeFileSync(out, JSON.stringify({ name: c.name, value: c.value })); console.log(u, c.name); await b.close(); })();
