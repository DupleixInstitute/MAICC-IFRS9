// Automated user-manual screenshot capture via headless Chromium (puppeteer).
// Logs into the running MAIIC IFRS 9 platform once, then captures a hi-res
// (2x) screenshot of each configured page. Optionally overlays numbered
// callouts so the manual's "see callout N" instructions line up with images.
//
// Driven by a JSON config written by `php artisan manual:screenshots`:
//   { edge, baseUrl, email, password, captcha?, outDir, viewport:{width,height},
//     shots:[ { url, file, fullPage?, callouts?:[{x,y,n,label?}] } ] }
//
// Usage (normally invoked by the artisan command):
//   node scripts/manual-screenshots.cjs <config.json>
'use strict';

const fs = require('fs');
const path = require('path');
// Prefer the bundled Chromium (full `puppeteer`) so capture never depends on a
// matching system Edge/Chrome version. Fall back to puppeteer-core + an
// explicit executablePath when only core exists.
let puppeteer, BUNDLED = false;
try { puppeteer = require('puppeteer'); BUNDLED = true; }
catch (e) { puppeteer = require('puppeteer-core'); }

const cfgPath = process.argv[2];
if (!cfgPath || !fs.existsSync(cfgPath)) {
  console.error('Config JSON path is required and must exist.');
  process.exit(2);
}
const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
const viewport = Object.assign({ width: 1440, height: 900, deviceScaleFactor: 2 }, cfg.viewport || {});

// Draw numbered callouts (MAIIC gold circles + optional label) over the page
// just before the screenshot. Runs in the browser context.
function drawCallouts(callouts) {
  const layer = document.createElement('div');
  layer.style.cssText = 'position:fixed;inset:0;z-index:2147483647;pointer-events:none;font-family:Arial,Helvetica,sans-serif;';
  (callouts || []).forEach(function (c) {
    const dot = document.createElement('div');
    dot.textContent = String(c.n);
    dot.style.cssText = 'position:absolute;left:' + (c.x - 15) + 'px;top:' + (c.y - 15) + 'px;'
      + 'width:30px;height:30px;border-radius:50%;background:#d97706;color:#fff;'
      + 'display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;'
      + 'box-shadow:0 0 0 3px #fff,0 2px 6px rgba(0,0,0,.4);';
    layer.appendChild(dot);
    if (c.label) {
      const tag = document.createElement('div');
      tag.textContent = c.label;
      tag.style.cssText = 'position:absolute;left:' + (c.x + 20) + 'px;top:' + (c.y - 11) + 'px;'
        + 'background:#14532d;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;white-space:nowrap;';
      layer.appendChild(tag);
    }
  });
  document.body.appendChild(layer);
}

(async () => {
  const launch = {
    headless: 'new',
    defaultViewport: viewport,
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--force-device-scale-factor=1'],
  };
  if (!BUNDLED) launch.executablePath = cfg.edge; // puppeteer-core needs an explicit browser
  const browser = await puppeteer.launch(launch);
  // JPEG keeps the manual small (a full-page PNG is ~5x larger); text stays
  // crisp at quality 87. PNG output is still honoured if a file ends .png.
  const shotOpts = (file, fullPage) => Object.assign(
    { path: file, fullPage: fullPage !== false },
    /\.jpe?g$/i.test(file) ? { type: 'jpeg', quality: 87 } : {},
  );
  const results = [];
  try {
    const page = await browser.newPage();
    await page.setViewport(viewport);
    fs.mkdirSync(cfg.outDir, { recursive: true });

    // ---- Log in (session auth) ----
    await page.goto(cfg.baseUrl + '/login', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('#email, input[type="email"], input[name="email"]', { timeout: 20000 }).catch(() => {});
    await page.evaluate(() => new Promise((res) => {
      const go = () => requestAnimationFrame(() => requestAnimationFrame(res));
      if (document.fonts && document.fonts.ready) { document.fonts.ready.then(go); setTimeout(go, 3000); }
      else { go(); }
    })).catch(() => {});
    await new Promise((r) => setTimeout(r, 600));

    // Capture the login page itself before signing in: the manual's
    // "Logging In" section needs it, and this is the only moment it exists.
    try {
      await page.screenshot(shotOpts(path.join(cfg.outDir, 'login.jpg'), false));
      results.push({ file: 'login.jpg', url: cfg.baseUrl + '/login', ok: true });
      console.log('captured  login.jpg');
    } catch (e) { /* non-fatal */ }

    await page.type('#email, input[type="email"], input[name="email"]', cfg.email, { delay: 12 });
    await page.type('#password, input[type="password"], input[name="password"]', cfg.password, { delay: 12 });
    // The MAIIC login has a self-hosted CAPTCHA. The artisan command passes a
    // one-shot bypass code (config('captcha.manual_code')) valid only when
    // APP_ENV=local; type it if the field is present.
    if (cfg.captcha) {
      await page.type('#captcha, input[name="captcha"]', cfg.captcha, { delay: 12 }).catch(() => {});
    }
    // Inertia submits over XHR (no full navigation), so wait for the path to
    // leave /login rather than a navigation event.
    await page.click('button[type="submit"]').catch(() => page.keyboard.press('Enter'));
    await page.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 90000 }).catch(() => {});
    await new Promise((r) => setTimeout(r, 1000));

    const loginUrl = page.url();
    if (/\/login/.test(loginUrl)) {
      throw new Error('Login failed (still on /login). Check credentials and the CAPTCHA bypass (MANUAL_SHOT_* env, APP_ENV=local).');
    }

    // ---- Capture each page ----
    for (const shot of cfg.shots) {
      try {
        // domcontentloaded (not networkidle0): pages with polling never go
        // network-idle. Wait for the Inertia shell, fonts and charts instead.
        await page.goto(shot.url, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
        await page.waitForSelector('main, #app', { timeout: 12000 }).catch(() => {});
        await page.evaluate(() => new Promise((res) => {
          const go = () => requestAnimationFrame(() => requestAnimationFrame(res));
          if (document.fonts && document.fonts.ready) { document.fonts.ready.then(go); setTimeout(go, 4000); }
          else { go(); }
        })).catch(() => {});
        await new Promise((r) => setTimeout(r, 1200)); // let charts/animations settle
        if (Array.isArray(shot.callouts) && shot.callouts.length) {
          await page.evaluate(drawCallouts, shot.callouts);
        }
        const out = path.join(cfg.outDir, shot.file);
        await page.screenshot(shotOpts(out, shot.fullPage));
        results.push({ file: shot.file, url: shot.url, ok: true });
        console.log('captured  ' + shot.file);
      } catch (e) {
        results.push({ file: shot.file, url: shot.url, ok: false, error: String(e.message || e) });
        console.error('FAILED    ' + shot.file + '  ' + (e.message || e));
      }
    }
  } finally {
    await browser.close();
  }
  fs.writeFileSync(path.join(cfg.outDir, '_manifest.json'), JSON.stringify(results, null, 2));
  const ok = results.filter((r) => r.ok).length;
  console.log('\ndone: ' + ok + '/' + results.length + ' captured -> ' + cfg.outDir);
  process.exit(results.some((r) => !r.ok) ? 1 : 0);
})().catch((e) => {
  console.error('FATAL: ' + (e.message || e));
  process.exit(1);
});
