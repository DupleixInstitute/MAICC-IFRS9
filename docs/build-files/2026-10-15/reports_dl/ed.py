import sys, json
def edit(p, reps):
    raw=open(p,encoding='utf-8',newline='').read()
    crlf='\r\n' in raw
    s=raw.replace('\r\n','\n')
    for o,n in reps:
        assert s.count(o)==1,(p,o[:80],s.count(o))
        s=s.replace(o,n)
    if crlf: s=s.replace('\n','\r\n')
    open(p,'w',encoding='utf-8',newline='').write(s)
