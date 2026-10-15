// node click.cjs <path> <steps.json> <out.png>  steps: [{click:"text"} | {sel:"css", click:true} | {type:"text", sel:"css"} | {select:"css", value:"v"} | {wait:ms} | {eval:"js"}]
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(__dirname + '/../../c_auditB.json');
const steps = require(require('path').resolve(process.argv[3]));
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs=[], bad=[]; p.on('pageerror', e => errs.push(e.message.slice(0,200))); p.on('console', m => { if (m.type()==='error') errs.push(m.text().slice(0,200)); });
  p.on('response', r => { if (r.status() >= 400) bad.push(r.status() + ' ' + r.url()); });
  p.on('dialog', async d => { console.log('DIALOG', d.type(), d.message()); await d.dismiss(); });
  for (let i=0;i<4;i++){ try { await p.goto('http://127.0.0.1:8072/' + process.argv[2].replace(/^\//,''), { waitUntil: 'networkidle2', timeout: 180000 }); } catch(e){}
    if (await p.evaluate(()=>document.body.scrollHeight>50 && !!document.querySelector('main'))) break; }
  await new Promise(r => setTimeout(r, 1000));
  for (const s of steps) {
    try {
      if (s.wait) await new Promise(r => setTimeout(r, s.wait));
      if (s.click && !s.sel) {
        const ok = await p.evaluate((t, within) => { const root = within ? document.querySelector(within) : document; const els=[...root.querySelectorAll('button,a,[role=button],label,span,div,td,th,li')].filter(e=>e.offsetParent!==null && e.innerText && e.innerText.trim()===t); const el = els[els.length-1]; if(!el) return false; el.click(); return true; }, s.click, s.within||null);
        console.log('click', s.click, ok);
      }
      if (s.sel && s.click === true) { await p.click(s.sel); console.log('click', s.sel); }
      if (s.type !== undefined) { await p.focus(s.sel); await p.evaluate(sel=>{const e=document.querySelector(sel); e.value='';}, s.sel); await p.type(s.sel, String(s.type)); console.log('type', s.sel); }
      if (s.select) { await p.select(s.select, s.value); console.log('select', s.select, s.value); }
      if (s.eval) console.log('eval', JSON.stringify(await p.evaluate(s.eval)));
      if (s.shot) await p.screenshot({ path: s.shot, fullPage: !!s.full });
    } catch (e) { console.log('STEP ERR', JSON.stringify(s), e.message.slice(0,200)); }
  }
  await new Promise(r => setTimeout(r, 1500));
  await p.screenshot({ path: process.argv[4], fullPage: true });
  console.log('url', p.url(), 'errs', JSON.stringify(errs.filter(e=>!e.includes('manual')&&!e.includes('401')).slice(0,5)), 'bad', JSON.stringify(bad.filter(x=>!x.includes('manuals')).slice(0,5)));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
