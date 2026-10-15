module.exports = async (L, p, log) => {
  await L.goto(p, '/fli-adjustments?period=2026-09');
  const rows = [];
  for (let pg = 1; pg <= 8; pg++) {
    rows.push(...await p.evaluate(() => [...document.querySelectorAll('main table tbody tr')].map(r => r.innerText.replace(/\s+/g, ' ')).filter(t => /Applied/.test(t))));
    const ok = await p.evaluate(n => { const b = [...document.querySelectorAll('main button')].find(b => b.offsetParent && b.textContent.trim() === String(n)); if (b) { b.click(); return true } return false; }, pg + 1);
    if (!ok) break; await L.sleep(500);
  }
  rows.forEach(r => log(r.slice(0, 160)));
};
