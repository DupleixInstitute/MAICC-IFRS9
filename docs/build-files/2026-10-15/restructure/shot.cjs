'use strict';
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const out = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/restructure/';
(async () => {
  const browser = await puppeteer.launch({ headless: 'new', defaultViewport: { width: 1440, height: 1000 }, timeout: 120000, args: ['--no-sandbox', '--disable-gpu'] });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
  await page.goto('http://127.0.0.1:8097/login', { waitUntil: 'load', timeout: 120000 });
  await page.waitForSelector('input[type=password]', { timeout: 120000 }); await page.type('input[type=email], input[name=email]', 'restructure.check@example.test');
  await page.type('input[type=password]', 'Check-Restructure-2026'); if (await page.$('#captcha')) await page.type('#captcha', 'ABCDE');
  await page.click('button[type=submit]'); await page.waitForFunction(() => !location.pathname.includes('login'), { timeout: 300000 });
  for (const tab of ['register', 'resets']) {
    await page.goto('http://127.0.0.1:8097/eir-restructures?tab=' + tab, { waitUntil: 'load', timeout: 180000 });
    await page.waitForSelector('table', { timeout: 180000 }); await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: out + `shot-${tab}-light.png`, fullPage: false });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await new Promise(r => setTimeout(r, 300));
    await page.screenshot({ path: out + `shot-${tab}-dark.png`, fullPage: false });
    await page.evaluate(() => document.documentElement.classList.remove('dark'));
  }
  // the classify modal, in dark mode
  await page.goto('http://127.0.0.1:8097/eir-restructures', { waitUntil: 'load', timeout: 180000 }); await page.waitForSelector('button[title=Classify]', { timeout: 180000 });
  const btn = await page.$('button[title=Classify]');
  if (btn) {
    await btn.click();
    await new Promise(r => setTimeout(r, 500));
    await page.select('#rs-class', 'MODIFICATION');
    await new Promise(r => setTimeout(r, 300));
    await page.screenshot({ path: out + 'shot-modal-light.png' });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await new Promise(r => setTimeout(r, 300));
    await page.screenshot({ path: out + 'shot-modal-dark.png' });
  } else {
    errors.push('no Classify button found');
  }
  console.log(JSON.stringify(errors));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
