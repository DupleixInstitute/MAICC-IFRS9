const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const T = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/';
(async () => {
  const b = await puppeteer.launch({ headless: 'new' });
  const p = await b.newPage(); await p.setViewport({ width: 1440, height: 1000 });
  const c = require(T + 'c_fli.json');
  await p.setCookie({ name: c.name, value: c.value, domain: '127.0.0.1', path: '/' });
  await p.goto('http://127.0.0.1:8072' + process.argv[2], { waitUntil: 'load', timeout: 120000 });
  await new Promise(r => setTimeout(r, 3000));
  const out = await p.evaluate(() => {
    const tall = [...document.querySelectorAll('body *')]
      .map(e => ({ e, h: e.getBoundingClientRect().height }))
      .filter(x => x.h > 2500)
      .map(x => x.e.tagName + '.' + String(x.e.className || '').slice(0, 60) + ' h=' + Math.round(x.h) + ' kids=' + x.e.children.length);
    return 'body=' + document.body.scrollHeight + '\n' + tall.slice(-8).join('\n');
  });
  console.log(out);
  await b.close();
})();
