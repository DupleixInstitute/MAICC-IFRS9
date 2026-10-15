import json,re
r=json.load(open('uat_routes.json'))
for x in r:
  if x['method'].startswith(('POST','PUT','PATCH','DELETE')) and re.search(r'eir|feed|loan-book|expected|write|govern|period|workspace|import|fli|scenario|macro|sicr|stag|transition|loss-given|lgd|megafarm|rbm|lock|approve|collateral|client|credit-loss|internal',x['uri']):
    print(x['method'][:6], x['uri'], x['name'], (x['action'] or '').split(chr(92))[-1][:70])
