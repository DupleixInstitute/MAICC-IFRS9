// node act.cjs <steps.json>
// steps: [{ "as": "a"|"b", "method": "POST", "path": "/x", "body": {...}, "json": false }]
// Runs each request from a logged-in browser page (a = admin c_fli.json, b = demo approver c_b.json),
// with the XSRF token and Inertia headers, and prints the status and the flash message that came back.
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
const steps = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const pages = {};
  for (const [k, f] of [['a', 'c_auditB.json']]) {
    const ctx = await b.createBrowserContext();
    const p = await ctx.newPage();
    const c = require(T + f);
    await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
    await p.goto('http://127.0.0.1:8072/dashboard', { waitUntil: 'load', timeout: 120000 });
    pages[k] = p;
  }
  for (const s of steps) {
    const p = pages[s.as || 'a'];
    const r = await p.evaluate(async (s) => {
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      const version = JSON.parse(document.getElementById('app').dataset.page).version;
      const headers = { 'Content-Type': 'application/json', Accept: s.json ? 'application/json' : 'text/html, application/xhtml+xml', 'X-XSRF-TOKEN': m ? decodeURIComponent(m[1]) : '', 'X-Requested-With': 'XMLHttpRequest' };
      if (!s.json) { headers['X-Inertia'] = 'true'; headers['X-Inertia-Version'] = version; }
      let body = s.body ? JSON.stringify(s.body) : undefined;
      if (s.csv) { const fd = new FormData(); fd.append('file', new Blob([s.csv], { type: 'text/csv' }), 'test.csv'); body = fd; delete headers['Content-Type']; }
      const res = await fetch(s.path, { method: s.method || 'GET', credentials: 'same-origin', headers, body });
      const text = await res.text();
      let out = { status: res.status };
      try {
        const j = JSON.parse(text);
        if (s.json) out.body = j; else out.flash = j.props && j.props.flash ? Object.fromEntries(Object.entries(j.props.flash).filter(([, v]) => v)) : null, out.errors = j.props && j.props.errors && Object.keys(j.props.errors).length ? j.props.errors : undefined, out.component = j.component;
      } catch (e) { out.text = text.slice(0, 300) }
      return out;
    }, s);
    console.log((s.label || s.path) + ' => ' + JSON.stringify(r));
    if (s.pollRun && r.body && r.body.id) {
      for (let i = 0; i < 400; i++) {
        await new Promise(x => setTimeout(x, 2000));
        const o = await p.evaluate(async (id) => (await (await fetch('/fli/run/' + id, { headers: { Accept: 'application/json' } })).json()), r.body.id);
        if (o.run && o.run.status !== 'running') { console.log('   run ' + o.run.status + ' in ' + o.run.duration + '\n' + o.run.output.split('\n').slice(-25).join('\n')); break; }
      }
    }
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
