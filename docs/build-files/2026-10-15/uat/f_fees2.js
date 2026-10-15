module.exports = async (L, p, log) => {
  await L.goto(p, '/eir-fee-classification?contract_id=' + process.env.C);
  log('markers', await p.evaluate(() => [...document.querySelectorAll('.maiic-badge')].filter(b => /Superseded by/.test(b.textContent)).map(b => b.textContent.trim())));
  const y = await p.evaluate(() => { const b = [...document.querySelectorAll('.maiic-badge')].find(b => /Superseded by/.test(b.textContent)); if (b) { b.scrollIntoView(); } return !!b; });
  await L.shot(p, 'E1_fees_superseded_marker', false); await L.dark(p, true); await L.shot(p, 'E1_fees_superseded_marker_dark', false);
};
