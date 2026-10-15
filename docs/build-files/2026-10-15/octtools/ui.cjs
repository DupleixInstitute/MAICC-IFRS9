'use strict';
// Small puppeteer helper for the October final check (server on 127.0.0.1:8078).
const fs = require('fs');
const path = require('path');
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');

const BASE = 'http://127.0.0.1:8078';
const SCRATCH = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad';
const OUT = SCRATCH + '/oct';
const USERS = {
  maker: { email: 'oct.maker@maiic.local', password: 'OctMaker#2026check' },
  checker: { email: 'oct.checker@maiic.local', password: 'OctChecker#2026check' },
};
const CAPTCHA = 'OCTCHECK';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function open(who) {
  const browser = await puppeteer.launch({
    headless: 'new', timeout: 120000, protocolTimeout: 900000,
    userDataDir: SCRATCH + '/octprofiles/' + who,
    defaultViewport: { width: 1440, height: 900 },
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(600000);
  page.setDefaultNavigationTimeout(600000);
  page.on('pageerror', (e) => console.log('[pageerror]', String(e.message || e).slice(0, 300)));
  await goto(page, '/workspace');
  if (/\/login/.test(page.url())) {
    await page.waitForSelector('#email');
    await page.type('#email', USERS[who].email);
    await page.type('#password', USERS[who].password);
    await page.type('#captcha', CAPTCHA);
    await page.click('button[type="submit"]');
    await page.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 300000 });
    await sleep(1000);
  }
  if (/\/login/.test(page.url())) throw new Error('login failed for ' + who);
  return { browser, page };
}

async function goto(page, url, tries = 4) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await page.goto(url.startsWith('http') ? url : BASE + url, { waitUntil: 'networkidle2', timeout: 600000 });
      await sleep(800);
      return r;
    } catch (e) {
      console.log('goto retry', url, e.message.slice(0, 120));
      await sleep(3000);
    }
  }
  throw new Error('goto failed ' + url);
}

async function shot(page, name, full = true) {
  fs.mkdirSync(OUT, { recursive: true });
  const file = path.join(OUT, name + '.png');
  await page.screenshot({ path: file, fullPage: full });
  return file;
}

// A request from inside the signed-in page, with Laravel's XSRF cookie, as the screens send it.
async function api(page, method, url, body, headers = {}) {
  return page.evaluate(async (method, url, body, headers) => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    const h = Object.assign({ 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, headers);
    if (m) h['X-XSRF-TOKEN'] = decodeURIComponent(m[1]);
    let payload;
    if (body && !(body instanceof FormData)) { h['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    const r = await fetch(url, { method, headers: h, body: payload, credentials: 'same-origin', redirect: 'follow' });
    const t = await r.text();
    let flash = null, errors = null;
    if (t.includes('data-page=')) {
      try {
        const d = new DOMParser().parseFromString(t, 'text/html');
        const pg = JSON.parse(d.getElementById('app').getAttribute('data-page'));
        flash = pg.props.flash || null; errors = pg.props.errors || null;
      } catch (e) {}
      return { status: r.status, url: r.url, flash, errors };
    }
    return { status: r.status, url: r.url, body: t.slice(0, 20000) };
  }, method, url, body || null, headers);
}

// The flash message shown on an Inertia page (props.flash), read from the page's data-page.
async function props(page) {
  return page.evaluate(() => {
    const el = document.getElementById('app');
    try { return JSON.parse(el.getAttribute('data-page')).props; } catch (e) { return null; }
  });
}

async function text(page) { return page.evaluate(() => document.body.innerText); }

module.exports = { open, goto, shot, api, props, text, sleep, BASE, OUT, SCRATCH };

// A form post the way an Inertia screen posts it, without following the redirect; the flash is
// then read from the page the redirect leads back to.
async function post(page, url, body) {
  const here = page.url();
  const r = await page.evaluate(async (url, body) => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    const h = { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', Accept: 'text/html' };
    if (m) h['X-XSRF-TOKEN'] = decodeURIComponent(m[1]);
    try {
      const r = await fetch(url, { method: 'POST', headers: h, body: JSON.stringify(body || {}), credentials: 'same-origin', redirect: 'manual' });
      return { status: r.status, type: r.type, body: r.type === 'opaqueredirect' ? null : (await r.text()).slice(0, 2000) };
    } catch (e) { return { status: -1, error: String(e) }; }
  }, url, body);
  await goto(page, here);
  const p = await props(page);
  return Object.assign(r, { flash: p ? p.flash : null });
}
module.exports.post = post;
