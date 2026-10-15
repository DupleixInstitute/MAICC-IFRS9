// node dom.cjs <path> <js-expression-returning-json> [shot.png]  (viewport shot)
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(__dirname + '/../../c_auditB.json');
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  const errs=[]; p.on('pageerror', e => errs.push(e.message.slice(0,200))); p.on('console', m => { if (m.type()==='error') errs.push(m.text().slice(0,200)); });
  for (let i=0;i<4;i++){ try { await p.goto('http://127.0.0.1:8072/' + process.argv[2].replace(/^\//,''), { waitUntil: 'networkidle2', timeout: 180000 }); } catch(e){}
    if (await p.evaluate(()=>document.body.scrollHeight>50 && !!document.querySelector('main'))) break; }
  await new Promise(r => setTimeout(r, 1500));
  if (process.argv[4]) await p.screenshot({ path: process.argv[4] });
  const r = await p.evaluate(process.argv[3]);
  console.log(JSON.stringify(r), errs.length? 'ERRS '+JSON.stringify(errs.slice(0,4)):'');
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
