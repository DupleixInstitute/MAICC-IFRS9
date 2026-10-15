module.exports = async (L, p, log) => {
  await L.goto(p, '/client');
  log('before', await L.mainText(p, 300));
  log('click', await L.clickText(p, 'Link the loan book', 'button'));
  log('confirm', await L.confirmDlg(p));
  await L.idle(p, 3000);
  log('flash', await L.flash(p));
  await L.shot(p, 'B1b_clients_link', false);
  log('after', await L.mainText(p, 400));
};
