module.exports = async (L, p, log) => {
  await L.goto(p, process.env.DURL);
  log(await p.evaluate(() => ({ files: document.querySelectorAll('input[type=file]').length, links: [...document.querySelectorAll('a[href]')].map(a => a.getAttribute('href')).filter(h => /sample|import/.test(h)).slice(0, 20) })));
  log(await L.mainText(p, 1500));
  await L.shot(p, 'dbg', false);
};
