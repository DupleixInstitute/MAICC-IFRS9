import json
r=json.load(open('uat_routes.json'))
want=[]
for x in r:
  if 'GET' not in x['method'] or '{' in x['uri']: continue
  n=x['name'] or ''
  if n.startswith(('ifrs9-reports.','rbm-return.index','auditor-pack.index','stress-testing.index','reports.loan-book-reconciliation','reports.ecl-reconciliation','eir-intake.index','clients.import.create','loan_applications.loan-book.import.create','loan_applications.loan-book.summary','collateral.register.import','credit-loss-data.importView','credit-loss-data.create','credit-loss-data.definition','expected-credit-loss.create','expected-credit-loss.projections','expected-credit-loss.reports','expected-credit-loss.summary','lgd-calculations.index','lgd-payment-report.index','transition-matrices.create','transition-matrix-cummulative.create','transition-profiles.create','loss-given-default.create','lgd-cummulative.create','fli.transmission-methods','fli.scenarios.create','stageing-rules.index','tickets.create','users.create','users.roles.create','portfolios.create','groups.create','accounting.financial_periods.create','collateral.allocate','help.manage.index','license.index','eir-feed.queries','loan_applications.contracts.index','macro-statistics.legacy','regression.index','dashboard.my-workspace','settings.')):
    if n.endswith(('-pdf','.pdf','export','.ai-narrative')): continue
    want.append({'menu':'(not in menu)','tab':'','route':n,'url':'/'+x['uri']})
json.dump(want,open('uat/extra_urls.json','w'),indent=1)
print(len(want))
for w in want: print(w['url'])
