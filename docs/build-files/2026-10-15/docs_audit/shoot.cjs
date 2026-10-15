// Manual screenshot capture (documentation audit, 14 Oct 2026). Same output
// format as scripts/manual-screenshots.cjs (JPEG q87, 1440x900 @2x, light
// theme), plus per-shot actions so tabs, modals, the folded sidebar and
// scrolled chart areas can be captured. Read-only: it clicks only buttons that
// open a view (tabs, modals) and never submits a form.
'use strict';
const fs = require('fs');
const path = require('path');
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');

const cfg = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const only = (process.argv[3] || '').split(',').filter(Boolean);
const viewport = { width: 1440, height: 900, deviceScaleFactor: 2 };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function settle(page) {
  await page.waitForSelector('main, #app', { timeout: 15000 }).catch(() => {});
  await page.evaluate(() => new Promise((res) => {
    const go = () => requestAnimationFrame(() => requestAnimationFrame(res));
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(go); setTimeout(go, 4000); } else { go(); }
  })).catch(() => {});
  await sleep(1500);
}

async function clickText(page, text, tag) {
  const ok = await page.evaluate((text, tag) => {
    const els = Array.from(document.querySelectorAll(tag || 'button, a, [role="tab"], label, summary'));
    const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
    const el = els.find((e) => norm(e.innerText) === text) || els.find((e) => norm(e.innerText).startsWith(text));
    if (!el) return false;
    el.scrollIntoView({ block: 'center' });
    el.click();
    return true;
  }, text, tag);
  if (!ok) throw new Error('clickText not found: ' + text);
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', defaultViewport: viewport, timeout: 120000,
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--force-device-scale-factor=1'] });
  const results = [];
  try {
    const page = await browser.newPage();
    await page.setViewport(viewport);
    // serve the built assets from disk so the single-threaded php -S server
    // only answers the page and its XHRs (no connection resets under load)
    const PUB = 'C:/xampp/htdocs/MAICC-IFRS9/public';
    const TYPES = { '.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf', '.ico': 'image/x-icon', '.json': 'application/json' };
    await page.setRequestInterception(true);
    page.on('request', (req) => {
      try {
        const u = new URL(req.url());
        if (u.origin === new URL(cfg.baseUrl).origin && /^\/(build|images|fonts|favicon|img|css|js)/.test(u.pathname)) {
          const f = path.join(PUB, decodeURIComponent(u.pathname));
          if (fs.existsSync(f) && fs.statSync(f).isFile()) {
            return req.respond({ status: 200, contentType: TYPES[path.extname(f).toLowerCase()] || 'application/octet-stream', body: fs.readFileSync(f) });
          }
        }
      } catch (e) {}
      req.continue();
    });
    await page.evaluateOnNewDocument(() => { try { localStorage.setItem('maiic.theme', 'light'); } catch (e) {} });
    fs.mkdirSync(cfg.outDir, { recursive: true });
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e.message || e)));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('dialog', (d) => d.dismiss().catch(() => {}));

    // login
    for (let i = 0; i < 5; i++) {
      await page.goto(cfg.baseUrl + '/login', { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {});
      await settle(page);
      if (await page.$('#email')) break;
    }
    if (!only.length || only.includes('login')) {
      await page.screenshot({ path: path.join(cfg.outDir, 'login.jpg'), type: 'jpeg', quality: 87 });
      results.push({ file: 'login.jpg', ok: true });
      console.log('captured  login.jpg');
    }
    await page.type('#email, input[name="email"]', cfg.email, { delay: 10 });
    await page.type('#password, input[name="password"]', cfg.password, { delay: 10 });
    await page.type('#captcha, input[name="captcha"]', cfg.captcha, { delay: 10 }).catch(() => {});
    await page.click('button[type="submit"]').catch(() => page.keyboard.press('Enter'));
    await page.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 90000 }).catch(() => {});
    await sleep(1000);
    if (/\/login/.test(page.url())) throw new Error('Login failed');

    for (const shot of cfg.shots) {
      if (only.length && !only.includes(shot.key)) continue;
      errors.length = 0;
      const file = shot.key + '.jpg';
      try {
        // per-shot localStorage (sidebar folded or not), applied before the page loads
        await page.evaluate((ls) => {
          try { localStorage.setItem('maiic.sidebar.collapsed', ls && ls.collapsed ? '1' : '0'); } catch (e) {}
        }, shot.ls || {});
        let resp = null, status = 0;
        for (let attempt = 1; attempt <= 4; attempt++) {
          errors.length = 0;
          resp = await page.goto(cfg.baseUrl + shot.url, { waitUntil: 'networkidle2', timeout: 90000 }).catch((e) => null);
          status = resp ? resp.status() : 0;
          await settle(page);
          const len = await page.evaluate(() => ((document.querySelector('main') || document.body).innerText || '').trim().length).catch(() => 0);
          const broken = errors.some((e) => /dynamically imported module|ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE/.test(e));
          if (!broken && len >= 60) break;
          console.log('retry     ' + file + ' (attempt ' + attempt + ', text ' + len + ')');
          await sleep(1500);
        }
        for (const a of shot.actions || []) {
          if (a.clickText) { await clickText(page, a.clickText, a.tag); await sleep(a.wait || 1200); }
          if (a.click) { await page.click(a.click); await sleep(a.wait || 1200); }
          if (a.scrollTo) {
            await page.evaluate((t) => {
              const el = Array.from(document.querySelectorAll('h1,h2,h3,h4,div,span,p')).find((e) => (e.innerText || '').trim().startsWith(t));
              if (el) { el.scrollIntoView({ block: 'start' }); window.scrollBy(0, -90); }
            }, a.scrollTo);
            await sleep(a.wait || 800);
          }
          if (a.scrollY) { await page.evaluate((y) => window.scrollTo(0, y), a.scrollY); await sleep(600); }
          if (a.eval) { await page.evaluate(a.eval); await sleep(a.wait || 800); }
        }
        const scrolled = await page.evaluate(() => {
          const out = [];
          if (window.scrollX) { out.push('window ' + window.scrollX); window.scrollTo(0, window.scrollY); }
          document.querySelectorAll('*').forEach((e) => { if (e.scrollLeft > 0 && !/overflow|table|wrap/i.test(e.className && e.className.toString ? e.className.toString() : '')) { out.push(e.tagName + '.' + (e.className || '').toString().slice(0, 40) + ' ' + e.scrollLeft); e.scrollLeft = 0; } });
          return out;
        }).catch(() => []);
        if (scrolled.length) console.log('unscrolled ' + file + ' ' + scrolled.join(' | '));
        await page.screenshot({ path: path.join(cfg.outDir, file), fullPage: !!shot.fullPage, captureBeyondViewport: false, type: 'jpeg', quality: 87 });
        const info = await page.evaluate(() => ({
          title: (document.querySelector('h1, h2') || {}).innerText || '',
          text: ((document.querySelector('main') || document.body).innerText || '').trim().length,
          url: location.pathname + location.search,
        })).catch(() => ({}));
        const r = { file, url: shot.url, finalUrl: info.url, status, ok: true, textLength: info.text, title: info.title, errors: errors.slice(0, 4) };
        if (status >= 400 || info.text < 60 || /\/login/.test(info.url || '')) r.warn = true;
        results.push(r);
        console.log((r.warn ? 'WARN      ' : 'captured  ') + file + '  [' + status + '] ' + (info.title || '').slice(0, 60) + (errors.length ? '  errors: ' + errors.slice(0, 2).join(' | ').slice(0, 200) : ''));
      } catch (e) {
        results.push({ file, url: shot.url, ok: false, error: String(e.message || e) });
        console.error('FAILED    ' + file + '  ' + (e.message || e));
      }
    }
  } finally {
    await browser.close();
  }
  fs.writeFileSync(path.join(cfg.manifestDir || cfg.outDir, '_shoot_manifest_' + Date.now() + '.json'), JSON.stringify(results, null, 2));
  console.log('done: ' + results.filter((r) => r.ok).length + '/' + results.length);
})().catch((e) => { console.error('FATAL ' + (e.message || e)); process.exit(1); });
