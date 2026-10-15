// node post.cjs steps.json : [{label, path, method, fields:{}, file:{field,name,path|content}, json:true|false, show:N}]
const fs = require('fs');
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/';
const steps = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
for (const s of steps) if (s.file && s.file.path) s.file.content = fs.readFileSync(s.file.path, 'utf8');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  const c = require(T + 'tools/c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8072/import', { waitUntil: 'load', timeout: 120000 });
  for (const s of steps) {
    const r = await p.evaluate(async (s) => {
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      const version = JSON.parse(document.getElementById('app').dataset.page).version;
      const headers = { Accept: s.json ? 'application/json' : 'text/html, application/xhtml+xml', 'X-XSRF-TOKEN': m ? decodeURIComponent(m[1]) : '', 'X-Requested-With': 'XMLHttpRequest' };
      if (!s.json) { headers['X-Inertia'] = 'true'; headers['X-Inertia-Version'] = version; }
      const fd = new FormData();
      for (const [k, v] of Object.entries(s.fields || {})) fd.append(k, typeof v === 'string' ? v : JSON.stringify(v));
      if (s.file) fd.append(s.file.field, new Blob([s.file.content], { type: 'text/csv' }), s.file.name);
      const res = await fetch(s.path, { method: s.method || 'POST', credentials: 'same-origin', headers, body: (s.method || 'POST') === 'GET' ? undefined : fd, redirect: 'follow' });
      const text = await res.text();
      let out = { status: res.status, url: res.url.replace(location.origin, '') };
      try {
        const j = JSON.parse(text);
        if (s.json) out.body = JSON.stringify(j).slice(0, s.show || 1200);
        else { out.component = j.component; out.flash = j.props && j.props.flash ? Object.fromEntries(Object.entries(j.props.flash).filter(([, v]) => v)) : null; out.errors = j.props && j.props.errors && Object.keys(j.props.errors).length ? j.props.errors : undefined; }
      } catch (e) { out.text = text.slice(0, 300) }
      return out;
    }, s);
    console.log((s.label || s.path) + ' => ' + JSON.stringify(r));
  }
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
