// node props.cjs <path> [jsexpr on props]  -> prints Inertia props (via X-Inertia GET)
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(__dirname + '/../../c_auditB.json');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8072/dashboard', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const r = await p.evaluate(async (path) => {
    const version = JSON.parse(document.getElementById('app').dataset.page).version;
    let res; for (let i=0;i<4;i++){ try { res = await fetch(path, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version, 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' } }); break; } catch(e) { await new Promise(r=>setTimeout(r,1500)); } }
    const t = await res.text();
    try { return { status: res.status, j: JSON.parse(t) }; } catch (e) { return { status: res.status, text: t.slice(0, 500) }; }
  }, process.argv[2]);
  if (r.j) { const props = r.j.props; delete props.menu; delete props.auth; delete props.jetstream; delete props.ziggy;
    const out = process.argv[3] ? eval('(' + process.argv[3] + ')')(props) : props;
    console.log(r.status, r.j.component, JSON.stringify(out, null, 0).slice(0, Number(process.env.MAX || 6000))); }
  else console.log(JSON.stringify(r));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
