// Shared UAT helpers: login via the login page, visit with error capture, screenshots.
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const fs = require('fs');
const BASE = process.env.UAT_BASE || 'http://127.0.0.1:8073';
const SHOTS = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/s';
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function launch() {
  const browser = await puppeteer.launch({ headless: 'new', protocolTimeout: 600000 });
  return browser;
}
async function newPage(browser, dl) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  page.setDefaultTimeout(300000);
  page.setDefaultNavigationTimeout(300000);
  page._log = { console: [], pageerr: [], bad: [] };
  page.on('console', m => { if (m.type() === 'error') page._log.console.push(m.text().slice(0, 300)); });
  page.on('pageerror', e => page._log.pageerr.push(String(e.message).slice(0, 300)));
  page.on('response', r => { if (r.status() >= 400 && !r.url().includes('favicon')) page._log.bad.push(r.status() + ' ' + r.request().method() + ' ' + r.url().replace(BASE, '')); });
  if (dl) {
    const cdp = await page.target().createCDPSession();
    await cdp.send('Browser.setDownloadBehavior', { behavior: 'allow', downloadPath: dl, eventsEnabled: true });
  }
  return page;
}
function resetLog(page) { page._log = { console: [], pageerr: [], bad: [] }; }
async function idle(page, ms = 1500) {
  try { await page.waitForNetworkIdle({ idleTime: 800, timeout: 300000 }); } catch (e) {}
  await sleep(ms);
}
async function goto(page, path, tries = 5) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await page.goto(BASE + path, { waitUntil: ['load', 'networkidle2'], timeout: 300000 });
      await idle(page, 800);
      const styled = await page.evaluate(() => { const ls = [...document.querySelectorAll('link[rel=stylesheet]')].filter(l => l.href.includes('/build/')); return ls.every(l => !!l.sheet) && !!document.querySelector('#app *'); });
      if (!styled && i < tries - 1) { await sleep(2000); continue; }
      page._unstyled = !styled;
      return r ? r.status() : 0;
    } catch (e) {
      if (i === tries - 1) throw e;
      await sleep(3000);
    }
  }
}
async function useCookie(page, file) {
  const c = JSON.parse(fs.readFileSync(file, 'utf8'));
  const u = new URL(BASE);
  await page.setCookie({ name: c.name, value: c.value, domain: u.hostname, path: '/' });
}
async function login(page, email, pw) {
  await goto(page, '/login');
  await page.type('#email', email);
  await page.type('#password', pw);
  await page.type('#captcha', 'UATBOT7');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), page.click('button[type=submit]')]);
  await idle(page);
  return page.url();
}
async function info(page) {
  return page.evaluate(() => {
    const crumbs = [...document.querySelectorAll('nav[aria-label=Breadcrumb] li')].map(l => l.textContent.trim()).filter(x => x && x !== '...').join(' > ');
    const h = document.querySelector('header h1, h1, h2');
    const body = document.body.innerText;
    const err = /Server Error|Whoops|SQLSTATE|Exception|Undefined (array key|variable|index)|Call to a member|Trying to access|ErrorException/i.exec(body);
    const inertiaModal = !!document.querySelector('dialog iframe, div[style*="z-index: 200000"] iframe');
    return { crumbs, title: h ? h.textContent.trim().slice(0, 80) : '', err: err ? body.slice(Math.max(0, err.index - 80), err.index + 200).replace(/\s+/g, ' ') : '', inertiaModal, len: body.length };
  });
}
async function shot(page, name, full = true) {
  const p = SHOTS + '/' + name + '.png';
  try { await page.screenshot({ path: p, fullPage: full }); } catch (e) { await page.screenshot({ path: p }); }
  return p;
}
async function dark(page, on) {
  await page.evaluate(on => { document.documentElement.classList.toggle('dark', on); }, on);
  await sleep(300);
}
async function clickText(page, text, sel = 'button, a') {
  return page.evaluate((text, sel) => {
    const els = [...document.querySelectorAll(sel)].filter(e => e.offsetParent !== null);
    const el = els.find(x => x.textContent.replace(/\s+/g, ' ').trim() === text) || els.find(x => x.textContent.replace(/\s+/g, ' ').trim().startsWith(text));
    if (el) { el.click(); return el.textContent.replace(/\s+/g, ' ').trim().slice(0, 60); }
    return null;
  }, text, sel);
}
async function texts(page, sel) { return page.$$eval(sel, els => els.filter(e => e.offsetParent !== null).map(e => e.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean)); }
module.exports = { useCookie, BASE, SHOTS, sleep, launch, newPage, resetLog, idle, goto, login, info, shot, dark, clickText, texts, fs };
// ---- flow helpers ----
async function flash(page) {
  return page.evaluate(() => [...document.querySelectorAll('[role=alert], [role=status]')].filter(e => e.offsetParent !== null).map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean).join(' || ').slice(0, 600));
}
async function fetchTo(page, href, out) {
  const r = await page.evaluate(async h => { const r = await fetch(h, { credentials: 'same-origin' }); const b = new Uint8Array(await r.arrayBuffer()); return { s: r.status, ct: r.headers.get('content-type'), cd: r.headers.get('content-disposition'), data: Array.from(b) }; }, href);
  if (out) fs.writeFileSync(out, Buffer.from(r.data));
  return { s: r.s, ct: r.ct, cd: r.cd, size: r.data.length };
}
async function hrefOf(page, text) {
  return page.evaluate(t => { const a = [...document.querySelectorAll('a[href]')].find(a => a.offsetParent !== null && a.textContent.replace(/\s+/g, ' ').trim().startsWith(t)); return a ? a.getAttribute('href') : null; }, text);
}
async function upload(page, file, idx = 0) {
  const ins = await page.$$('input[type=file]');
  await ins[idx].uploadFile(file);
  await sleep(800);
}
async function mainText(page, n = 1500) { return page.evaluate(n => (document.querySelector('main') || document.body).innerText.replace(/\s+/g, ' ').slice(0, n), n); }
module.exports.flash = flash; module.exports.fetchTo = fetchTo; module.exports.hrefOf = hrefOf; module.exports.upload = upload; module.exports.mainText = mainText;
// click the confirm button of the shared ConfirmDialog (mounted outside #app), or of a modal
async function confirmDlg(page, label) {
  await sleep(500);
  const r = await page.evaluate(label => {
    const app = document.querySelector('#app');
    const btns = [...document.querySelectorAll('button')].filter(b => b.offsetParent !== null && !app.contains(b));
    const t = b => b.textContent.replace(/\s+/g, ' ').trim();
    const dlgText = btns.length ? (btns[0].closest('[role=dialog]') || btns[0].parentElement.parentElement.parentElement).innerText.replace(/\s+/g, ' ').slice(0, 300) : null;
    const b = (label && btns.find(b => t(b).startsWith(label))) || btns[btns.length - 1];
    if (!b) return null; const txt = t(b); b.click(); return { clicked: txt, dlgText };
  }, label);
  return r;
}
module.exports.confirmDlg = confirmDlg;
async function setVal(page, sel, v) {
  return page.evaluate((sel, v) => { const el = document.querySelector(sel); if (!el) return false; const proto = el.tagName === 'SELECT' ? HTMLSelectElement.prototype : el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype; Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, v); el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); return true; }, sel, v);
}
module.exports.setVal = setVal;
