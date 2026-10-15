import json, sys
for l in open(sys.argv[1], encoding='utf-8'):
    d = json.loads(l)
    h = d.pop('headers')
    sha = d.pop('sha256')
    print(json.dumps(d))
    print('   sha:', sha[:16], ' H:', ','.join(h)[:300])
