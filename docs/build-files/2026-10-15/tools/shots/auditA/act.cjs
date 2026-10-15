// node act.cjs <steps.json>  (auditA copy: port 8071, cookie c_auditA.json)
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
const steps = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  const c = require(T + 'c_auditA.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8071/dashboard', { waitUntil: 'load', timeout: 120000 });
  for (const s of steps) {
    const r = await p.evaluate(async (s) => {
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      const version = JSON.parse(document.getElementById('app').dataset.page).version;
      const headers = { 'Content-Type': 'application/json', Accept: s.json ? 'application/json' : 'text/html, application/xhtml+xml', 'X-XSRF-TOKEN': m ? decodeURIComponent(m[1]) : '', 'X-Requested-With': 'XMLHttpRequest' };
      if (!s.json && !s.raw) { headers['X-Inertia'] = 'true'; headers['X-Inertia-Version'] = version; }
      if (s.raw) { delete headers['X-Requested-With']; headers.Accept = '*/*'; }
      let body = s.body ? JSON.stringify(s.body) : undefined;
      if (s.csv) { const fd = new FormData(); fd.append(s.field || 'file', new Blob([s.csv], { type: 'text/csv' }), s.filename || 'test.csv'); for (const [k,v] of Object.entries(s.form||{})) fd.append(k,v); body = fd; delete headers['Content-Type']; }
      const res = await fetch(s.path, { method: s.method || 'GET', credentials: 'same-origin', headers, body, redirect: 'follow' });
      const text = await res.text();
      let out = { status: res.status, ctype: res.headers.get('content-type'), disp: res.headers.get('content-disposition'), url: res.url };
      if (s.raw) { out.text = text.slice(0, s.n || 600); return out; }
      try {
        const j = JSON.parse(text);
        if (s.json) out.body = JSON.stringify(j).slice(0, s.n || 1500); else { out.flash = j.props && j.props.flash ? Object.fromEntries(Object.entries(j.props.flash).filter(([, v]) => v)) : null; out.errors = j.props && j.props.errors && Object.keys(j.props.errors).length ? j.props.errors : undefined; out.component = j.component; if (s.props) out.props = JSON.stringify(s.props.map(k=>[k, j.props[k]])).slice(0, s.n||1500); }
      } catch (e) { out.text = text.slice(0, s.n || 400) }
      return out;
    }, s);
    console.log((s.label || s.path) + ' => ' + JSON.stringify(r));
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
