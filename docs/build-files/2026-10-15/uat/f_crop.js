module.exports = async (L, p, log) => {
  await L.goto(p, process.env.CURL);
  for (const mode of [false, true]) {
    await L.dark(p, mode);
    const el = await p.evaluateHandle(t => [...document.querySelectorAll('main *')].find(e => e.offsetParent && e.textContent.trim().startsWith(t) && e.children.length < 3).closest('.maiic-panel, table, section, div.rounded-lg') , process.env.CTEXT);
    await el.evaluate(e => e.scrollIntoView());
    await L.sleep(300);
    await el.screenshot({ path: L.SHOTS + '/' + process.env.CTAG + (mode ? '_dark' : '') + '.png' });
  }
};
