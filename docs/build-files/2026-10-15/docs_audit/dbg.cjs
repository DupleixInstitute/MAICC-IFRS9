const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
(async () => {
  const b = await puppeteer.launch({ headless: 'new', defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 2 }, args: ['--no-sandbox','--disable-gpu','--force-device-scale-factor=1'] });
  const p = await b.newPage();
  for (let i = 0; i < 5; i++) { await p.goto('http://127.0.0.1:8077/login', { waitUntil: 'networkidle2' }).catch(()=>{}); if (await p.$('#email')) break; }
  await p.type('#email', 'admin@localhost.com'); await p.type('#password', 'password'); await p.type('#captcha', 'docshot2026');
  await p.click('button[type=submit]'); await p.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 90000 });
  for (const u of ['/megafarm']) {
    await p.goto('http://127.0.0.1:8077' + u, { waitUntil: 'networkidle2', timeout: 90000 });
    await new Promise(r => setTimeout(r, 2000)); await p.screenshot({path:'dbg2.jpg', fullPage:false, type:'jpeg'}); await p.screenshot({path:'dbg3.jpg', fullPage:true, captureBeyondViewport:false, type:'jpeg'});
    console.log(u, JSON.stringify(await p.evaluate(() => {
      const h = Array.from(document.querySelectorAll('h1,h2')).find(e=>/Mega Farm|EIR/.test(e.innerText)); const main = h; let wrap = h; const chain=[]; while (wrap && chain.length<12) { chain.push(wrap.tagName+':'+(wrap.className||'').toString().slice(0,70)+':'+Math.round(wrap.getBoundingClientRect().left)); wrap = wrap.parentElement; } window.__chain=chain;
      return { sx: window.scrollX, sw: document.documentElement.scrollWidth, ls: localStorage.getItem('maiic.sidebar.collapsed'), mainLeft: main && main.getBoundingClientRect().left, chain: window.__chain,
        wide: Array.from(document.querySelectorAll('*')).filter(e => e.scrollWidth > 1500 && getComputedStyle(e).overflowX !== 'auto').slice(0,3).map(e => e.tagName + '.' + (e.className||'').toString().slice(0,60) + ' ' + e.scrollWidth) };
    })));
  }
  await b.close();
})();
