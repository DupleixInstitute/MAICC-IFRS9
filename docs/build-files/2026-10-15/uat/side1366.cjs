const L = require('./lib.cjs');
(async () => {
  const b = await L.launch(); const p = await L.newPage(b);
  await p.setViewport({ width: 1366, height: 768 });
  await L.login(p, 'uat.checker@maiic.test', 'UatPass#2026');
  const measure = async (tag) => p.evaluate((tag) => {
    const side = [...document.querySelectorAll('div')].find(d => getComputedStyle(d).position === 'fixed' && d.getBoundingClientRect().height > 500);
    const sr = side ? side.getBoundingClientRect().right : 0;
    const main = document.querySelector('main'); const mr = main ? main.getBoundingClientRect() : { left: -1 };
    const firstText = [...(main ? main.querySelectorAll('h1,h2,nav[aria-label=Breadcrumb]') : [])].map(e => Math.round(e.getBoundingClientRect().left));
    return { tag, url: location.pathname + location.search, sidebarRight: Math.round(sr), mainLeft: Math.round(mr.left), headLefts: firstText.slice(0, 2), scrollX, scrollW: document.documentElement.scrollWidth, overlap: firstText.some(x => x < sr - 1) };
  }, tag);
  const out = [];
  out.push(await measure('dashboard'));
  for (const path of ['/workspace', '/eir-feed?tab=builds', '/eir-data?tab=contracts', '/expected-credit-loss/list', '/ifrs9-reports/sector-ecl', '/audit-trail', '/macro-statistics?tab=dashboard', '/fli', '/eir-fee-classification']) {
    // navigate the way a person does: an Inertia visit from the page, not a reload
    await p.evaluate(u => { const a = document.createElement('a'); a.href = u; window.__inertia_go = u; }, path);
    await p.evaluate(u => window.history && (window.Inertia ? window.Inertia.visit(u) : null), path);
    const linked = await p.evaluate(u => { const a = [...document.querySelectorAll('a[href]')].find(a => a.getAttribute('href').endsWith(u)); if (a) { a.click(); return true } return false; }, path);
    if (!linked) await L.goto(p, path);
    await L.idle(p, 600);
    out.push(await measure(linked ? 'click' : 'goto'));
    await p.screenshot({ path: L.SHOTS + '/S1366_' + path.replace(/[^a-z0-9]+/gi, '_') + '.png' });
  }
  // collapse the sidebar, then expand, then resize from 1440 to 1366
  await p.evaluate(() => { const b = [...document.querySelectorAll('button')].find(b => /Collapse menu/.test(b.textContent)); b && b.click(); }); await L.sleep(500);
  out.push(await measure('collapsed'));
  await p.screenshot({ path: L.SHOTS + '/S1366_collapsed.png' });
  await p.evaluate(() => { const b = [...document.querySelectorAll('button')].find(b => /Expand menu|Collapse menu|expand/i.test(b.textContent + b.title + (b.getAttribute('aria-label') || ''))); b && b.click(); }); await L.sleep(500);
  out.push(await measure('expanded'));
  await p.setViewport({ width: 1440, height: 900 }); await L.sleep(300); await p.setViewport({ width: 1366, height: 768 }); await L.sleep(50);
  out.push(await measure('resized-50ms')); await L.sleep(400); out.push(await measure('resized-450ms'));
  out.forEach(o => console.log(JSON.stringify(o)));
  await b.close();
})();
