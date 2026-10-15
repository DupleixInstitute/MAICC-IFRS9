const ui = require('./ui.cjs');
(async () => { const { browser, page } = await ui.open('maker'); await ui.goto(page, '/fli-adjustments?period=2026-09'); const p = await ui.props(page);
  console.log(Object.keys(p).filter(k => !['jetstream','auth','errorBags','errors','user','menu','breadcrumbs','logoUrl','smallLogoUrl','companyName','companyAddress','companyMobile','companyTel','companyEmail','currency','notifications_unread','route_name','currentPeriod'].includes(k)).join(','));
  const fits = (p.fits || []).filter(f => !f.blocked); console.log("fits", (p.fits||[]).length, "unblocked", fits.length);
  console.log(JSON.stringify(fits.slice(0, 6).map(f => ({ id: f.id, verdict: f.verdict, r2: f.r_squared, blocked: f.blocked, can: f.can_propose, status: f.approval_status, stat: f.statistic_code, proxy: f.proxy_code })), null, 0));
  await ui.shot(page, 'A4_fli_adjustments_2026-09'); await browser.close(); })();
