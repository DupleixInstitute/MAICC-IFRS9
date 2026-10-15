const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const c = require(process.argv[2]);
(async () => {
  const b = await puppeteer.launch({ headless: 'new' }); const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8070/transition-matrix', { waitUntil: 'load' });
  await new Promise(r => setTimeout(r, 2000));
  await (await p.$$('button[title="View the matrix"]'))[0].click();
  await new Promise(r => setTimeout(r, 2500));
  const csv = await p.evaluate(() => new Promise(res => {
    const orig = URL.createObjectURL; URL.createObjectURL = (blob) => { blob.text().then(res); return orig(blob); };
    [...document.querySelectorAll('button')].find(x => x.textContent.includes('Download CSV')).click();
  }));
  console.log(csv);
  [...await p.$$('button')];
  await p.evaluate(() => [...document.querySelectorAll('button')].find(x => x.textContent.trim() === 'Close').click());
  await new Promise(r => setTimeout(r, 800));
  console.log('modal open after Close:', await p.evaluate(() => !!document.querySelector('.max-w-7xl')));
  await b.close();
})();
