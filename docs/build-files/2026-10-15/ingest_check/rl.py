import json,re,sys
r=json.load(open(sys.argv[1]))
for x in r:
  u=x['uri'];n=x['name'] or ''
  if re.search(sys.argv[2],u+' '+n,re.I):
    print(x['method'][:9].ljust(9),u.ljust(66),n.ljust(44),x['action'].split(chr(92))[-1][:70])
