const L = require('./lib.cjs');
const urls = process.argv.slice(2);
(async () => {
  const b = await L.launch(); const p = await L.newPage(b);
  await p.setViewport({ width: 1366, height: 768 });
  await L.useCookie(p, '../tools/c_fli.json');
  for (const u of urls) {
    await L.goto(p, u);
    const r = await p.evaluate(() => {
      const de = document.documentElement;
      const side = [...document.querySelectorAll('div')].find(d => getComputedStyle(d).position === 'fixed' && d.getBoundingClientRect().height > 500);
      const sw = side ? side.getBoundingClientRect().width : null;
      const main = document.querySelector('main') || document.body; const mr = main.getBoundingClientRect();
      // elements whose left edge sits under the sidebar
      const under = [...main.querySelectorAll('*')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 50 && r.height > 10 && r.left < (sw || 0) - 2 && r.right > 0 && getComputedStyle(e).position !== 'fixed'; }).slice(0, 5).map(e => e.tagName + '.' + String(e.className).slice(0, 60) + ' L' + Math.round(e.getBoundingClientRect().left));
      return { scrollW: de.scrollWidth, innerW: innerWidth, sidebarW: sw, mainLeft: mr.left, collapsed: localStorage.getItem('maiic.sidebar.collapsed'), under };
    });
    console.log(u, JSON.stringify(r));
    await p.screenshot({ path: L.SHOTS + '/W1366_' + u.replace(/[^a-z0-9]+/gi, '_') + '.png' });
  }
  await b.close();
})();
