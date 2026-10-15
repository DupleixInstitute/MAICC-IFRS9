import json,sys
for f in sys.argv[1:]:
  for l in open(f):
    r=json.loads(l)
    e=r.get('errors',{}) or {}
    bad=[b for b in e.get('bad',[]) if 'fonts.g' not in b]
    flags=[]
    if r.get('status')!=200: flags.append('STATUS %s'%r.get('status'))
    if r.get('fatal'): flags.append('FATAL '+r['fatal'][:100])
    if r.get('err'): flags.append('ERRTEXT '+r['err'][:150])
    if e.get('pageerr'): flags.append('JS '+'; '.join(e['pageerr'])[:200])
    if e.get('console'): flags.append('CONSOLE '+'; '.join(e['console'])[:200])
    if bad: flags.append('HTTP '+'; '.join(bad)[:200])
    if r.get('unstyled'): flags.append('UNSTYLED')
    if (r.get('hOverflow') or 0)>0: flags.append('HOVERFLOW %s'%r['hOverflow'])
    for x in r.get('exports',[]):
      if x.get('s')!=200 or not x.get('size'): flags.append('EXPORT %s %s %s %s'%(x.get('t'),x.get('h'),x.get('s'),x.get('size')))
    if r.get('searchErr'): flags.append('SEARCH '+str(r['searchErr'])[:150])
    dw=(r.get('darkWhite') or {}).get('count',0)
    if dw: flags.append('DARKWHITE %d %s'%(dw,(r['darkWhite']['ex'][:2])))
    print(r['slug'][:40].ljust(40), r.get('crumbs','')[:70].ljust(70), ' | '.join(flags) if flags else 'OK')
