const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const fs = require('fs');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8072/internal-grading', { waitUntil: 'load', timeout: 120000 });
  const b64 = fs.readFileSync(T + '../ecl_dec2025.xlsx').toString('base64');
  const r = await p.evaluate(async (b64) => {
    const bin = Uint8Array.from(atob(b64), ch => ch.charCodeAt(0));
    const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), 'model.xlsx');
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    const res = await fetch('/internal-grading/import-sp/preview', { method: 'POST', body: fd, headers: { 'X-XSRF-TOKEN': decodeURIComponent(m[1]), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    return res.status + ' ' + (await res.text()).slice(0, 300);
  }, b64);
  console.log(r);
  await b.close();
})();
