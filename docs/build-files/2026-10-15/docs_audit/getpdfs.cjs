'use strict';
const fs = require('fs'), path = require('path');
const puppeteer = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/puppeteer');
const [base, outDir, ...paths] = process.argv.slice(2);
(async () => {
  const b = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const p = await b.newPage();
  for (let i = 0; i < 5; i++) {
    await p.goto(base + '/login', { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => {});
    if (await p.waitForSelector('#email', { timeout: 15000 }).then(() => true).catch(() => false)) break;
  }
  await p.type('#email', 'admin@localhost.com'); await p.type('#password', 'password'); await p.type('#captcha', 'docshot2026').catch(() => {});
  await p.click('button[type="submit"]');
  await p.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 90000 });
  const cookie = (await p.cookies()).map((c) => c.name + '=' + c.value).join('; ');
  await b.close();
  fs.mkdirSync(outDir, { recursive: true });
  for (const u of paths) {
    const t = Date.now();
    const r = await fetch(base + '/' + u, { headers: { cookie }, signal: AbortSignal.timeout(1200000) });
    const buf = Buffer.from(await r.arrayBuffer());
    const name = (r.headers.get('content-disposition') || '').match(/filename="?([^";]+)/);
    const file = path.join(outDir, name ? name[1] : u.replace(/\W+/g, '_') + '.pdf');
    fs.writeFileSync(file, buf);
    console.log(r.status, r.headers.get('content-type'), buf.length, file, ((Date.now() - t) / 1000) + 's');
  }
})().catch((e) => { console.error('FATAL', e); process.exit(1); });
